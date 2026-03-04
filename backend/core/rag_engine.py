import asyncio
from typing import List, Dict, Any, Optional
import logging
from datetime import datetime

from langchain.chains import RetrievalQA
from langchain.chat_models import ChatOpenAI
from langchain.embeddings import OpenAIEmbeddings
from langchain.prompts import PromptTemplate
from langchain.schema import BaseRetriever, Document
from langchain.vectorstores import Chroma

from backend.core.vector_store import vector_store
from backend.config.settings import settings

logger = logging.getLogger(__name__)


class CustomRetriever(BaseRetriever):
    """Custom retriever that uses our vector store"""
    
    def __init__(self, vector_store_instance, top_k: int = 5):
        super().__init__()
        self.vector_store = vector_store_instance
        self.top_k = top_k
    
    def _get_relevant_documents(self, query: str, **kwargs) -> List[Document]:
        """Retrieve relevant documents"""
        try:
            results = self.vector_store.query(query, n_results=self.top_k)
            
            documents = []
            for i, (doc_text, metadata, distance) in enumerate(zip(
                results["documents"], 
                results["metadatas"], 
                results["distances"]
            )):
                documents.append(
                    Document(
                        page_content=doc_text,
                        metadata={
                            **metadata,
                            "similarity_score": 1 - distance,  # Convert distance to similarity
                            "retrieved_at": datetime.now().isoformat()
                        }
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
        """Initialize RAG components"""
        try:
            # Initialize LLM
            if settings.OPENAI_API_KEY:
                self.llm = ChatOpenAI(
                    model=settings.OPENAI_MODEL,
                    openai_api_key=settings.OPENAI_API_KEY,
                    openai_api_base=settings.OPENAI_BASE_URL,
                    temperature=0.1
                )
            else:
                logger.warning("OpenAI API key not configured. Using mock responses.")
                self.llm = None
            
            # Initialize retriever
            self.retriever = CustomRetriever(vector_store, top_k=5)
            
            # Create prompt template
            prompt_template = """
            You are a helpful assistant for Kwekwe Polytechnic in Zimbabwe. 
            Use the following context to answer the user's question accurately and professionally.
            
            Context:
            {context}
            
            Question: {question}
            
            Instructions:
            1. Base your answer only on the provided context
            2. If the context doesn't contain the answer, say "I don't have enough information to answer that question"
            3. Be specific about programs, requirements, and fees
            4. Mention department heads when relevant
            5. Include currency information (USD/ZiG) for fee-related questions
            6. Be helpful and professional
            
            Answer:
            """
            
            PROMPT = PromptTemplate(
                template=prompt_template,
                input_variables=["context", "question"]
            )
            
            # Initialize QA chain if LLM is available
            if self.llm:
                self.qa_chain = RetrievalQA.from_chain_type(
                    llm=self.llm,
                    chain_type="stuff",
                    retriever=self.retriever,
                    chain_type_kwargs={"prompt": PROMPT},
                    return_source_documents=True
                )
            
            logger.info("RAG engine initialized successfully")
            
        except Exception as e:
            logger.error(f"Error initializing RAG engine: {str(e)}")
    
    async def query(self, question: str, session_id: Optional[str] = None) -> Dict[str, Any]:
        """Query the RAG engine"""
        try:
            if not self.qa_chain:
                # Fallback to simple retrieval without LLM
                return await self._simple_query(question)
            
            # Use LangChain QA chain
            result = await asyncio.get_event_loop().run_in_executor(
                None, 
                self.qa_chain, 
                {"query": question}
            )
            
            # Extract source documents
            source_docs = []
            if "source_documents" in result:
                for doc in result["source_documents"]:
                    source_docs.append({
                        "content": doc.page_content[:200] + "..." if len(doc.page_content) > 200 else doc.page_content,
                        "metadata": doc.metadata,
                        "similarity_score": doc.metadata.get("similarity_score", 0)
                    })
            
            return {
                "answer": result["result"],
                "sources": source_docs,
                "session_id": session_id,
                "timestamp": datetime.now().isoformat(),
                "query_type": "rag"
            }
            
        except Exception as e:
            logger.error(f"Error in RAG query: {str(e)}")
            return await self._simple_query(question)
    
    async def _simple_query(self, question: str) -> Dict[str, Any]:
        """Simple query without LLM (fallback)"""
        try:
            # Retrieve relevant documents
            docs = self.retriever._get_relevant_documents(question)
            
            if not docs:
                return {
                    "answer": "I don't have enough information to answer that question. Please contact the admissions office for more details.",
                    "sources": [],
                    "timestamp": datetime.now().isoformat(),
                    "query_type": "simple"
                }
            
            # Simple answer based on retrieved documents
            best_doc = max(docs, key=lambda x: x.metadata.get("similarity_score", 0))
            
            answer = f"Based on the available information: {best_doc.page_content[:300]}..."
            
            source_docs = []
            for doc in docs[:3]:  # Top 3 sources
                source_docs.append({
                    "content": doc.page_content[:200] + "..." if len(doc.page_content) > 200 else doc.page_content,
                    "metadata": doc.metadata,
                    "similarity_score": doc.metadata.get("similarity_score", 0)
                })
            
            return {
                "answer": answer,
                "sources": source_docs,
                "timestamp": datetime.now().isoformat(),
                "query_type": "simple"
            }
            
        except Exception as e:
            logger.error(f"Error in simple query: {str(e)}")
            return {
                "answer": "I'm experiencing technical difficulties. Please try again later or contact the support team.",
                "sources": [],
                "timestamp": datetime.now().isoformat(),
                "query_type": "error"
            }
    
    async def health_check(self) -> Dict[str, Any]:
        """Check RAG engine health"""
        try:
            stats = vector_store.get_collection_stats()
            
            return {
                "status": "healthy",
                "vector_store_stats": stats,
                "llm_configured": self.llm is not None,
                "retriever_configured": self.retriever is not None,
                "timestamp": datetime.now().isoformat()
            }
            
        except Exception as e:
            logger.error(f"Health check failed: {str(e)}")
            return {
                "status": "unhealthy",
                "error": str(e),
                "timestamp": datetime.now().isoformat()
            }


# Global RAG engine instance
rag_engine = RAGEngine()
