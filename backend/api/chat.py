from fastapi import APIRouter, HTTPException, Depends, BackgroundTasks, Request
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from pydantic import BaseModel, Field
from typing import Dict, Any, Optional, List
import logging
import uuid
from datetime import datetime

from backend.core.rag_engine import rag_engine
from backend.services.session_manager import session_manager
from backend.services.analytics_store import analytics_store
from backend.services.langchain_tools import execute_tool, get_available_tools
from backend.utils.security import rate_limiter, input_validator
from backend.config.settings import settings

logger = logging.getLogger(__name__)
router = APIRouter(prefix="/chat", tags=["chat"])
security = HTTPBearer(auto_error=False)


def _get_client_id(request: Request) -> str:
    """Resolve a stable client identifier for rate limiting."""
    forwarded_for = request.headers.get("x-forwarded-for")
    if forwarded_for:
        return forwarded_for.split(",")[0].strip()

    if request.client and request.client.host:
        return request.client.host

    return "anonymous"


class ChatRequest(BaseModel):
    message: str = Field(..., description="User message", min_length=1, max_length=1000)
    session_id: Optional[str] = Field(None, description="Session ID for conversation continuity")
    context: Optional[Dict[str, Any]] = Field(None, description="Additional context")
    use_tools: Optional[bool] = Field(False, description="Whether to use LangChain tools")
    language: Optional[str] = Field("en", description="Preferred response language")


class ChatResponse(BaseModel):
    response: str
    session_id: str
    sources: Optional[List[Dict[str, Any]]] = None
    tool_results: Optional[Dict[str, Any]] = None
    confidence: Optional[Dict[str, Any]] = None
    handoff: Optional[Dict[str, Any]] = None
    suggested_actions: Optional[List[Dict[str, Any]]] = None
    language: Optional[str] = None
    intent: Optional[str] = None
    timestamp: str
    query_type: str


class ToolRequest(BaseModel):
    tool_name: str = Field(..., description="Name of the tool to execute")
    parameters: Dict[str, Any] = Field(default_factory=dict, description="Tool parameters")
    session_id: Optional[str] = Field(None, description="Session ID")


class ToolResponse(BaseModel):
    result: Dict[str, Any]
    session_id: Optional[str] = None
    timestamp: str


class SearchRequest(BaseModel):
    query: str = Field(..., min_length=1, max_length=500)
    limit: int = Field(default=5, ge=1, le=10)


class FeedbackRequest(BaseModel):
    session_id: str
    message_content: str
    helpful: bool
    comment: Optional[str] = Field(default=None, max_length=500)
    intent: Optional[str] = None


async def verify_session(session_id: Optional[str] = None) -> str:
    """Verify or create session"""
    if session_id:
        session = await session_manager.get_session(session_id)
        if session:
            return session_id
    
    # Create new session
    new_session_id = str(uuid.uuid4())
    await session_manager.create_session(
        new_session_id,
        {"created_at": datetime.now().isoformat(), "source": "api"}
    )
    return new_session_id


