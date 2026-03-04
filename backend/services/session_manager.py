import json
import asyncio
from typing import Dict, Any, Optional, List
from datetime import datetime, timedelta
import logging
import redis.asyncio as redis

from backend.config.settings import settings

logger = logging.getLogger(__name__)


class SessionManager:
    def __init__(self):
        self.redis_client = None
        self.session_ttl = 3600  # 1 hour
        self._initialize_redis()
    
    async def _initialize_redis(self):
        """Initialize Redis connection"""
        try:
            self.redis_client = redis.from_url(
                settings.REDIS_URL,
                encoding="utf-8",
                decode_responses=True
            )
            # Test connection
            await self.redis_client.ping()
            logger.info("Redis connection established")
        except Exception as e:
            logger.warning(f"Redis connection failed: {str(e)}. Using in-memory storage.")
            self.redis_client = None
            self.memory_store = {}
    
    async def create_session(self, session_id: str, user_data: Dict[str, Any]) -> bool:
        """Create a new session"""
        try:
            session_data = {
                "session_id": session_id,
                "created_at": datetime.now().isoformat(),
                "last_activity": datetime.now().isoformat(),
                "user_data": user_data,
                "conversation_history": [],
                "context": {}
            }
            
            if self.redis_client:
                await self.redis_client.setex(
                    f"session:{session_id}",
                    self.session_ttl,
                    json.dumps(session_data)
                )
            else:
                self.memory_store[session_id] = session_data
            
            logger.info(f"Created session: {session_id}")
            return True
            
        except Exception as e:
            logger.error(f"Error creating session: {str(e)}")
            return False
    
    async def get_session(self, session_id: str) -> Optional[Dict[str, Any]]:
        """Get session data"""
        try:
            if self.redis_client:
                session_data = await self.redis_client.get(f"session:{session_id}")
                if session_data:
                    return json.loads(session_data)
            else:
                return self.memory_store.get(session_id)
            
            return None
            
        except Exception as e:
            logger.error(f"Error getting session: {str(e)}")
            return None
    
    async def update_session(self, session_id: str, updates: Dict[str, Any]) -> bool:
        """Update session data"""
        try:
            session = await self.get_session(session_id)
            if not session:
                return False
            
            # Update session with new data
            session.update(updates)
            session["last_activity"] = datetime.now().isoformat()
            
            if self.redis_client:
                await self.redis_client.setex(
                    f"session:{session_id}",
                    self.session_ttl,
                    json.dumps(session)
                )
            else:
                self.memory_store[session_id] = session
            
            return True
            
        except Exception as e:
            logger.error(f"Error updating session: {str(e)}")
            return False
    
    async def add_message(self, session_id: str, message: Dict[str, Any]) -> bool:
        """Add message to conversation history"""
        try:
            session = await self.get_session(session_id)
            if not session:
                return False
            
            # Add timestamp to message
            message["timestamp"] = datetime.now().isoformat()
            
            # Add to conversation history
            session["conversation_history"].append(message)
            
            # Keep only last 20 messages to prevent memory issues
            if len(session["conversation_history"]) > 20:
                session["conversation_history"] = session["conversation_history"][-20:]
            
            return await self.update_session(session_id, {
                "conversation_history": session["conversation_history"]
            })
            
        except Exception as e:
            logger.error(f"Error adding message: {str(e)}")
            return False
    
    async def get_conversation_history(self, session_id: str, limit: int = 10) -> List[Dict[str, Any]]:
        """Get conversation history"""
        try:
            session = await self.get_session(session_id)
            if not session:
                return []
            
            history = session.get("conversation_history", [])
            return history[-limit:] if limit > 0 else history
            
        except Exception as e:
            logger.error(f"Error getting conversation history: {str(e)}")
            return []
    
    async def set_context(self, session_id: str, context: Dict[str, Any]) -> bool:
        """Set session context"""
        return await self.update_session(session_id, {"context": context})
    
    async def get_context(self, session_id: str) -> Dict[str, Any]:
        """Get session context"""
        try:
            session = await self.get_session(session_id)
            if not session:
                return {}
            
            return session.get("context", {})
            
        except Exception as e:
            logger.error(f"Error getting context: {str(e)}")
            return {}
    
    async def delete_session(self, session_id: str) -> bool:
        """Delete session"""
        try:
            if self.redis_client:
                await self.redis_client.delete(f"session:{session_id}")
            else:
                if session_id in self.memory_store:
                    del self.memory_store[session_id]
            
            logger.info(f"Deleted session: {session_id}")
            return True
            
        except Exception as e:
            logger.error(f"Error deleting session: {str(e)}")
            return False
    
    async def cleanup_expired_sessions(self):
        """Clean up expired sessions (for in-memory storage)"""
        if self.redis_client:
            return  # Redis handles TTL automatically
        
        try:
            current_time = datetime.now()
            expired_sessions = []
            
            for session_id, session_data in self.memory_store.items():
                last_activity = datetime.fromisoformat(session_data["last_activity"])
                if current_time - last_activity > timedelta(seconds=self.session_ttl):
                    expired_sessions.append(session_id)
            
            for session_id in expired_sessions:
                del self.memory_store[session_id]
            
            if expired_sessions:
                logger.info(f"Cleaned up {len(expired_sessions)} expired sessions")
                
        except Exception as e:
            logger.error(f"Error cleaning up sessions: {str(e)}")
    
    async def get_session_stats(self) -> Dict[str, Any]:
        """Get session statistics"""
        try:
            if self.redis_client:
                # Get all session keys
                keys = await self.redis_client.keys("session:*")
                active_sessions = len(keys)
            else:
                active_sessions = len(self.memory_store)
            
            return {
                "active_sessions": active_sessions,
                "session_ttl": self.session_ttl,
                "storage_type": "redis" if self.redis_client else "memory",
                "timestamp": datetime.now().isoformat()
            }
            
        except Exception as e:
            logger.error(f"Error getting session stats: {str(e)}")
            return {"error": str(e)}


# Global session manager instance
session_manager = SessionManager()
