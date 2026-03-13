import asyncio
import logging
import re
from datetime import datetime
from typing import Any, Dict, List, Optional

from langchain.chains import RetrievalQA
from langchain.chat_models import ChatOpenAI
from langchain.prompts import PromptTemplate
from langchain.schema import BaseRetriever, Document

from backend.config.settings import settings
from backend.core.vector_store import vector_store

logger = logging.getLogger(__name__)

LANGUAGE_LABELS = {
    "en": "English",
    "sn": "Shona",
    "nd": "Ndebele",
}

OFFICE_DIRECTORY: Dict[str, Dict[str, Any]] = {
    "admissions": {
        "office": "Admissions Office",
        "message": "For application support, intake guidance, and program selection.",
        "links": [
            {"label": "Apply Online", "url": "https://apply.kwekwepoly.ac.zw/"},
            {"label": "Official Website", "url": "https://www.kwekwepoly.ac.zw/"},
        ],
        "contact": {"phone": "+263 8612 122991", "email": "infor@kwekwepoly.ac.zw"},
    },
    "fees": {
        "office": "Accounts Office",
        "message": "For verified balances, payment issues, and receipting support.",
        "links": [{"label": "Payment Guidance", "url": "https://www.kwekwepoly.ac.zw/"}],
        "contact": {"phone": "+263 8612 122991", "email": "infor@kwekwepoly.ac.zw"},
    },
    "portal": {
        "office": "ICT Unit",
        "message": "For student portal access, e-learning support, and password help.",
        "links": [
            {"label": "Student Portal", "url": "http://elearning.kwekwepoly.ac.zw/"},
            {"label": "ICT Support", "url": "https://www.kwekwepoly.ac.zw/portal.php"},
        ],
        "contact": {"phone": "+263 711 806 837", "email": "infor@kwekwepoly.ac.zw"},
    },
    "results": {
        "office": "Examinations Office",
        "message": "For result availability, collection guidance, and examination notices.",
        "links": [{"label": "Official Website", "url": "https://www.kwekwepoly.ac.zw/"}],
        "contact": {"phone": "+263 8612 122991", "email": "infor@kwekwepoly.ac.zw"},
    },
    "accommodation": {
        "office": "Accommodation Desk",
        "message": "For hostels, off-campus accommodation, and student welfare logistics.",
        "links": [{"label": "Accommodation Information", "url": "https://www.kwekwepoly.ac.zw/hostel.php"}],
        "contact": {"phone": "+263 8612 122991", "email": "infor@kwekwepoly.ac.zw"},
    },
    "programs": {
        "office": "Academic Registry",
        "message": "For program structure, entry requirements, and progression pathways.",
        "links": [{"label": "Academic Pages", "url": "https://www.kwekwepoly.ac.zw/"}],
        "contact": {"phone": "+263 8612 122991", "email": "infor@kwekwepoly.ac.zw"},
    },
    "general": {
        "office": "Kwekwe Polytechnic Front Office",
        "message": "For help reaching the right office or confirming official information.",
        "links": [{"label": "Official Website", "url": "https://www.kwekwepoly.ac.zw/"}],
        "contact": {"phone": "+263 8612 122991", "email": "infor@kwekwepoly.ac.zw"},
    },
}


class CustomRetriever(BaseRetriever):
    """Custom retriever that uses our vector store."""

    vector_store: Any
    top_k: int = 5

    def _get_relevant_documents(self, query: str, **kwargs) -> List[Document]:
        try:
            results = self.vector_store.query(query, n_results=self.top_k)
            documents = []
            for doc_text, metadata, distance in zip(
                results["documents"],
                results["metadatas"],
                results["distances"],
            ):
                similarity = max(0.0, min(1.0, 1 - float(distance)))
                documents.append(
                    Document(
                        page_content=doc_text,
                        metadata={
                            **metadata,
                            "similarity_score": similarity,
                            "retrieved_at": datetime.now().isoformat(),
                        },
                    )
                )
            return documents
        except Exception as e:
            logger.error(f"Error retrieving documents: {str(e)}")
            return []


