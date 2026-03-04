from fastapi import APIRouter, HTTPException, Depends, BackgroundTasks
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from pydantic import BaseModel, Field
from typing import Dict, Any, Optional, List
import logging
import uuid
from datetime import datetime

from backend.core.rag_engine import rag_engine
from backend.services.session_manager import session_manager
from backend.services.langchain_tools import execute_tool, get_available_tools
from backend.utils.security import rate_limiter
from backend.config.settings import settings

logger = logging.getLogger(__name__)
router = APIRouter(prefix="/chat", tags=["chat"])
security = HTTPBearer(auto_error=False)


class ChatRequest(BaseModel):
    message: str = Field(..., description="User message", min_length=1, max_length=1000)
    session_id: Optional[str] = Field(None, description="Session ID for conversation continuity")
    context: Optional[Dict[str, Any]] = Field(None, description="Additional context")
    use_tools: Optional[bool] = Field(False, description="Whether to use LangChain tools")


class ChatResponse(BaseModel):
    response: str
    session_id: str
    sources: Optional[List[Dict[str, Any]]] = None
    tool_results: Optional[Dict[str, Any]] = None
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
    request: ChatRequest,
    background_tasks: BackgroundTasks,
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(security)
):
    """Main chat endpoint"""
    try:
        # Rate limiting check
        client_ip = "client_ip"  # Would get from request in real implementation
        if not rate_limiter.is_allowed(client_ip):
            raise HTTPException(status_code=429, detail="Rate limit exceeded")
        
        # Verify/create session
        session_id = await verify_session(request.session_id)
        
        # Add user message to history
        await session_manager.add_message(
            session_id,
            {
                "role": "user",
                "content": request.message,
                "timestamp": datetime.now().isoformat()
            }
        )
        
        # Process the query
        if request.use_tools:
            # Try to use LangChain tools first
            response_data = await _process_with_tools(request.message, session_id)
        else:
            # Use RAG engine
            response_data = await rag_engine.query(request.message, session_id)
        
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
        if request.context:
            await session_manager.set_context(session_id, request.context)
        
        return ChatResponse(
            response=response_data["answer"],
            session_id=session_id,
            sources=response_data.get("sources"),
            timestamp=response_data.get("timestamp", datetime.now().isoformat()),
            query_type=response_data.get("query_type", "rag")
        )
        
    except Exception as e:
        logger.error(f"Chat query error: {str(e)}")
        raise HTTPException(status_code=500, detail="Internal server error")


@router.post("/tools/execute", response_model=ToolResponse)
async def execute_tool_endpoint(
    request: ToolRequest,
    background_tasks: BackgroundTasks,
    credentials: Optional[HTTPAuthorizationCredentials] = Depends(security)
):
    """Execute LangChain tools"""
    try:
        # Rate limiting check
        client_ip = "client_ip"
        if not rate_limiter.is_allowed(client_ip):
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
            tool_list.append({
                "name": tool.name,
                "description": tool.description,
                "args_schema": tool.args_schema.schema() if tool.args_schema else {}
            })
        
        return {"tools": tool_list, "count": len(tool_list)}
        
    except Exception as e:
        logger.error(f"List tools error: {str(e)}")
        raise HTTPException(status_code=500, detail="Failed to list tools")


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


async def _process_with_tools(message: str, session_id: str) -> Dict[str, Any]:
    """Process message using LangChain tools"""
    try:
        # Simple keyword-based tool selection
        message_lower = message.lower()
        
        if "verify" in message_lower and "student" in message_lower:
            # Extract student ID and DOB (simplified)
            student_id = "KP123456"  # Would extract from message
            date_of_birth = "2000-01-01"  # Would extract from message
            
            result = await execute_tool("student_verification", student_id=student_id, date_of_birth=date_of_birth)
            
            return {
                "answer": f"Student verification result: {result.get('status', 'Unknown')}",
                "sources": [],
                "tool_results": result,
                "query_type": "tool"
            }
        
        elif "fee" in message_lower or "balance" in message_lower:
            student_id = "KP123456"  # Would get from session context
            result = await execute_tool("fee_balance", student_id=student_id)
            
            if result.get("status") == "success":
                fee_info = result["fee_balance"]
                answer = f"Your fee balance is ${fee_info['usd']:.2f} USD or {fee_info['zig']:.2f} ZiG. Due date: {fee_info['due_date']}"
            else:
                answer = "Unable to retrieve fee balance. Please verify your student ID first."
            
            return {
                "answer": answer,
                "sources": [],
                "tool_results": result,
                "query_type": "tool"
            }
        
        elif "result" in message_lower or "exam" in message_lower:
            student_id = "KP123456"  # Would get from session context
            exam_type = "HEXCO" if "hexco" in message_lower else "SEMESTER"
            result = await execute_tool("exam_results", student_id=student_id, exam_type=exam_type)
            
            if result.get("status") == "success":
                answer = f"Your {exam_type} results are available. Overall grade: {result.get('overall_grade', 'N/A')}"
            else:
                answer = "Unable to retrieve exam results. Please verify your student ID first."
            
            return {
                "answer": answer,
                "sources": [],
                "tool_results": result,
                "query_type": "tool"
            }
        
        elif "payment" in message_lower or "pay" in message_lower:
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
                "query_type": "tool"
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
                "query_type": "tool"
            }
        
        # Fallback to RAG if no tool matches
        return await rag_engine.query(message, session_id)
        
    except Exception as e:
        logger.error(f"Tool processing error: {str(e)}")
        return {
            "answer": "I'm having trouble processing your request. Please try again or contact support.",
            "sources": [],
            "query_type": "error"
        }