@router.post("/query", response_model=ChatResponse)
async def chat_query(
    request_context: Request,
    request: ChatRequest,
    background_tasks: BackgroundTasks,
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(security)
):
    """Main chat endpoint"""
    try:
        # Rate limiting check
        client_id = _get_client_id(request_context)
        if not rate_limiter.is_allowed(client_id):
            raise HTTPException(status_code=429, detail="Rate limit exceeded")

        sanitized_message = input_validator.sanitize_input(request.message)
        if not sanitized_message:
            raise HTTPException(status_code=400, detail="Message cannot be empty")
        
        # Verify/create session
        session_id = await verify_session(request.session_id)
        session_context = await session_manager.get_context(session_id)
        preferred_language = (request.language or session_context.get("language") or "en").lower()
        
        # Add user message to history
        await session_manager.add_message(
            session_id,
            {
                "role": "user",
                "content": sanitized_message,
                "timestamp": datetime.now().isoformat()
            }
        )
        
        # Process the query
        if request.use_tools:
            # Try to use LangChain tools first
            response_data = await _process_with_tools(sanitized_message, session_id, preferred_language)
        else:
            # Use RAG engine
            response_data = await rag_engine.query(
                sanitized_message,
                session_id,
                preferred_language=preferred_language
            )
        
        # Add bot response to history
        await session_manager.add_message(
            session_id,
            {
                "role": "assistant",
                "content": response_data["answer"],
                "timestamp": datetime.now().isoformat(),
                "sources": response_data.get("sources", [])
            }
        )
        
        # Update session context if provided
        updated_context = dict(session_context or {})
        if request.context:
            updated_context.update(request.context)
        updated_context["language"] = preferred_language
        await session_manager.set_context(session_id, updated_context)

        await analytics_store.record_chat_event(
            {
                "timestamp": datetime.now().isoformat(),
                "session_id": session_id,
                "client_id": client_id,
                "message": sanitized_message,
                "language": preferred_language,
                "intent": response_data.get("intent", "general"),
                "confidence": response_data.get("confidence"),
                "handoff": response_data.get("handoff"),
                "query_type": response_data.get("query_type"),
                "use_tools": bool(request.use_tools),
            }
        )
        
        return ChatResponse(
            response=response_data["answer"],
            session_id=session_id,
            sources=response_data.get("sources"),
            tool_results=response_data.get("tool_results"),
            confidence=response_data.get("confidence"),
            handoff=response_data.get("handoff"),
            suggested_actions=response_data.get("suggested_actions"),
            language=preferred_language,
            intent=response_data.get("intent"),
            timestamp=response_data.get("timestamp", datetime.now().isoformat()),
            query_type=response_data.get("query_type", "rag")
        )
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Chat query error: {str(e)}")
        raise HTTPException(status_code=500, detail="Internal server error")


@router.post("/tools/execute", response_model=ToolResponse)
async def execute_tool_endpoint(
    request_context: Request,
    request: ToolRequest,
    background_tasks: BackgroundTasks,
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(security)
):
    """Execute LangChain tools"""
    try:
        # Rate limiting check
        client_id = _get_client_id(request_context)
        if not rate_limiter.is_allowed(client_id):
            raise HTTPException(status_code=429, detail="Rate limit exceeded")
        
        # Verify/create session
        session_id = await verify_session(request.session_id)
        
        # Execute the tool
        result = await execute_tool(request.tool_name, **request.parameters)
        
        # Log tool usage
        await session_manager.add_message(
            session_id,
            {
                "role": "tool",
                "tool_name": request.tool_name,
                "parameters": request.parameters,
                "result": result,
                "timestamp": datetime.now().isoformat()
            }
        )
        
        return ToolResponse(
            result=result,
            session_id=session_id,
            timestamp=datetime.now().isoformat()
        )
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Tool execution error: {str(e)}")
        raise HTTPException(status_code=500, detail="Tool execution failed")


@router.get("/tools")
async def list_tools():
    """List available tools"""
    try:
        tools = get_available_tools()
        tool_list = []
        
        for tool in tools:
            schema = {}
            if tool.args_schema:
                schema = (
                    tool.args_schema.model_json_schema()
                    if hasattr(tool.args_schema, "model_json_schema")
                    else tool.args_schema.schema()
                )

            tool_list.append({
                "name": tool.name,
                "description": tool.description,
                "args_schema": schema
            })
        
        return {"tools": tool_list, "count": len(tool_list)}
        
    except Exception as e:
        logger.error(f"List tools error: {str(e)}")
        raise HTTPException(status_code=500, detail="Failed to list tools")


