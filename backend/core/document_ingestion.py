from typing import List, Dict, Any, Optional
import logging
from pathlib import Path
from io import BytesIO
import hashlib
import re
import aiofiles
import PyPDF2
from docx import Document as DocxDocument
import markdown

from backend.core.vector_store import vector_store
from backend.config.settings import settings

logger = logging.getLogger(__name__)


class DocumentIngestion:
    def __init__(self):
        self.supported_formats = {".pdf", ".txt", ".docx", ".md"}
        self.chunk_size = 900
        self.chunk_overlap = 150
    
    async def ingest_directory(self, directory_path: str) -> bool:
        """Ingest all documents from a directory"""
        try:
            directory = Path(directory_path)
            if not directory.exists():
                logger.error(f"Directory {directory_path} does not exist")
                return False
            
            documents = []
            for file_path in sorted(directory.rglob("*")):
                if file_path.is_file() and file_path.suffix.lower() in self.supported_formats:
                    try:
                        content = await self._extract_text(file_path)
                        if content:
                            documents.extend(self._build_documents(file_path, content))
                    except Exception as e:
                        logger.error(f"Error processing {file_path}: {str(e)}")
                        continue
            
            if documents:
                success = vector_store.add_documents(documents)
                if success:
                    logger.info(f"Successfully ingested {len(documents)} documents")
                    return True
            
            return False
            
        except Exception as e:
            logger.error(f"Error ingesting directory: {str(e)}")
            return False

    async def ingest_file(self, file_path: str) -> bool:
        """Ingest a single file into the vector store."""
        try:
            path = Path(file_path)
            if not path.exists() or not path.is_file():
                logger.error(f"File {file_path} does not exist")
                return False

            if path.suffix.lower() not in self.supported_formats:
                logger.error(f"Unsupported file type for {file_path}")
                return False

            content = await self._extract_text(path)
            if not content:
                return False

            documents = self._build_documents(path, content)
            return vector_store.add_documents(documents)
        except Exception as e:
            logger.error(f"Error ingesting file {file_path}: {str(e)}")
            return False
    
    async def _extract_text(self, file_path: Path) -> Optional[str]:
        """Extract text from different file formats"""
        try:
            if file_path.suffix.lower() == ".pdf":
                return await self._extract_pdf_text(file_path)
            elif file_path.suffix.lower() == ".txt":
                return await self._extract_txt_text(file_path)
            elif file_path.suffix.lower() == ".docx":
                return await self._extract_docx_text(file_path)
            elif file_path.suffix.lower() == ".md":
                return await self._extract_markdown_text(file_path)
            else:
                logger.warning(f"Unsupported file format: {file_path.suffix}")
                return None
        except Exception as e:
            logger.error(f"Error extracting text from {file_path}: {str(e)}")
            return None
    
    async def _extract_pdf_text(self, file_path: Path) -> str:
        """Extract text from PDF files"""
        text = ""
        async with aiofiles.open(file_path, 'rb') as file:
            content = await file.read()
            pdf_reader = PyPDF2.PdfReader(BytesIO(content))
            for page in pdf_reader.pages:
                page_text = page.extract_text() or ""
                text += page_text + "\n"
        return text
    
    async def _extract_txt_text(self, file_path: Path) -> str:
        """Extract text from TXT files"""
        async with aiofiles.open(file_path, 'r', encoding='utf-8') as file:
            return await file.read()
    
    async def _extract_docx_text(self, file_path: Path) -> str:
        """Extract text from DOCX files"""
        doc = DocxDocument(file_path)
        text = ""
        for paragraph in doc.paragraphs:
            text += paragraph.text + "\n"
        return text
    
    async def _extract_markdown_text(self, file_path: Path) -> str:
        """Extract text from Markdown files"""
        async with aiofiles.open(file_path, 'r', encoding='utf-8') as file:
            md_content = await file.read()
            # Convert markdown to plain text (basic conversion)
            html = markdown.markdown(md_content)
            # Simple HTML tag removal
            import re
            text = re.sub(r'<[^>]+>', '', html)
            return text
    
    def _categorize_document(self, filename: str) -> str:
        """Categorize document based on filename"""
        filename_lower = filename.lower()
        
        if any(keyword in filename_lower for keyword in ["engineering", "automotive", "electrical", "mechanical"]):
            return "engineering"
        elif any(keyword in filename_lower for keyword in ["commerce", "business", "management"]):
            return "commerce"
        elif any(keyword in filename_lower for keyword in ["fee", "payment", "cost"]):
            return "fees"
        elif any(keyword in filename_lower for keyword in ["admission", "application", "intake"]):
            return "admissions"
        elif any(keyword in filename_lower for keyword in ["portal", "manual", "guide"]):
            return "student_services"
        elif any(keyword in filename_lower for keyword in ["hexco", "result", "examination"]):
            return "examinations"
        else:
            return "general"

    def _build_documents(self, file_path: Path, content: str) -> List[Dict[str, Any]]:
        """Split a source document into retrieval-friendly chunks."""
        chunks = self._chunk_text(content)
        category = self._categorize_document(file_path.name)
        documents = []

        for chunk_index, chunk in enumerate(chunks):
            documents.append({
                "id": self._build_document_id(file_path, chunk_index),
                "text": chunk,
                "metadata": {
                    "source": str(file_path),
                    "filename": file_path.name,
                    "file_type": file_path.suffix.lower(),
                    "category": category,
                    "chunk_index": chunk_index,
                    "chunk_count": len(chunks)
                }
            })

        return documents

    def _build_document_id(self, file_path: Path, chunk_index: int) -> str:
        """Create stable ids so re-ingestion updates existing chunks instead of duplicating them."""
        digest = hashlib.sha1(f"{file_path.resolve()}:{chunk_index}".encode("utf-8")).hexdigest()[:12]
        return f"{file_path.stem}_{chunk_index}_{digest}"

    def _chunk_text(self, text: str) -> List[str]:
        """Chunk long content for better retrieval quality."""
        normalized = re.sub(r"\n{3,}", "\n\n", text).strip()
        if not normalized:
            return []

        if len(normalized) <= self.chunk_size:
            return [normalized]

        chunks = []
        start = 0
        text_length = len(normalized)

        while start < text_length:
            end = min(start + self.chunk_size, text_length)

            if end < text_length:
                split_candidates = [
                    normalized.rfind("\n\n", start, end),
                    normalized.rfind(". ", start, end),
                    normalized.rfind("\n", start, end),
                ]
                best_split = max(split_candidates)
                if best_split > start + self.chunk_overlap:
                    end = best_split + (2 if normalized[best_split:best_split + 2] == ". " else 0)

            chunk = normalized[start:end].strip()
            if chunk:
                chunks.append(chunk)

            if end >= text_length:
                break

            start = max(end - self.chunk_overlap, start + 1)

        return chunks

    async def ensure_knowledge_base(self) -> bool:
        """Populate the vector store from repository documents if it is empty."""
        current_count = vector_store.get_collection_stats().get("document_count", 0)
        if current_count:
            logger.info("Knowledge base already populated")
            return True

        return await self.create_sample_documents()
    
    async def create_sample_documents(self) -> bool:
        """Create or ingest seed documents for testing and local development."""
        repository_docs_dir = Path("data/sample_docs")
        if repository_docs_dir.exists() and any(repository_docs_dir.iterdir()):
            return await self.ingest_directory(str(repository_docs_dir))

        sample_docs_dir = Path(settings.CHROMA_DB_PATH).parent / "sample_docs"
        sample_docs_dir.mkdir(exist_ok=True)
        
        # Sample engineering requirements
        engineering_content = """
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
        """
        
        # Sample commerce requirements
        commerce_content = """
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
        """
        
        # Sample fees structure
        fees_content = """
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
        
        # Save sample documents
        sample_files = [
            ("engineering_requirements.txt", engineering_content),
            ("commerce_requirements.txt", commerce_content),
            ("fees_structure_2026.txt", fees_content)
        ]
        
        for filename, content in sample_files:
            file_path = sample_docs_dir / filename
            async with aiofiles.open(file_path, 'w', encoding='utf-8') as file:
                await file.write(content)
        
        # Ingest the sample documents
        return await self.ingest_directory(str(sample_docs_dir))


# Global document ingestion instance
document_ingestion = DocumentIngestion()
