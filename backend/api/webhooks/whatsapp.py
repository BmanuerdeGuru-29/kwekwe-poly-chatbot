from fastapi import APIRouter, Request, HTTPException, BackgroundTasks
from pydantic import BaseModel, Field
from typing import Dict, Any, Optional, List
import logging
import hashlib
import hmac
from datetime import datetime, timedelta

from backend.core.rag_engine import rag_engine
from backend.services.session_manager import session_manager
from backend.config.settings import settings

logger = logging.getLogger(__name__)
router = APIRouter(prefix="/webhooks/whatsapp", tags=["whatsapp"])


class WhatsAppMessage(BaseModel):
    from_number: str = Field(..., description="Sender phone number")
    message_id: str = Field(..., description="Message ID")
    message_type: str = Field(..., description="Message type")
    content: str = Field(..., description="Message content")
    timestamp: str = Field(..., description="Message timestamp")


class WhatsAppWebhookData(BaseModel):
    object: str
    entry: List[Dict[str, Any]]


@router.get("/verify")
async def verify_webhook(
    hub_mode: str,
    hub_challenge: str,
    hub_verify_token: str
):
    """Verify WhatsApp webhook"""
    try:
        if (hub_mode == "subscribe" and 
            hub_verify_token == settings.WHATSAPP_WEBHOOK_VERIFY_TOKEN):
            return int(hub_challenge)
        else:
            raise HTTPException(status_code=403, detail="Verification failed")
    except Exception as e:
        logger.error(f"Webhook verification error: {str(e)}")
        raise HTTPException(status_code=403, detail="Verification failed")


@router.post("/message")
async def receive_message(
    request: Request,
    background_tasks: BackgroundTasks
):
    """Receive WhatsApp message"""
    try:
        data = await request.json()
        
        # Extract message from webhook payload
        messages = _extract_messages(data)
        
        for message in messages:
            # Process message in background
            background_tasks.add_task(
                process_whatsapp_message,
                message
            )
        
        return {"status": "received"}
        
    except Exception as e:
        logger.error(f"WhatsApp webhook error: {str(e)}")
        return {"status": "error"}


def _extract_messages(data: Dict[str, Any]) -> List[WhatsAppMessage]:
    """Extract messages from webhook payload"""
    messages = []
    
    try:
        for entry in data.get("entry", []):
            for change in entry.get("changes", []):
                if "messages" in change.get("value", {}):
                    for msg_data in change["value"]["messages"]:
                        if msg_data.get("type") == "text":
                            message = WhatsAppMessage(
                                from_number=msg_data["from"],
                                message_id=msg_data["id"],
                                message_type=msg_data["type"],
                                content=msg_data["text"]["body"],
                                timestamp=msg_data["timestamp"]
                            )
                            messages.append(message)
    except Exception as e:
        logger.error(f"Message extraction error: {str(e)}")
    
    return messages


async def process_whatsapp_message(message: WhatsAppMessage):
    """Process incoming WhatsApp message"""
    try:
        # Create session based on phone number
        session_id = f"whatsapp_{message.from_number}"
        
        # Get or create session
        session = await session_manager.get_session(session_id)
        if not session:
            await session_manager.create_session(
                session_id,
                {
                    "platform": "whatsapp",
                    "phone_number": message.from_number,
                    "created_at": datetime.now().isoformat()
                }
            )
        
        # Add user message to history
        await session_manager.add_message(
            session_id,
            {
                "role": "user",
                "content": message.content,
                "timestamp": message.timestamp,
                "message_id": message.message_id
            }
        )
        
        # Get response from RAG engine
        response_data = await rag_engine.query(message.content, session_id)
        
        # Send response via WhatsApp
        await send_whatsapp_message(
            message.from_number,
            response_data["answer"]
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
        
    except Exception as e:
        logger.error(f"Message processing error: {str(e)}")
        # Send error message
        await send_whatsapp_message(
            message.from_number,
            "Sorry, I'm having trouble processing your request. Please try again later."
        )


async def send_whatsapp_message(to_number: str, message: str):
    """Send message via WhatsApp API"""
    try:
        if not settings.WHATSAPP_ACCESS_TOKEN or not settings.WHATSAPP_PHONE_NUMBER_ID:
            logger.warning("WhatsApp credentials not configured")
            return
        
        import httpx
        
        url = f"https://graph.facebook.com/{settings.WHATSAPP_API_VERSION}/{settings.WHATSAPP_PHONE_NUMBER_ID}/messages"
        
        headers = {
            "Authorization": f"Bearer {settings.WHATSAPP_ACCESS_TOKEN}",
            "Content-Type": "application/json"
        }
        
        payload = {
            "messaging_product": "whatsapp",
            "to": to_number,
            "type": "text",
            "text": {
                "body": message[:1600]  # WhatsApp message limit
            }
        }
        
        async with httpx.AsyncClient() as client:
            response = await client.post(url, json=payload, headers=headers)
            
            if response.status_code == 200:
                logger.info(f"Message sent to {to_number}")
            else:
                logger.error(f"Failed to send message: {response.text}")
                
    except Exception as e:
        logger.error(f"WhatsApp send error: {str(e)}")
