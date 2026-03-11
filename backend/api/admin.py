from pathlib import Path
from typing import List, Optional
from uuid import uuid4

from fastapi import APIRouter, File, Header, HTTPException, UploadFile
from pydantic import BaseModel, Field

from backend.config.settings import settings
from backend.core.document_ingestion import document_ingestion
from backend.core.site_sync import website_sync_service
from backend.core.vector_store import vector_store
from backend.services.analytics_store import analytics_store

router = APIRouter(prefix="/admin", tags=["admin"])


class LocalIngestionRequest(BaseModel):
    directory_path: str = Field(..., description="Directory path to ingest")


class WebsiteSyncRequest(BaseModel):
    seeds: Optional[List[str]] = Field(default=None, description="Seed URLs")
    max_pages: int = Field(default=10, ge=1, le=100)
    max_depth: int = Field(default=1, ge=0, le=3)


def _authorize_admin(admin_key: Optional[str]) -> None:
    if settings.ADMIN_API_KEY and admin_key != settings.ADMIN_API_KEY:
        raise HTTPException(status_code=401, detail="Invalid admin key")


@router.get("/overview")
async def get_admin_overview(x_admin_key: Optional[str] = Header(default=None)):
    _authorize_admin(x_admin_key)
    stats = vector_store.get_collection_stats()
    analytics = await analytics_store.get_summary()
    return {
        "vector_store": stats,
        "analytics": analytics,
        "upload_dir": settings.UPLOAD_DIR,
        "website_sync_seeds": [seed.strip() for seed in settings.WEBSITE_SYNC_SEEDS.split(",") if seed.strip()],
    }


@router.post("/ingest/local")
async def ingest_local_directory(
    request: LocalIngestionRequest,
    x_admin_key: Optional[str] = Header(default=None),
):
    _authorize_admin(x_admin_key)
    success = await document_ingestion.ingest_directory(request.directory_path)
    if not success:
        raise HTTPException(status_code=400, detail="Directory ingestion failed")
    return {"status": "success", "directory_path": request.directory_path}


@router.post("/ingest/upload")
async def upload_documents(
    files: List[UploadFile] = File(...),
    x_admin_key: Optional[str] = Header(default=None),
):
    _authorize_admin(x_admin_key)
    upload_dir = Path(settings.UPLOAD_DIR)
    upload_dir.mkdir(parents=True, exist_ok=True)

    uploaded_files = []
    for upload in files:
        suffix = Path(upload.filename or "").suffix.lower()
        if suffix not in settings.ALLOWED_FILE_TYPES:
            raise HTTPException(status_code=400, detail=f"Unsupported file type: {upload.filename}")

        stored_name = f"{uuid4().hex}_{Path(upload.filename).name}"
        stored_path = upload_dir / stored_name
        content = await upload.read()
        stored_path.write_bytes(content)
        success = await document_ingestion.ingest_file(str(stored_path))
        if success:
            uploaded_files.append(str(stored_path))

    return {"status": "success", "uploaded_files": uploaded_files}


@router.post("/sync/website")
async def sync_official_website(
    request: WebsiteSyncRequest,
    x_admin_key: Optional[str] = Header(default=None),
):
    _authorize_admin(x_admin_key)
    result = await website_sync_service.sync(
        seeds=request.seeds,
        max_pages=request.max_pages,
        max_depth=request.max_depth,
    )
    return result


@router.get("/analytics/summary")
async def get_analytics_summary(x_admin_key: Optional[str] = Header(default=None)):
    _authorize_admin(x_admin_key)
    return await analytics_store.get_summary()


@router.get("/feedback")
async def get_feedback(limit: int = 50, x_admin_key: Optional[str] = Header(default=None)):
    _authorize_admin(x_admin_key)
    return {"feedback": await analytics_store.list_feedback(limit=limit)}