@router.post("/search")
async def search_knowledge_base(request: SearchRequest):
    """Search the indexed knowledge base without generating a full answer."""
    try:
        sanitized_query = input_validator.sanitize_input(request.query, max_length=500)
        results = await rag_engine.search(sanitized_query, limit=request.limit)
        return {"results": results, "count": len(results)}
    except Exception as e:
        logger.error(f"Search error: {str(e)}")
        raise HTTPException(status_code=500, detail="Search failed")


@router.post("/feedback")
async def submit_feedback(request: FeedbackRequest):
    """Capture user feedback for model and content improvement."""
    try:
        await analytics_store.record_feedback(
            {
                "timestamp": datetime.now().isoformat(),
                "session_id": request.session_id,
                "message_content": request.message_content,
                "helpful": request.helpful,
                "comment": request.comment,
                "intent": request.intent,
            }
        )
        return {"status": "received"}
    except Exception as e:
        logger.error(f"Feedback error: {str(e)}")
        raise HTTPException(status_code=500, detail="Failed to record feedback")


@router.get("/session/{session_id}")
async def get_session(session_id: str):
    """Get session information"""
    try:
        session = await session_manager.get_session(session_id)
        if not session:
            raise HTTPException(status_code=404, detail="Session not found")
        
        # Remove sensitive information
        safe_session = {
            "session_id": session["session_id"],
            "created_at": session["created_at"],
            "last_activity": session["last_activity"],
            "message_count": len(session.get("conversation_history", [])),
            "context": session.get("context", {})
        }
        
        return safe_session
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Get session error: {str(e)}")
        raise HTTPException(status_code=500, detail="Failed to get session")


@router.get("/session/{session_id}/history")
async def get_session_history(
    session_id: str,
    limit: int = 10,
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(security)
):
    """Get conversation history"""
    try:
        session = await session_manager.get_session(session_id)
        if not session:
            raise HTTPException(status_code=404, detail="Session not found")
        
        history = await session_manager.get_conversation_history(session_id, limit)
        
        return {
            "session_id": session_id,
            "history": history,
            "total_messages": len(session.get("conversation_history", []))
        }
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Get history error: {str(e)}")
        raise HTTPException(status_code=500, detail="Failed to get history")


@router.delete("/session/{session_id}")
async def delete_session(session_id: str):
    """Delete session"""
    try:
        success = await session_manager.delete_session(session_id)
        if not success:
            raise HTTPException(status_code=404, detail="Session not found")
        
        return {"message": "Session deleted successfully"}
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Delete session error: {str(e)}")
        raise HTTPException(status_code=500, detail="Failed to delete session")


@router.get("/health")
async def health_check():
    """Health check endpoint"""
    try:
        rag_health = await rag_engine.health_check()
        session_stats = await session_manager.get_session_stats()
        
        return {
            "status": "healthy",
            "timestamp": datetime.now().isoformat(),
            "rag_engine": rag_health,
            "session_manager": session_stats,
            "version": settings.VERSION
        }
        
    except Exception as e:
        logger.error(f"Health check error: {str(e)}")
        return {
            "status": "unhealthy",
            "error": str(e),
            "timestamp": datetime.now().isoformat()
        }


