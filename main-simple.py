import asyncio
import uvicorn
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from fastapi.responses import FileResponse
from pydantic import BaseModel
from typing import List, Dict, Any, Optional
from datetime import datetime
import os

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
    return FileResponse('frontend/index.html')

class ChatRequest(BaseModel):
    message: str
    session_id: Optional[str] = None
    use_tools: Optional[bool] = False

class ChatResponse(BaseModel):
    response: str
    session_id: str
    sources: Optional[List[Dict[str, Any]]] = []
    timestamp: str
    query_type: str = "simple"

def get_simple_response(message: str) -> str:
    """Get simple response based on keywords"""
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
        return "I can help you with information about Kwekwe Polytechnic's programs including Engineering, Commerce, Applied Sciences, B-Tech, and A.C.E programs. I can also provide information about fees, admission requirements, contact details, HEXCO results, and January 2026 intake. Please ask me about any of these topics."

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
        # Generate simple response
        response_text = get_simple_response(request.message)
        
        # Generate session ID if not provided
        session_id = request.session_id or f"session_{datetime.now().strftime('%Y%m%d_%H%M%S')}"
        
        return ChatResponse(
            response=response_text,
            session_id=session_id,
            timestamp=datetime.now().isoformat(),
            query_type="simple"
        )
        
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Internal server error: {str(e)}")

@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "timestamp": datetime.now().isoformat(),
        "version": "1.0.0",
        "environment": "development"
    }

if __name__ == "__main__":
    uvicorn.run(
        "main-simple:app",
        host="0.0.0.0",
        port=8000,
        reload=True,
        log_level="info"
    )
