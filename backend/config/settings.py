from pydantic_settings import BaseSettings
from typing import Optional, List
import os


class Settings(BaseSettings):
    # API Configuration
    API_V1_STR: str = "/api/v1"
    PROJECT_NAME: str = "Kwekwe Polytechnic Chatbot"
    VERSION: str = "1.0.0"
    
    # Server Configuration
    HOST: str = "0.0.0.0"
    PORT: int = 8000
    DEBUG: bool = False
    ALLOWED_ORIGINS: str = "*"
    
    # Security
    SECRET_KEY: str = "your-secret-key-change-in-production"
    ALGORITHM: str = "HS256"
    ACCESS_TOKEN_EXPIRE_MINUTES: int = 30
    ADMIN_API_KEY: Optional[str] = None
    
    # LLM Configuration
    OPENAI_API_KEY: Optional[str] = None
    OPENAI_MODEL: str = "gpt-3.5-turbo"
    OPENAI_BASE_URL: Optional[str] = None  # For local LLM alternatives
    
    # Vector Database
    CHROMA_DB_PATH: str = "./data/vector_store"
    EMBEDDING_MODEL: str = "all-MiniLM-L6-v2"
    UPLOAD_DIR: str = "./data/uploads"
    FEEDBACK_FILE: str = "./data/feedback/feedback.jsonl"
    ANALYTICS_FILE: str = "./data/analytics/chat_events.jsonl"
    WEBSITE_SYNC_SEEDS: str = (
        "https://www.kwekwepoly.ac.zw/,"
        "https://www.kwekwepoly.ac.zw/portal.php,"
        "https://www.kwekwepoly.ac.zw/hostel.php,"
        "https://www.kwekwepoly.ac.zw/commerce.php,"
        "https://apply.kwekwepoly.ac.zw/"
    )
    MAX_WEBSITE_SYNC_PAGES: int = 25
    
    # WhatsApp Configuration
    WHATSAPP_PHONE_NUMBER_ID: Optional[str] = None
    WHATSAPP_ACCESS_TOKEN: Optional[str] = None
    WHATSAPP_WEBHOOK_VERIFY_TOKEN: Optional[str] = None
    WHATSAPP_API_VERSION: str = "v18.0"
    
    # Redis for Session Management
    REDIS_URL: str = "redis://localhost:6379"
    
    # PHP Integration
    PHP_API_BASE_URL: str = "http://localhost/php-api"
    PHP_API_KEY: Optional[str] = None
    
    # File Upload
    MAX_FILE_SIZE: int = 10 * 1024 * 1024  # 10MB
    ALLOWED_FILE_TYPES: list = [".pdf", ".txt", ".docx", ".md"]
    
    # Rate Limiting
    RATE_LIMIT_REQUESTS: int = 100
    RATE_LIMIT_WINDOW: int = 3600  # 1 hour
    
    # Logging
    LOG_LEVEL: str = "INFO"
    LOG_FILE: str = "./logs/app.log"
    
    class Config:
        env_file = ".env"
        case_sensitive = True


settings = Settings()