class RAGEngine:
    def __init__(self):
        self.llm = None
        self.retriever = None
        self.qa_chain = None
        self._initialize_components()

    def _initialize_components(self):
        try:
            if settings.OPENAI_API_KEY:
                self.llm = ChatOpenAI(
                    model=settings.OPENAI_MODEL,
                    openai_api_key=settings.OPENAI_API_KEY,
                    openai_api_base=settings.OPENAI_BASE_URL,
                    temperature=0.1,
                )
            else:
                logger.warning("OpenAI API key not configured. Using retrieval-only responses.")
                self.llm = None

            self.retriever = CustomRetriever(vector_store=vector_store, top_k=5)
            prompt_template = """
            You are the official AI assistant for Kwekwe Polytechnic in Zimbabwe.
            Answer only from the provided context. If the answer is incomplete, say so clearly.
            Keep the tone professional, concise, and student-friendly.
            When possible, mention the most relevant office or next step.

            Context:
            {context}

            Question:
            {question}

            Answer:
            """
            prompt = PromptTemplate(template=prompt_template, input_variables=["context", "question"])

            if self.llm:
                self.qa_chain = RetrievalQA.from_chain_type(
                    llm=self.llm,
                    chain_type="stuff",
                    retriever=self.retriever,
                    chain_type_kwargs={"prompt": prompt},
                    return_source_documents=True,
                )

            logger.info("RAG engine initialized successfully")
        except Exception as e:
            logger.error(f"Error initializing RAG engine: {str(e)}")

    def refresh_configuration(self):
        """Rebuild OpenAI-dependent components after runtime configuration changes."""
        self.llm = None
        self.retriever = None
        self.qa_chain = None
        self._initialize_components()

    async def query(
        self,
        question: str,
        session_id: Optional[str] = None,
        preferred_language: str = "en",
    ) -> Dict[str, Any]:
        try:
            docs = self.retriever._get_relevant_documents(question) if self.retriever else []
            intent = self._infer_intent(question, docs)
            confidence = self._calculate_confidence(docs)
            handoff = self._build_handoff(intent, confidence, question)
            suggested_actions = self._build_suggested_actions(intent)

            if self._requires_verification(question, intent):
                answer = self._verification_required_message(intent, preferred_language)
                return {
                    "answer": answer,
                    "sources": self._format_sources(docs[:3]),
                    "session_id": session_id,
                    "timestamp": datetime.now().isoformat(),
                    "query_type": "verification_required",
                    "confidence": confidence,
                    "handoff": handoff,
                    "suggested_actions": suggested_actions,
                    "intent": intent,
                }

            if not docs:
                answer = self._fallback_answer(intent, preferred_language, low_context=True)
                return {
                    "answer": answer,
                    "sources": [],
                    "session_id": session_id,
                    "timestamp": datetime.now().isoformat(),
                    "query_type": "handoff",
                    "confidence": confidence,
                    "handoff": handoff,
                    "suggested_actions": suggested_actions,
                    "intent": intent,
                }

            if confidence["label"] == "low":
                answer = self._fallback_answer(intent, preferred_language, low_context=True)
                return {
                    "answer": answer,
                    "sources": self._format_sources(docs[:3]),
                    "session_id": session_id,
                    "timestamp": datetime.now().isoformat(),
                    "query_type": "low_confidence",
                    "confidence": confidence,
                    "handoff": handoff,
                    "suggested_actions": suggested_actions,
                    "intent": intent,
                }

            if self.qa_chain:
                query_text = question
                if preferred_language in LANGUAGE_LABELS and preferred_language != "en":
                    query_text = f"{question}\nPlease answer in {LANGUAGE_LABELS[preferred_language]}."
                result = await asyncio.get_event_loop().run_in_executor(
                    None,
                    self.qa_chain,
                    {"query": query_text},
                )
                source_docs = self._format_sources(result.get("source_documents", docs))
                answer = result.get("result") or self._extractive_answer(question, docs, preferred_language)
            else:
                source_docs = self._format_sources(docs)
                answer = self._extractive_answer(question, docs, preferred_language)

            if confidence["label"] == "medium" and handoff and "next step" not in answer.lower():
                answer = f"{answer}\n\nIf you need an official confirmation or a personalized follow-up, the {handoff['office']} can help."

            return {
                "answer": answer,
                "sources": source_docs[:3],
                "session_id": session_id,
                "timestamp": datetime.now().isoformat(),
                "query_type": "rag" if self.qa_chain else "retrieval",
                "confidence": confidence,
                "handoff": handoff if confidence["label"] != "high" or intent in {"fees", "results", "portal"} else None,
                "suggested_actions": suggested_actions,
                "intent": intent,
            }
        except Exception as e:
            logger.error(f"Error in RAG query: {str(e)}")
            return {
                "answer": "I'm experiencing technical difficulties. Please try again later or contact Kwekwe Polytechnic for support.",
                "sources": [],
                "timestamp": datetime.now().isoformat(),
                "query_type": "error",
                "confidence": {"label": "low", "score": 0.0},
                "handoff": self._build_handoff("general", {"label": "low", "score": 0.0}, question),
                "suggested_actions": self._build_suggested_actions("general"),
                "intent": "error",
            }

    async def search(self, query: str, limit: int = 5) -> List[Dict[str, Any]]:
        docs = self.retriever._get_relevant_documents(query) if self.retriever else []
        results: List[Dict[str, Any]] = []
        for doc in docs[:limit]:
            results.append(
                {
                    "title": doc.metadata.get("filename", "Institutional Document"),
                    "snippet": f"{doc.page_content[:220]}..." if len(doc.page_content) > 220 else doc.page_content,
                    "category": doc.metadata.get("category", "general"),
                    "source_url": doc.metadata.get("source_url") or doc.metadata.get("source"),
                    "similarity_score": round(doc.metadata.get("similarity_score", 0.0), 3),
                }
            )
        return results

    async def health_check(self) -> Dict[str, Any]:
        try:
            stats = vector_store.get_collection_stats()
            return {
                "status": "healthy",
                "vector_store_stats": stats,
                "llm_configured": self.llm is not None,
                "retriever_configured": self.retriever is not None,
                "timestamp": datetime.now().isoformat(),
            }
        except Exception as e:
            logger.error(f"Health check failed: {str(e)}")
            return {
                "status": "unhealthy",
                "error": str(e),
                "timestamp": datetime.now().isoformat(),
            }

    @staticmethod
    def _calculate_confidence(docs: List[Document]) -> Dict[str, Any]:
        if not docs:
            return {"label": "low", "score": 0.0}
        best_score = max(doc.metadata.get("similarity_score", 0.0) for doc in docs)
        if best_score >= 0.78:
            label = "high"
        elif best_score >= 0.62:
            label = "medium"
        else:
            label = "low"
        return {"label": label, "score": round(best_score, 3)}

    def _infer_intent(self, question: str, docs: List[Document]) -> str:
        question_lower = question.lower()
        if any(term in question_lower for term in ["apply", "application", "intake", "admission"]):
            return "admissions"
        if any(term in question_lower for term in ["fee", "payment", "tuition", "balance", "ecocash", "paynow", "onemoney"]):
            return "fees"
        if any(term in question_lower for term in ["portal", "e-learning", "password", "login", "ict"]):
            return "portal"
        if any(term in question_lower for term in ["result", "hexco", "exam", "transcript"]):
            return "results"
        if any(term in question_lower for term in ["hostel", "accommodation", "residence"]):
            return "accommodation"
        if any(term in question_lower for term in ["program", "course", "engineering", "commerce", "science", "b-tech", "ace"]):
            return "programs"

        if docs:
            top_category = docs[0].metadata.get("category", "")
            if top_category in {"fees", "admissions", "accommodation", "commerce", "engineering", "student_services"}:
                return {
                    "fees": "fees",
                    "admissions": "admissions",
                    "accommodation": "accommodation",
                    "student_services": "portal",
                    "commerce": "programs",
                    "engineering": "programs",
                }.get(top_category, "general")

        return "general"

    def _build_handoff(self, intent: str, confidence: Dict[str, Any], question: str) -> Optional[Dict[str, Any]]:
        office_key = intent if intent in OFFICE_DIRECTORY else "general"
        office = OFFICE_DIRECTORY.get(office_key, OFFICE_DIRECTORY["general"])
        if confidence["label"] == "high" and office_key not in {"fees", "results", "portal"}:
            return None
        return {
            "office": office["office"],
            "message": office["message"],
            "contact": office["contact"],
            "links": office["links"],
        }

    def _build_suggested_actions(self, intent: str) -> List[Dict[str, Any]]:
        base_actions = {
            "admissions": [
                {"label": "Apply Online", "type": "link", "url": "https://apply.kwekwepoly.ac.zw/"},
                {"label": "Ask About Entry Requirements", "type": "prompt", "prompt": "What are the entry requirements for my chosen program?"},
            ],
            "fees": [
                {"label": "Payment Methods", "type": "prompt", "prompt": "What payment methods are available for fees?"},
                {"label": "Banking Guidance", "type": "prompt", "prompt": "Show me the Kwekwe Polytechnic fee payment options in USD and ZiG."},
            ],
            "portal": [
                {"label": "Open Student Portal", "type": "link", "url": "http://elearning.kwekwepoly.ac.zw/"},
                {"label": "Portal Help", "type": "prompt", "prompt": "How do I access the student portal?"},
            ],
            "results": [
                {"label": "Exam Information", "type": "prompt", "prompt": "What should students know about HEXCO results?"},
                {"label": "Contact Examinations", "type": "link", "url": "https://www.kwekwepoly.ac.zw/"},
            ],
            "accommodation": [
                {"label": "Accommodation Info", "type": "link", "url": "https://www.kwekwepoly.ac.zw/hostel.php"},
                {"label": "Ask About Hostels", "type": "prompt", "prompt": "Tell me about accommodation at Kwekwe Polytechnic."},
            ],
            "programs": [
                {"label": "Engineering Programs", "type": "prompt", "prompt": "Which engineering programs are available at Kwekwe Polytechnic?"},
                {"label": "Commerce Programs", "type": "prompt", "prompt": "Which commerce programs are available at Kwekwe Polytechnic?"},
            ],
            "general": [
                {"label": "Official Website", "type": "link", "url": "https://www.kwekwepoly.ac.zw/"},
                {"label": "Admissions Help", "type": "prompt", "prompt": "How do I apply to Kwekwe Polytechnic?"},
            ],
        }
        return base_actions.get(intent, base_actions["general"])

    def _extractive_answer(self, question: str, docs: List[Document], preferred_language: str) -> str:
        sentences: List[str] = []
        for doc in docs[:3]:
            doc_sentences = re.split(r"(?<=[.!?])\s+", doc.page_content)
            for sentence in doc_sentences:
                clean = sentence.strip()
                if len(clean) < 40:
                    continue
                if clean not in sentences:
                    sentences.append(clean)
                if len(sentences) >= 4:
                    break
            if len(sentences) >= 4:
                break

        body = " ".join(sentences[:3]) if sentences else docs[0].page_content[:400]
        prefix = {
            "en": "Based on official Kwekwe Polytechnic information, ",
            "sn": "Zvichienderana neruzivo rwepamutemo rweKwekwe Polytechnic, ",
            "nd": "Ngokusekelwa kulwazi olusemthethweni lweKwekwe Polytechnic, ",
        }.get(preferred_language, "Based on official Kwekwe Polytechnic information, ")
        return prefix + body

    def _fallback_answer(self, intent: str, preferred_language: str, low_context: bool = False) -> str:
        handoff = self._build_handoff(intent, {"label": "low", "score": 0.0}, "")
        office_name = handoff["office"] if handoff else "the relevant office"
        messages = {
            "en": f"I found limited official information for that question. For an authoritative answer, please follow up with {office_name}.",
            "sn": f"Ndawana ruzivo rushoma rwepamutemo pamubvunzo uyu. Kuti muwane mhinduro yakasimbiswa, tapota tauriranai ne{office_name}.",
            "nd": f"Ngithole ulwazi oluncane olusemthethweni ngalombuzo. Ukuze uthole impendulo eqinisekileyo, xhumana le{office_name}.",
        }
        return messages.get(preferred_language, messages["en"])

    def _verification_required_message(self, intent: str, preferred_language: str) -> str:
        office = OFFICE_DIRECTORY.get(intent, OFFICE_DIRECTORY["general"])["office"]
        messages = {
            "en": f"For privacy and security, personalized student information must be verified before it can be shared. Please contact {office} or use the official portal.",
            "sn": f"Nekuda kwekuchengetedzwa kwemashoko, ruzivo rwemunhu pachake runofanira kutanga rwasimbiswa. Tapota bata {office} kana kushandisa portal yepamutemo.",
            "nd": f"Ngenxa yokuvikelwa kolwazi lomuntu, imininingwane yomfundi idinga ukuqinisekiswa kuqala. Sicela uxhumane le{office} kumbe usebenzise i-portal esemthethweni.",
        }
        return messages.get(preferred_language, messages["en"])

    @staticmethod
    def _requires_verification(question: str, intent: str) -> bool:
        lower = question.lower()
        personal_markers = [" my ", " me ", " i ", "mine", "student id", "account", "balance", "results", "application status"]
        asks_personal = any(marker in f" {lower} " for marker in personal_markers)
        return asks_personal and intent in {"fees", "results", "admissions", "portal"}

    @staticmethod
    def _format_sources(docs: List[Document]) -> List[Dict[str, Any]]:
        formatted = []
        for doc in docs:
            formatted.append(
                {
                    "content": f"{doc.page_content[:220]}..." if len(doc.page_content) > 220 else doc.page_content,
                    "metadata": doc.metadata,
                    "similarity_score": round(doc.metadata.get("similarity_score", 0.0), 3),
                }
            )
        return formatted


rag_engine = RAGEngine()
