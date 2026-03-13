import asyncio
import uvicorn
import re
from fastapi import APIRouter, FastAPI, File, Header, HTTPException, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from fastapi.responses import FileResponse, RedirectResponse
from pydantic import BaseModel, Field
from typing import List, Dict, Any, Optional, Tuple
from datetime import datetime
from pathlib import Path

from backend.config.settings import settings
from backend.services.analytics_store import analytics_store
from backend.services.openai_key_manager import (
    clear_openai_api_key,
    get_openai_key_status,
    set_openai_api_key,
)

try:
    from openai import AsyncOpenAI
except Exception:
    AsyncOpenAI = None

try:
    from backend.api.admin import router as admin_router
    ADMIN_ROUTER_IMPORT_ERROR = None
except Exception as exc:
    admin_router = None
    ADMIN_ROUTER_IMPORT_ERROR = exc

# Simple mock data for demonstration
MOCK_KNOWLEDGE_BASE = {
    "engineering_requirements": """
    Kwekwe Polytechnic - Engineering Division Requirements
    
    Academic Programs:
    1. Automotive Engineering
    2. Electrical Engineering  
    3. Mechanical Engineering
    
    Entry Requirements:
    - Minimum 5 'O' Level passes including Mathematics, English, and Science
    - Mathematics and Science subjects must be at grade C or better
    - English must be at least grade C
    
    Department Heads:
    - Mr. Gunda - Engineering Division Head
    - Mr. Mutiza - Automotive Engineering
    - Mr. Sibanda - Electrical Engineering
    - Mr. Mundandi - Mechanical Engineering
    
    Duration: 3 years full-time
    Accreditation: HEXCO certified programs
    """,
    
    "commerce_requirements": """
    Kwekwe Polytechnic - Commerce Division Requirements
    
    Academic Programs:
    1. Commerce (Management)
    2. Commerce (Business)
    
    Entry Requirements:
    Commerce (Management):
    - Minimum 5 'O' Level passes
    - English Language at grade C or better
    - Any other 4 subjects at grade C or better
    
    Commerce (Business):
    - Minimum 5 'O' Level passes
    - Mathematics and English at grade C or better
    - Any other 3 subjects at grade C or better
    
    Department Heads:
    - Mr. T. Sambama - Commerce (Management)
    - Mr. A. Vuma - Commerce (Business)
    
    Duration: 2 years full-time
    Accreditation: HEXCO certified programs
    """,
    
    "fees_structure": """
    Kwekwe Polytechnic - 2026 Fee Structure
    
    Tuition Fees per Semester:
    
    Engineering Programs:
    - USD $450 per semester
    - ZiG 18,000 per semester
    
    Commerce Programs:
    - USD $350 per semester
    - ZiG 14,000 per semester
    
    Payment Methods:
    1. Paynow (Online payment gateway)
    2. Ecocash (Mobile money)
    3. OneMoney (Mobile money)
    4. Bank Transfer:
       - ZB Bank Account: 1234567890
       - CBZ Bank Account: 0987654321
    
    Registration Fee:
    - USD $50 (once-off)
    - ZiG 2,000 (once-off)
    
    Late Payment Penalty:
    - 10% of outstanding amount after registration deadline
    """
}

app = FastAPI(
    title="Kwekwe Polytechnic Chatbot",
    description="Simple demonstration version",
    version="1.0.0"
)

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
SUPPORTED_SIMPLE_INGEST_TYPES = {".txt", ".md"}
RUNTIME_KNOWLEDGE_BASE: Dict[str, Dict[str, Any]] = {}
OPENAI_CLIENT = None
SEARCH_STOPWORDS = {
    "a", "an", "and", "are", "at", "be", "by", "do", "for", "from", "how",
    "i", "in", "is", "it", "me", "my", "of", "on", "or", "please", "poly",
    "polytechnic", "kwekwe", "tell", "that", "the", "their", "there", "this",
    "to", "what", "when", "where", "which", "who", "why", "with", "you",
    "your",
}
MIN_RELEVANCE_SCORE = 0.58
GENERAL_KWEKWE_CONTEXT = """
Institution: Kwekwe Polytechnic in Zimbabwe.
Supported topics usually include programmes, admissions, fees, payments, student services, examinations, contacts, accommodation, and campus guidance.
Known contact channels: +263 8612 122991, 0786 658 480, 0711 806 837 (WhatsApp), infor@kwekwepoly.ac.zw, https://apply.kwekwepoly.ac.zw
Known programme areas: Engineering, Commerce, Applied Sciences, B-Tech, and Adult & Continuing Education (A.C.E).
""".strip()
OPENAI_SCOPE_PROMPT = """
You are the Kwekwe Polytechnic AI assistant.
You must stay focused on Kwekwe Polytechnic in Zimbabwe at all times.

Rules:
- Answer only in the context of Kwekwe Polytechnic.
- If a user asks something unrelated to Kwekwe Polytechnic, politely say you can only help with Kwekwe Polytechnic matters.
- If exact verified information is not available, say that clearly and give the safest next step for Kwekwe Polytechnic, such as contacting the relevant office or using the official channels provided.
- Do not invent precise facts such as fees, dates, deadlines, names, phone numbers, accommodation rules, or admissions policies unless they are present in the supplied context.
- Keep the answer concise, helpful, and institution-focused.
""".strip()


