import time
import hashlib
import secrets
from typing import Dict, Optional
import logging
from datetime import datetime, timedelta

logger = logging.getLogger(__name__)


class RateLimiter:
    def __init__(self, requests: int = 100, window: int = 3600):
        self.requests = requests
        self.window = window
        self.clients: Dict[str, Dict[str, int]] = {}
    
    def is_allowed(self, client_id: str) -> bool:
        """Check if client is allowed to make a request"""
        try:
            current_time = int(time.time())
            
            if client_id not in self.clients:
                self.clients[client_id] = {
                    "count": 1,
                    "window_start": current_time
                }
                return True
            
            client_data = self.clients[client_id]
            
            # Reset window if expired
            if current_time - client_data["window_start"] > self.window:
                client_data["count"] = 1
                client_data["window_start"] = current_time
                return True
            
            # Check if under limit
            if client_data["count"] < self.requests:
                client_data["count"] += 1
                return True
            
            return False
            
        except Exception as e:
            logger.error(f"Rate limiter error: {str(e)}")
            return True  # Allow on error


class InputValidator:
    @staticmethod
    def sanitize_input(text: str, max_length: int = 1000) -> str:
        """Sanitize user input"""
        if not text:
            return ""
        
        # Remove potentially harmful characters
        dangerous_chars = ["<", ">", "&", "\"", "'", "/"]
        for char in dangerous_chars:
            text = text.replace(char, "")
        
        # Limit length
        if len(text) > max_length:
            text = text[:max_length]
        
        return text.strip()
    
    @staticmethod
    def validate_student_id(student_id: str) -> bool:
        """Validate student ID format"""
        if not student_id:
            return False
        
        # Example validation: KP followed by 6 digits
        import re
        pattern = r'^KP\d{6}$'
        return bool(re.match(pattern, student_id))
    
    @staticmethod
    def validate_date(date_str: str) -> bool:
        """Validate date format YYYY-MM-DD"""
        if not date_str:
            return False
        
        try:
            datetime.strptime(date_str, "%Y-%m-%d")
            return True
        except ValueError:
            return False
    
    @staticmethod
    def validate_phone_number(phone: str) -> bool:
        """Validate phone number"""
        if not phone:
            return False
        
        # Remove non-digit characters
        digits_only = ''.join(filter(str.isdigit, phone))
        
        # Check if it's a valid Zimbabwe number (simplified)
        return len(digits_only) >= 9 and len(digits_only) <= 15


class SecurityUtils:
    @staticmethod
    def generate_session_token() -> str:
        """Generate secure session token"""
        return secrets.token_urlsafe(32)
    
    @staticmethod
    def hash_password(password: str) -> str:
        """Hash password using SHA-256"""
        return hashlib.sha256(password.encode()).hexdigest()
    
    @staticmethod
    def verify_password(password: str, hashed: str) -> bool:
        """Verify password against hash"""
        return SecurityUtils.hash_password(password) == hashed
    
    @staticmethod
    def generate_api_key() -> str:
        """Generate API key"""
        return f"kp_{secrets.token_urlsafe(24)}"
    
    @staticmethod
    def mask_sensitive_data(data: str, mask_char: str = "*") -> str:
        """Mask sensitive data for logging"""
        if not data or len(data) < 4:
            return mask_char * 4
        
        # Show first 2 and last 2 characters
        if len(data) > 4:
            return data[:2] + mask_char * (len(data) - 4) + data[-2:]
        else:
            return data[:2] + mask_char * (len(data) - 2)


class AuditLogger:
    def __init__(self):
        self.logger = logging.getLogger("audit")
    
    def log_access(self, user_id: str, action: str, resource: str, ip_address: str = None):
        """Log access attempt"""
        self.logger.info(
            f"ACCESS: user={user_id}, action={action}, resource={resource}, "
            f"ip={ip_address}, timestamp={datetime.now().isoformat()}"
        )
    
    def log_security_event(self, event_type: str, details: str, severity: str = "INFO"):
        """Log security event"""
        self.logger.log(
            getattr(logging, severity.upper()),
            f"SECURITY: type={event_type}, details={details}, "
            f"timestamp={datetime.now().isoformat()}"
        )
    
    def log_data_access(self, user_id: str, data_type: str, record_id: str = None):
        """Log data access"""
        self.logger.info(
            f"DATA_ACCESS: user={user_id}, type={data_type}, "
            f"record={record_id}, timestamp={datetime.now().isoformat()}"
        )


# Global instances
rate_limiter = RateLimiter()
input_validator = InputValidator()
security_utils = SecurityUtils()
audit_logger = AuditLogger()