async def _process_with_tools(message: str, session_id: str, preferred_language: str) -> Dict[str, Any]:
    """Process message using LangChain tools"""
    try:
        # Simple keyword-based tool selection
        message_lower = message.lower()
        
        if "verify" in message_lower and "student" in message_lower:
            return {
                "answer": "Student verification is not yet connected to the live institutional system in this deployment. Please use the official portal or contact the relevant office for verified personal records.",
                "sources": [],
                "tool_results": None,
                "query_type": "verification_required",
                "intent": "student_verification",
                "confidence": {"label": "low", "score": 0.2},
                "suggested_actions": [
                    {"label": "Open Student Portal", "type": "link", "url": "http://elearning.kwekwepoly.ac.zw/"}
                ],
                "handoff": {
                    "office": "ICT Unit",
                    "message": "Use the official portal or ICT support for verified student access.",
                    "contact": {"phone": "+263 711 806 837", "email": "infor@kwekwepoly.ac.zw"},
                    "links": [{"label": "Student Portal", "url": "http://elearning.kwekwepoly.ac.zw/"}],
                },
            }
        
        elif "balance" in message_lower or ("fee" in message_lower and any(token in message_lower for token in ["my", "account", "owed"])):
            return {
                "answer": "Verified fee balances are not yet connected to a live student account system here. For a personal balance, please use the official student channels or contact the Accounts Office.",
                "sources": [],
                "tool_results": None,
                "query_type": "verification_required",
                "intent": "fees",
                "confidence": {"label": "low", "score": 0.2},
                "suggested_actions": [
                    {"label": "Payment Methods", "type": "prompt", "prompt": "What payment methods are available for fees?"}
                ],
                "handoff": {
                    "office": "Accounts Office",
                    "message": "For verified balances or receipting issues, contact Accounts.",
                    "contact": {"phone": "+263 8612 122991", "email": "infor@kwekwepoly.ac.zw"},
                    "links": [{"label": "Official Website", "url": "https://www.kwekwepoly.ac.zw/"}],
                },
            }
        
        elif ("result" in message_lower or "exam" in message_lower) and any(token in message_lower for token in ["my", "account", "student id", "status"]):
            return {
                "answer": "Personal examination results should only be accessed through verified institutional channels. Please contact the Examinations Office or use the official student systems.",
                "sources": [],
                "tool_results": None,
                "query_type": "verification_required",
                "intent": "results",
                "confidence": {"label": "low", "score": 0.2},
                "suggested_actions": [
                    {"label": "HEXCO Guidance", "type": "prompt", "prompt": "What should students know about HEXCO results?"}
                ],
                "handoff": {
                    "office": "Examinations Office",
                    "message": "For official results or collection guidance, contact Examinations.",
                    "contact": {"phone": "+263 8612 122991", "email": "infor@kwekwepoly.ac.zw"},
                    "links": [{"label": "Official Website", "url": "https://www.kwekwepoly.ac.zw/"}],
                },
            }
        
        elif "payment" in message_lower or "pay" in message_lower or "fee" in message_lower:
            result = await execute_tool("payment_methods")
            
            if result.get("status") == "success":
                methods = [method["name"] for method in result["payment_methods"]]
                answer = f"Available payment methods: {', '.join(methods)}. Bank accounts available at ZB Bank and CBZ Bank."
            else:
                answer = "Payment information temporarily unavailable."
            
            return {
                "answer": answer,
                "sources": [],
                "tool_results": result,
                "query_type": "tool",
                "intent": "payments",
                "confidence": {"label": "high", "score": 0.9},
                "suggested_actions": [],
            }
        
        elif "ict" in message_lower or "support" in message_lower or "portal" in message_lower:
            result = await execute_tool("ict_support")
            
            if result.get("status") == "success":
                ict_info = result["ict_unit"]
                answer = f"ICT Unit is managed by {ict_info['head']}. Contact: {ict_info['contact']['email']} or {ict_info['contact']['phone']}. Services include: {', '.join(ict_info['services'][:3])}..."
            else:
                answer = "ICT support information temporarily unavailable."
            
            return {
                "answer": answer,
                "sources": [],
                "tool_results": result,
                "query_type": "tool",
                "intent": "ict_support",
                "confidence": {"label": "high", "score": 0.9},
                "suggested_actions": [],
            }
        
        # Fallback to RAG if no tool matches
        return await rag_engine.query(message, session_id, preferred_language=preferred_language)
        
    except Exception as e:
        logger.error(f"Tool processing error: {str(e)}")
        return {
            "answer": "I'm having trouble processing your request. Please try again or contact support.",
            "sources": [],
            "query_type": "error",
            "intent": "error",
            "confidence": {"label": "low", "score": 0.0},
            "suggested_actions": [],
        }