def get_openai_client():
    global OPENAI_CLIENT

    if OPENAI_CLIENT is None and settings.OPENAI_API_KEY and AsyncOpenAI is not None:
        client_kwargs = {"api_key": settings.OPENAI_API_KEY}
        if settings.OPENAI_BASE_URL:
            client_kwargs["base_url"] = settings.OPENAI_BASE_URL
        OPENAI_CLIENT = AsyncOpenAI(**client_kwargs)

    return OPENAI_CLIENT


def reset_simple_openai_client():
    global OPENAI_CLIENT
    OPENAI_CLIENT = None


def extract_query_terms(query: str) -> List[str]:
    tokens = re.findall(r"[a-z0-9']+", query.lower())
    seen = set()
    terms = []

    for token in tokens:
        if token in SEARCH_STOPWORDS:
            continue
        if len(token) < 3 and token not in {"it", "nd", "nc", "hnd"}:
            continue
        if token not in seen:
            seen.add(token)
            terms.append(token)

    return terms


def iter_knowledge_entries() -> List[Tuple[str, str, Dict[str, Any]]]:
    entries: List[Tuple[str, str, Dict[str, Any]]] = []

    for key, content in MOCK_KNOWLEDGE_BASE.items():
        entries.append(
            (
                key,
                content,
                {
                    "title": f"Kwekwe Polytechnic - {key.replace('_', ' ').title()}",
                    "category": key.split("_")[0],
                    "source_url": None,
                    "source_path": None,
                },
            )
        )

    for key, entry in RUNTIME_KNOWLEDGE_BASE.items():
        entries.append(
            (
                key,
                entry["content"],
                {
                    "title": entry["title"],
                    "category": entry["category"],
                    "source_url": entry.get("source_url"),
                    "source_path": entry.get("source_path"),
                },
            )
        )

    return entries


def serve_repo_asset(asset_name: str) -> FileResponse:
    file_path = ASSET_PATHS.get(asset_name)
    if not file_path or not file_path.exists():
        raise HTTPException(status_code=404, detail=f"Repository asset not found: {asset_name}")
    return FileResponse(file_path)


def get_admin_mode() -> str:
    return "full" if admin_router is not None else "fallback"


def resolve_repo_path(raw_path: str) -> Path:
    candidate = Path(raw_path).expanduser()
    candidates = [candidate]

    if not candidate.is_absolute():
        candidates.append(APP_DIR / candidate)
        candidates.append(Path.cwd() / candidate)

    for item in candidates:
        resolved = item.resolve()
        if resolved.exists():
            return resolved

    return candidates[0].resolve()


def require_admin_key(admin_key: Optional[str]) -> None:
    if settings.ADMIN_API_KEY and admin_key != settings.ADMIN_API_KEY:
        raise HTTPException(status_code=401, detail="Invalid admin key")


def categorize_document_name(filename: str) -> str:
    lowered = filename.lower()
    if any(keyword in lowered for keyword in ["engineering", "automotive", "electrical", "mechanical", "civil"]):
        return "engineering"
    if any(keyword in lowered for keyword in ["commerce", "business", "management", "accountancy", "banking"]):
        return "commerce"
    if any(keyword in lowered for keyword in ["applied", "science", "laboratory", "metallurgical", "it"]):
        return "applied_sciences"
    if any(keyword in lowered for keyword in ["fee", "payment", "cost", "admission", "intake"]):
        return "administration"
    return "general"


