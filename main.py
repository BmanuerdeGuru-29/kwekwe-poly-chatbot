import asyncio
import uvicorn
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.middleware.gzip import GZipMiddleware
from contextlib import asynccontextmanager

from backend.config.settings import settings
from backend.utils.logger import logger
from backend.api.chat import router as chat_router
from backend.api.webhooks.whatsapp import router as whatsapp_router
from backend.core.document_ingestion import document_ingestion
from backend.core.rag_engine import rag_engine


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Application lifespan manager"""
    # Startup
    logger.info("Starting Kwekwe Polytechnic Chatbot...")
    
    try:
        # Initialize sample documents and vector store
        logger.info("Initializing document ingestion...")
        success = await document_ingestion.create_sample_documents()
        if success:
            logger.info("Sample documents created and ingested successfully")
        else:
            logger.warning("Failed to create sample documents")
        
        # Test RAG engine
        logger.info("Testing RAG engine...")
        health = await rag_engine.health_check()
        logger.info(f"RAG engine status: {health.get('status', 'unknown')}")
        
        logger.info("Application startup completed successfully")
        
    except Exception as e:
        logger.error(f"Startup error: {str(e)}")
    
    yield
    
    # Shutdown
    logger.info("Shutting down application...")


# Create FastAPI application
app = FastAPI(
    title=settings.PROJECT_NAME,
    version=settings.VERSION,
    description="Intelligent chatbot for Kwekwe Polytechnic using RAG",
    lifespan=lifespan
)

# Add middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # Configure appropriately for production
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.add_middleware(GZipMiddleware, minimum_size=1000)

# Include routers
app.include_router(chat_router)
app.include_router(whatsapp_router)


@app.get("/")
async def root():
    """Root endpoint"""
    return {
        "message": "Kwekwe Polytechnic Chatbot API",
        "version": settings.VERSION,
        "status": "running",
        "endpoints": {
            "chat": "/api/v1/chat",
            "whatsapp_webhook": "/api/v1/webhooks/whatsapp",
            "health": "/api/v1/chat/health"
        }
    }


@app.get("/health")
async def health_check():
    """Health check endpoint"""
    try:
        rag_health = await rag_engine.health_check()
        
        return {
            "status": "healthy",
            "timestamp": "2026-03-02T08:44:00Z",
            "version": settings.VERSION,
            "rag_engine": rag_health,
            "environment": "development" if settings.DEBUG else "production"
        }
        
    except Exception as e:
        logger.error(f"Health check failed: {str(e)}")
        raise HTTPException(status_code=503, detail="Service unavailable")


@app.exception_handler(Exception)
async def global_exception_handler(request, exc):
    """Global exception handler"""
    logger.error(f"Unhandled exception: {str(exc)}")
    return HTTPException(status_code=500, detail="Internal server error")


if __name__ == "__main__":
    uvicorn.run(
        "main:app",
        host=settings.HOST,
        port=settings.PORT,
        reload=settings.DEBUG,
        log_level=settings.LOG_LEVEL.lower()
    )
