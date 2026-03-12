import uvicorn
from fastapi import FastAPI, HTTPException
from fastapi.responses import FileResponse, JSONResponse, RedirectResponse
from fastapi.middleware.cors import CORSMiddleware
from fastapi.middleware.gzip import GZipMiddleware
from contextlib import asynccontextmanager
from datetime import datetime, timezone
from pathlib import Path

from backend.config.settings import settings
from backend.utils.logger import logger
from backend.api.chat import router as chat_router
from backend.api.admin import router as admin_router
from backend.api.webhooks.whatsapp import router as whatsapp_router
from backend.core.document_ingestion import document_ingestion
from backend.core.rag_engine import rag_engine
from backend.services.session_manager import session_manager

APP_DIR = Path(__file__).resolve().parent
FRONTEND_DIR = APP_DIR / "frontend"
ASSET_PATHS = {
    "index.html": FRONTEND_DIR / "index.html",
    "admin.html": FRONTEND_DIR / "admin.html",
    "embed.js": FRONTEND_DIR / "embed.js",
    "logo.png": FRONTEND_DIR / "logo.png",
    "kwekwe-chat-widget.js": APP_DIR / "kwekwe-chat-widget.js",
    "kwekwe-chat-widget.css": APP_DIR / "kwekwe-chat-widget.css",
    "kwekwe-chat-widget-professional.css": APP_DIR / "kwekwe-chat-widget-professional.css",
    "kwekwe-demo.html": APP_DIR / "kwekwe-demo.html",
}


def _serve_repo_asset(asset_name: str) -> FileResponse:
    """Serve a known static asset directly from the repository."""
    file_path = ASSET_PATHS.get(asset_name)
    if not file_path or not file_path.exists():
        raise HTTPException(status_code=404, detail=f"Repository asset not found: {asset_name}")
    return FileResponse(file_path)


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Application lifespan manager"""
    # Startup
    logger.info("Starting Kwekwe Polytechnic Chatbot...")
    
    try:
        logger.info("Initializing session storage...")
        await session_manager.initialize()

        logger.info("Initializing document ingestion...")
        success = await document_ingestion.ensure_knowledge_base()
        if success:
            logger.info("Knowledge base is ready")
        else:
            logger.warning("Failed to seed the knowledge base")
        
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
allowed_origins = [origin.strip() for origin in settings.ALLOWED_ORIGINS.split(",") if origin.strip()]
app.add_middleware(
    CORSMiddleware,
    allow_origins=allowed_origins or ["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

app.add_middleware(GZipMiddleware, minimum_size=1000)

# Include routers
app.include_router(chat_router, prefix=settings.API_V1_STR)
app.include_router(admin_router, prefix=settings.API_V1_STR)
app.include_router(whatsapp_router, prefix=settings.API_V1_STR)


@app.get("/")
async def root():
    """Root endpoint"""
    return {
        "message": "Kwekwe Polytechnic Chatbot API",
        "version": settings.VERSION,
        "status": "running",
        "endpoints": {
            "chat": "/api/v1/chat",
            "search": "/api/v1/chat/search",
            "feedback": "/api/v1/chat/feedback",
            "admin": "/api/v1/admin",
            "whatsapp_webhook": "/api/v1/webhooks/whatsapp",
            "health": "/api/v1/chat/health"
        }
    }


@app.get("/index.html", include_in_schema=False)
async def landing_page():
    """Serve the public landing page from the backend host."""
    return _serve_repo_asset("index.html")


@app.get("/admin.html", include_in_schema=False)
async def admin_page():
    """Serve the admin panel from the backend host."""
    return _serve_repo_asset("admin.html")


@app.get("/admin", include_in_schema=False)
async def admin_redirect():
    """Redirect a cleaner admin URL to the static admin page."""
    return RedirectResponse(url="/admin.html", status_code=307)


@app.get("/logo.png", include_in_schema=False)
async def logo_file():
    """Serve the shared logo asset used by the frontend pages."""
    return _serve_repo_asset("logo.png")


@app.get("/embed.js", include_in_schema=False)
async def embed_script():
    """Serve the one-line loader used to embed the floating widget."""
    return _serve_repo_asset("embed.js")


@app.get("/kwekwe-chat-widget.js", include_in_schema=False)
async def widget_script():
    """Serve the floating widget JavaScript bundle."""
    return _serve_repo_asset("kwekwe-chat-widget.js")


@app.get("/kwekwe-chat-widget.css", include_in_schema=False)
async def widget_styles():
    """Serve the floating widget stylesheet."""
    return _serve_repo_asset("kwekwe-chat-widget.css")


@app.get("/kwekwe-chat-widget-professional.css", include_in_schema=False)
async def widget_compat_styles():
    """Serve compatibility overrides for older widget integrations."""
    return _serve_repo_asset("kwekwe-chat-widget-professional.css")


@app.get("/kwekwe-demo.html", include_in_schema=False)
async def widget_demo_page():
    """Serve a local demo page for the floating website widget."""
    return _serve_repo_asset("kwekwe-demo.html")


@app.get("/health")
async def health_check():
    """Health check endpoint"""
    try:
        rag_health = await rag_engine.health_check()
        
        return {
            "status": "healthy",
            "timestamp": datetime.now(timezone.utc).isoformat(),
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
    return JSONResponse(status_code=500, content={"detail": "Internal server error"})


if __name__ == "__main__":
    uvicorn.run(
        "main:app",
        host=settings.HOST,
        port=settings.PORT,
        reload=settings.DEBUG,
        log_level=settings.LOG_LEVEL.lower()
    )