def normalize_text_for_demo(content: str, suffix: str) -> str:
    cleaned = content
    if suffix == ".md":
        cleaned = re.sub(r"[#*_>`\-]+", " ", cleaned)
    return " ".join(cleaned.split())


def ingest_text_document(file_path: Path) -> Dict[str, Any]:
    suffix = file_path.suffix.lower()
    if suffix not in SUPPORTED_SIMPLE_INGEST_TYPES:
        return {
            "status": "skipped",
            "source_path": str(file_path),
            "reason": f"Unsupported type for simple mode: {suffix}",
        }

    content = file_path.read_text(encoding="utf-8", errors="ignore")
    normalized = normalize_text_for_demo(content, suffix)
    if not normalized:
        return {
            "status": "skipped",
            "source_path": str(file_path),
            "reason": "File was empty after normalization",
        }

    doc_key = str(file_path.resolve())
    RUNTIME_KNOWLEDGE_BASE[doc_key] = {
        "title": file_path.stem.replace("_", " ").title(),
        "content": normalized,
        "category": categorize_document_name(file_path.name),
        "source_path": str(file_path.resolve()),
        "source_url": None,
    }
    return {
        "status": "ingested",
        "source_path": str(file_path.resolve()),
        "category": RUNTIME_KNOWLEDGE_BASE[doc_key]["category"],
        "characters": len(normalized),
    }


def ingest_directory_for_demo(directory_path: str) -> Dict[str, Any]:
    resolved_dir = resolve_repo_path(directory_path)
    if not resolved_dir.exists() or not resolved_dir.is_dir():
        raise HTTPException(status_code=404, detail=f"Directory not found: {directory_path}")

    ingested = []
    skipped = []
    for file_path in sorted(resolved_dir.rglob("*")):
        if not file_path.is_file():
            continue
        result = ingest_text_document(file_path)
        if result["status"] == "ingested":
            ingested.append(result)
        else:
            skipped.append(result)

    if not ingested:
        raise HTTPException(
            status_code=400,
            detail="No supported .txt or .md documents were ingested in simple mode",
        )

    return {
        "status": "success",
        "mode": "fallback",
        "directory_path": str(resolved_dir),
        "ingested_count": len(ingested),
        "skipped_count": len(skipped),
        "ingested_files": ingested,
        "skipped_files": skipped,
    }

# Add CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Mount static files from frontend directory
app.mount("/static", StaticFiles(directory="frontend"), name="static")

# Serve the main index.html at the root
@app.get("/")
async def read_index():
    return serve_repo_asset("index.html")


@app.get("/index.html")
async def read_index_html():
    return serve_repo_asset("index.html")


@app.get("/admin.html")
async def read_admin():
    return serve_repo_asset("admin.html")


@app.get("/admin")
async def read_admin_redirect():
    return RedirectResponse(url="/admin.html", status_code=307)


@app.get("/logo.png")
async def read_logo():
    return serve_repo_asset("logo.png")


@app.get("/embed.js")
async def read_embed():
    return serve_repo_asset("embed.js")


@app.get("/kwekwe-chat-widget.js")
async def read_widget_js():
    return serve_repo_asset("kwekwe-chat-widget.js")


@app.get("/kwekwe-chat-widget.css")
async def read_widget_css():
    return serve_repo_asset("kwekwe-chat-widget.css")


@app.get("/kwekwe-chat-widget-professional.css")
async def read_widget_professional_css():
    return serve_repo_asset("kwekwe-chat-widget-professional.css")


@app.get("/kwekwe-demo.html")
async def read_widget_demo():
    return serve_repo_asset("kwekwe-demo.html")

class ChatRequest(BaseModel):
    message: str
    session_id: Optional[str] = None
    use_tools: Optional[bool] = False
    context: Optional[Dict[str, Any]] = None
    language: Optional[str] = "en"

class ChatResponse(BaseModel):
    response: str
    session_id: str
    sources: Optional[List[Dict[str, Any]]] = []
    timestamp: str
    query_type: str = "simple"


class SearchRequest(BaseModel):
    query: str = Field(..., min_length=1, max_length=500)
    limit: int = Field(default=5, ge=1, le=10)


class FeedbackRequest(BaseModel):
    session_id: str
    message_content: str
    helpful: bool
    comment: Optional[str] = Field(default=None, max_length=500)
    intent: Optional[str] = None


class LocalIngestionRequest(BaseModel):
    directory_path: str = Field(..., min_length=1)


class WebsiteSyncRequest(BaseModel):
    seeds: Optional[List[str]] = None
    max_pages: int = Field(default=10, ge=1, le=100)
    max_depth: int = Field(default=1, ge=0, le=3)


class OpenAIKeyUpdateRequest(BaseModel):
    api_key: str = Field(..., min_length=1)
    persist: bool = True


fallback_admin_router = APIRouter(prefix=f"{settings.API_V1_STR}/admin", tags=["admin"])


@fallback_admin_router.get("/overview")
async def fallback_admin_overview(x_admin_key: Optional[str] = Header(default=None)):
    require_admin_key(x_admin_key)
    analytics = await analytics_store.get_summary()
    return {
        "mode": "fallback",
        "vector_store": None,
        "analytics": analytics,
        "knowledge_base": {
            "mock_documents": len(MOCK_KNOWLEDGE_BASE),
            "runtime_documents": len(RUNTIME_KNOWLEDGE_BASE),
            "total_documents": len(MOCK_KNOWLEDGE_BASE) + len(RUNTIME_KNOWLEDGE_BASE),
        },
        "upload_dir": str(resolve_repo_path(settings.UPLOAD_DIR)),
        "website_sync_available": False,
        "admin_import_error": str(ADMIN_ROUTER_IMPORT_ERROR) if ADMIN_ROUTER_IMPORT_ERROR else None,
        "openai": get_openai_key_status(),
    }


@fallback_admin_router.post("/ingest/local")
async def fallback_ingest_local(
    request: LocalIngestionRequest,
    x_admin_key: Optional[str] = Header(default=None),
):
    require_admin_key(x_admin_key)
    return ingest_directory_for_demo(request.directory_path)


@fallback_admin_router.post("/ingest/upload")
async def fallback_upload_documents(
    files: List[UploadFile] = File(...),
    x_admin_key: Optional[str] = Header(default=None),
):
    require_admin_key(x_admin_key)
    upload_dir = resolve_repo_path(settings.UPLOAD_DIR)
    upload_dir.mkdir(parents=True, exist_ok=True)

    ingested_files = []
    skipped_files = []

    for upload in files:
        original_name = Path(upload.filename or "upload.txt").name
        stored_path = upload_dir / original_name
        stored_path.write_bytes(await upload.read())
        result = ingest_text_document(stored_path)
        if result["status"] == "ingested":
            ingested_files.append(result)
        else:
            skipped_files.append(result)

    if not ingested_files:
        raise HTTPException(
            status_code=400,
            detail="No supported .txt or .md files were uploaded for simple mode ingestion",
        )

    return {
        "status": "success",
        "mode": "fallback",
        "uploaded_count": len(ingested_files),
        "uploaded_files": ingested_files,
        "skipped_files": skipped_files,
    }


@fallback_admin_router.post("/sync/website")
async def fallback_sync_website(
    request: WebsiteSyncRequest,
    x_admin_key: Optional[str] = Header(default=None),
):
    require_admin_key(x_admin_key)
    raise HTTPException(
        status_code=501,
        detail="Website sync is unavailable in simple mode. Install full backend dependencies and run the full admin stack to enable crawling.",
    )


@fallback_admin_router.get("/analytics/summary")
async def fallback_analytics_summary(x_admin_key: Optional[str] = Header(default=None)):
    require_admin_key(x_admin_key)
    summary = await analytics_store.get_summary()
    summary["mode"] = "fallback"
    return summary


@fallback_admin_router.get("/feedback")
async def fallback_feedback(limit: int = 50, x_admin_key: Optional[str] = Header(default=None)):
    require_admin_key(x_admin_key)
    return {"feedback": await analytics_store.list_feedback(limit=limit), "mode": "fallback"}


@fallback_admin_router.get("/openai")
async def fallback_get_openai_settings(x_admin_key: Optional[str] = Header(default=None)):
    require_admin_key(x_admin_key)
    status = get_openai_key_status()
    status["llm_configured"] = bool(settings.OPENAI_API_KEY and AsyncOpenAI is not None)
    status["mode"] = "fallback"
    return status


@fallback_admin_router.post("/openai")
async def fallback_update_openai_settings(
    request: OpenAIKeyUpdateRequest,
    x_admin_key: Optional[str] = Header(default=None),
):
    require_admin_key(x_admin_key)
    try:
        status = set_openai_api_key(request.api_key, persist=request.persist)
    except ValueError as exc:
        raise HTTPException(status_code=400, detail=str(exc))

    reset_simple_openai_client()
    status["llm_configured"] = bool(settings.OPENAI_API_KEY and AsyncOpenAI is not None)
    status["mode"] = "fallback"
    status["message"] = "OpenAI API key updated"
    return status


@fallback_admin_router.delete("/openai")
async def fallback_delete_openai_settings(
    persist: bool = True,
    x_admin_key: Optional[str] = Header(default=None),
):
    require_admin_key(x_admin_key)
    status = clear_openai_api_key(persist=persist)
    reset_simple_openai_client()
    status["llm_configured"] = bool(settings.OPENAI_API_KEY and AsyncOpenAI is not None)
    status["mode"] = "fallback"
    status["message"] = "OpenAI API key cleared"
    return status


if admin_router is not None:
    app.include_router(admin_router, prefix=settings.API_V1_STR)
else:
    app.include_router(fallback_admin_router)

def get_simple_response(message: str) -> Optional[str]:
    """Return a direct Kwekwe Polytechnic answer when the question matches known topics."""
    message_lower = message.lower()
    
    if any(keyword in message_lower for keyword in ["engineering", "automotive", "electrical", "mechanical", "civil"]):
        if "requirements" in message_lower or "entry" in message_lower:
            return """
            Kwekwe Polytechnic - Engineering Division Entry Requirements
            
            General Requirements for all Engineering Programs:
            - Minimum 5 Ordinary Level Passes including Mathematics, English Language and a relevant Science Subject with grade C or better
            - For National Diploma (ND) students, applicants must possess the relevant National Certificate (NC)
            
            Engineering Departments and Heads:
            - Automotive Engineering: Mr Mutiza
            - Mechanical Engineering: Mr Mundandi  
            - Electrical Engineering: Mr Sibanda
            - Civil Engineering: Mr Chivare
            
            Available Courses:
            Automotive Engineering: Motor Vehicle Mechanics(NC), Automobile Electrics and Electronics(NC), Motor Vehicle Body Repair(NC), Precision Machining(NC)
            Mechanical Engineering: Diesel Plant Fitting(NC), Fabrication(NC), Refrigeration(NC), Machineshop(NC)
            Electrical Engineering: Electrical Power(NC, ND), Instrumentation and Control(NC, ND), Electronic Communication Systems(NC, ND), Computer Systems(NC)
            Civil Engineering: Quantity Surveying(NC, ND), Building Technology(NC), Plumbing and Drain laying(NC, ND)
            """
        elif "head" in message_lower or "department" in message_lower or "who" in message_lower:
            return "Engineering Division Heads: Mr Mutiza (Automotive), Mr Mundandi (Mechanical), Mr Sibanda (Electrical), Mr Chivare (Civil). The overall Engineering Division is led by the Principal."
        else:
            return "Kwekwe Polytechnic Engineering Division offers Automotive, Mechanical, Electrical, and Civil Engineering programs at NC and ND levels. All require 5 'O' Levels including Math, English, and Science at grade C or better."
    
    elif any(keyword in message_lower for keyword in ["commerce", "business", "management", "accountancy", "banking"]):
        if "requirements" in message_lower or "entry" in message_lower:
            return """
            Kwekwe Polytechnic - Commerce Division Entry Requirements
            
            Management Department Requirements:
            - For National Certificate (NC): Minimum 5 Ordinary Level Passes including English Language
            - For National Diploma (ND): Applicants must possess the relevant NC
            
            Business Studies Department Requirements:
            - For NC: Minimum 5 Ordinary Level Passes including Mathematics and English Language
            - For ND: Applicants must possess the relevant NC
            
            Department Heads:
            - Management: Mr Sambama
            - Business Studies: Mr Vuma
            
            Available Courses:
            Management: Office Management(NC,ND), Human Resources Management(NC,ND), Sales & Marketing Management(NC,ND), Purchasing & Supply Management(NC,ND), Records Management & Information Science(NC,ND)
            Business Studies: Accountancy(NC,ND), Banking and Finance(NC,ND, HND)
            """
        elif "head" in message_lower or "department" in message_lower:
            return "Commerce Division Heads: Mr Sambama (Management), Mr Vuma (Business Studies)."
        else:
            return "Commerce programs include Management and Business Studies. Management requires English at grade C, while Business Studies requires both Math and English at grade C for NC level."
    
    elif any(keyword in message_lower for keyword in ["applied sciences", "science", "laboratory", "environmental", "chemistry", "food", "metallurgical", "industrial", "information technology"]):
        return """
        Kwekwe Polytechnic - Applied Sciences Division
        
        Departments and Heads:
        - Information Technology: Mrs Munhuwakare
        - Biological Sciences: Mrs Dube
        - Physical Sciences: Mr Mapiye
        
        Available Courses and Requirements:
        Information Technology Department:
        - Information Technology(NC,ND,HND)
        - Entry: 5 Ordinary Level Passes including Mathematics, English Language and a relevant Science subject
        
        Biological Sciences Department:
        - Food Science(NC)
        - Entry: 5 Ordinary Level Passes including Mathematics, English Language and a relevant Science subject
        
        Physical Sciences Department:
        - Metallurgical Assaying(NC, ND)
        - Industrial Metallurgy(NC, ND)
        - Laboratory Technology(NC)
        - Entry: 5 Ordinary Level Passes including Mathematics, English Language and a relevant Science subject
        """
    
    elif any(keyword in message_lower for keyword in ["b-tech", "btech", "bachelor", "technology", "industrial", "manufacturing", "electrical power"]):
        return """
        Kwekwe Polytechnic - Bachelor of Technology Programs
        
        Available B-Tech Programs:
        - B-Tech(Honors) Degree in Industrial and Manufacturing Engineering - Coordinator: Mr B. Kufa
        - B-Tech(Honors) Degree in Electrical Power Engineering - Coordinator: Mr J. Makonese
        
        Industrial and Manufacturing Engineering Requirements:
        - 5 O level subjects including English Language, Mathematics and a relevant Science subject with grade C or better
        - National Certificate in Machineshop Engineering, Fabrication Engineering, Refrigeration and Air Conditioning, Millwrights, Foundry, Draughting and Design or equivalent
        - Journeyman class 1 certificate
        
        Electrical Power Engineering Requirements:
        - 5 O level subjects including English Language, Mathematics and a relevant Science subject with grade C or better
        - National Certificate in Electrical Power Engineering
        - Journeyman class 1 certificate
        - National Diploma (ND) in Electrical Power Engineering or equivalent
        - Higher National Diploma (HND) in Electrical Power Engineering or in Instrumentation and control engineering
        """
    
    elif any(keyword in message_lower for keyword in ["ace", "artisan", "continuing", "short courses", "cosmetology", "tourism", "hospitality", "clothing", "textile", "art", "design"]):
        return """
        Kwekwe Polytechnic - Adult & Continuing Education (A.C.E) Programs
        
        Available Courses and Coordinators:
        - Cosmetology: M Mbirimi - National Certificate - Entry: 5 O' Level Subjects (English Language + four others)
        - Tourism & Hospitality: Ms F. Mlambo - National Certificate - Entry: 5 O' Level Subjects (English Language + four others)
        - Clothing & Textile Design: B. Muputisi - National Certificate - Entry: 5 O' Level Subjects (English Language + four others)
        - Applied Art and Design: National Certificate - Entry: 5 O' Level Subjects (English Language + four others)
        - Traditional Apprentice Programme: Various certifications available
        - Integrated Skills Expansion Outreach Program: Community-based skills training
        
        All ACE programs require 5 O' Level subjects including English Language plus four others for National Certificate courses.
        """
    
    elif any(keyword in message_lower for keyword in ["fee", "cost", "payment", "tuition", "bank", "paynow", "ecocash", "onemoney"]):
        return """
        Kwekwe Polytechnic - 2026 Payment Information
        
        Banking Details:
        ZB Bank Kwekwe Branch:
        - USD Account: 4556375118405
        - ZIG Account: 4556375118080
        
        CBZ Bank Kwekwe Branch:
        - USD Account: 10720303740098
        - ZIG Account: 01420303740058
        
        Other Payment Methods:
        - Paynow (Online payment gateway)
        - Ecocash (Mobile money)
        - OneMoney (Mobile money)
        
        For detailed fee structures per program, please contact the admissions office or check the official fees notice on the website.
        """
    
    elif any(keyword in message_lower for keyword in ["contact", "phone", "email", "address", "location"]):
        return """
        Kwekwe Polytechnic - Contact Information
        
        Phone Numbers:
        - +263 8612 122991
        - 0786 658 480
        - 0711 806 837 (WhatsApp)
        
        Email:
        - infor@kwekwepoly.ac.zw
        
        Online Applications:
        - https://apply.kwekwepoly.ac.zw
        
        Student Portal:
        - http://elearning.kwekwepoly.ac.zw/
        
        For detailed inquiries, feel free to contact us via phone or send us a message through our official channels.
        """
    
    elif any(keyword in message_lower for keyword in ["hexco", "results", "examination", "november"]):
        return """
        HEXCO Results Information:
        - November 2025 HEXCO results are available for collection
        - Students can collect their results from the institution
        - For specific collection dates and procedures, please contact the examinations department
        
        HEXCO (Higher Education Examination Council) is the national body responsible for technical and vocational education examinations in Zimbabwe.
        """
    
    elif any(keyword in message_lower for keyword in ["intake", "january", "2026", "admission", "opening"]):
        return """
        January 2026 Intake Information:
        - January 2026 Intake has been re-advertised
        - Opening dates for January 2026 Intake Students have been announced
        - Applications are still being accepted for various programs
        - For specific opening dates and application deadlines, please check the official notices or contact admissions
        
        To apply: https://apply.kwekwepoly.ac.zw
        """
    
    elif "hello" in message_lower or "hi" in message_lower:
        return "Hello! Welcome to Kwekwe Polytechnic. I can help you with information about our courses (Engineering, Commerce, Applied Sciences, B-Tech, A.C.E), fees, admission requirements, HEXCO results, and more. What would you like to know?"
    
    elif "who" in message_lower and ("you" in message_lower or "i" in message_lower):
        return "I am the Kwekwe Polytechnic AI assistant. I'm here to help you with information about our academic programs, fees, admission requirements, contact information, and institutional services."
    
    else:
        return None


def get_simple_search_results(query: str, limit: int = 5) -> List[Dict[str, Any]]:
    """Search the local Kwekwe Polytechnic knowledge using lightweight relevance scoring."""
    query_terms = extract_query_terms(query)
    if not query_terms:
        return []

    results: List[Dict[str, Any]] = []

    for _, content, metadata in iter_knowledge_entries():
        lowered = content.lower()
        matched_terms = [term for term in query_terms if term in lowered]
        if not matched_terms:
            continue

        coverage = len(set(matched_terms)) / max(len(query_terms), 1)
        frequency_bonus = min(
            0.18,
            sum(lowered.count(term) for term in set(matched_terms)) * 0.025,
        )
        relevance = min(0.99, 0.32 + (coverage * 0.52) + frequency_bonus)
        snippet = " ".join(content.split())[:240]

        results.append(
            {
                "title": metadata["title"],
                "snippet": f"{snippet}..." if len(content) > 240 else snippet,
                "category": metadata["category"],
                "source_url": metadata.get("source_url"),
                "source_path": metadata.get("source_path"),
                "matched_terms": matched_terms,
                "similarity_score": relevance,
            }
        )

    ordered = sorted(results, key=lambda item: item["similarity_score"], reverse=True)
    return ordered[:limit]


def has_related_knowledge(results: List[Dict[str, Any]]) -> bool:
    if not results:
        return False
    return results[0].get("similarity_score", 0.0) >= MIN_RELEVANCE_SCORE


def build_knowledge_based_answer(result: Dict[str, Any]) -> str:
    snippet = result.get("snippet", "").strip()
    title = result.get("title", "Kwekwe Polytechnic information")
    return f"Based on {title}: {snippet}"


def build_chat_sources(results: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    sources = []
    for result in results:
        sources.append(
            {
                "content": result.get("snippet", ""),
                "metadata": {
                    "filename": result.get("title", "Institutional source"),
                    "category": result.get("category", "official source"),
                    "source_url": result.get("source_url"),
                    "source": result.get("source_path"),
                },
                "similarity_score": result.get("similarity_score", 0.0),
            }
        )
    return sources


def build_kwekwe_only_fallback() -> str:
    return (
        "I could not find enough related information in the current Kwekwe Polytechnic knowledge base. "
        "I can only help with Kwekwe Polytechnic topics such as programmes, admissions, fees, exams, "
        "student services, accommodation, and contact details."
    )


async def get_openai_fallback_response(message: str, knowledge_results: Optional[List[Dict[str, Any]]] = None) -> Optional[str]:
    client = get_openai_client()
    if client is None:
        return None

    retrieved_context = []
    for result in (knowledge_results or [])[:2]:
        retrieved_context.append(
            f"Title: {result.get('title')}\n"
            f"Category: {result.get('category')}\n"
            f"Snippet: {result.get('snippet')}"
        )

    prompt = (
        f"User question: {message}\n\n"
        f"General institution context:\n{GENERAL_KWEKWE_CONTEXT}\n\n"
        "Retrieved local knowledge:\n"
        f"{chr(10).join(retrieved_context) if retrieved_context else 'No directly relevant local knowledge was found.'}\n\n"
        "Answer as the Kwekwe Polytechnic assistant."
    )

    try:
        response = await client.chat.completions.create(
            model=settings.OPENAI_MODEL,
            temperature=0.2,
            max_tokens=260,
            messages=[
                {"role": "system", "content": OPENAI_SCOPE_PROMPT},
                {"role": "user", "content": prompt},
            ],
        )
        answer = response.choices[0].message.content if response.choices else None
        return answer.strip() if answer else None
    except Exception:
        return None


async def build_chat_response(request: ChatRequest) -> ChatResponse:
    direct_response = get_simple_response(request.message)
    search_results = get_simple_search_results(request.message, limit=3)
    session_id = request.session_id or f"session_{datetime.now().strftime('%Y%m%d_%H%M%S')}"
    response_text = direct_response
    sources: List[Dict[str, Any]] = []
    query_type = "simple"

    if response_text is None and has_related_knowledge(search_results):
        response_text = build_knowledge_based_answer(search_results[0])
        sources = build_chat_sources(search_results)
        query_type = "knowledge"

    if response_text is None:
        openai_response = await get_openai_fallback_response(request.message, search_results)
        if openai_response:
            response_text = openai_response
            query_type = "openai_fallback"

    if response_text is None:
        response_text = build_kwekwe_only_fallback()
        query_type = "scope_fallback"

    return ChatResponse(
        response=response_text,
        session_id=session_id,
        sources=sources,
        timestamp=datetime.now().isoformat(),
        query_type=query_type
    )

@app.get("/api")
async def api_info():
    """API information endpoint"""
    return {
        "message": "Kwekwe Polytechnic Chatbot API",
        "version": "1.0.0",
        "status": "running",
        "endpoints": {
            "chat": "/chat/query",
            "health": "/health",
            "api_info": "/api"
        }
    }

@app.post("/chat/query")
async def chat_query(request: ChatRequest):
    """Main chat endpoint"""
    try:
        return await build_chat_response(request)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Internal server error: {str(e)}")


@app.post("/api/v1/chat/query")
async def chat_query_v1(request: ChatRequest):
    """Frontend-compatible chat endpoint."""
    try:
        return await build_chat_response(request)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Internal server error: {str(e)}")


@app.post("/api/v1/chat/search")
async def search_v1(request: SearchRequest):
    """Frontend-compatible search endpoint."""
    try:
        results = get_simple_search_results(request.query, request.limit)
        return {"results": results, "count": len(results)}
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Search failed: {str(e)}")


@app.post("/api/v1/chat/feedback")
async def feedback_v1(request: FeedbackRequest):
    """Frontend-compatible feedback capture."""
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
        raise HTTPException(status_code=500, detail=f"Feedback failed: {str(e)}")

@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "timestamp": datetime.now().isoformat(),
        "version": "1.0.0",
        "environment": "development",
        "admin_enabled": True,
        "admin_mode": get_admin_mode(),
        "admin_import_error": str(ADMIN_ROUTER_IMPORT_ERROR) if ADMIN_ROUTER_IMPORT_ERROR else None,
        "runtime_documents": len(RUNTIME_KNOWLEDGE_BASE),
        "openai_fallback_enabled": bool(settings.OPENAI_API_KEY and AsyncOpenAI is not None),
        "openai_model": settings.OPENAI_MODEL if settings.OPENAI_API_KEY else None,
    }


@app.get("/api/v1/chat/health")
async def health_check_v1():
    """Frontend-compatible health endpoint."""
    return await health_check()

if __name__ == "__main__":
    uvicorn.run(
        "main-simple:app",
        host="0.0.0.0",
        port=8000,
        reload=True,
        log_level="info"
    )
