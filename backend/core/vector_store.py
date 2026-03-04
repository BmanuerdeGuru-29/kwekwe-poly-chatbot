import chromadb
from chromadb.config import Settings as ChromaSettings
from sentence_transformers import SentenceTransformer
from typing import List, Dict, Any, Optional
import logging
from backend.config.settings import settings

logger = logging.getLogger(__name__)


class VectorStore:
    def __init__(self):
        self.client = chromadb.PersistentClient(
            path=settings.CHROMA_DB_PATH,
            settings=ChromaSettings(
                anonymized_telemetry=False,
                allow_reset=True
            )
        )
        self.embedding_model = SentenceTransformer(settings.EMBEDDING_MODEL)
        self.collection = None
        self._initialize_collection()
    
    def _initialize_collection(self):
        """Initialize or get the collection"""
        try:
            self.collection = self.client.get_collection("kwekwe_polytechnic")
            logger.info("Connected to existing vector collection")
        except Exception:
            self.collection = self.client.create_collection(
                name="kwekwe_polytechnic",
                metadata={"description": "Kwekwe Polytechnic institutional knowledge"}
            )
            logger.info("Created new vector collection")
    
    def add_documents(self, documents: List[Dict[str, Any]]):
        """Add documents to the vector store"""
        try:
            texts = [doc["text"] for doc in documents]
            metadatas = [doc.get("metadata", {}) for doc in documents]
            ids = [doc.get("id", f"doc_{i}") for i, doc in enumerate(documents)]
            
            # Generate embeddings
            embeddings = self.embedding_model.encode(texts).tolist()
            
            # Add to collection
            self.collection.add(
                documents=texts,
                metadatas=metadatas,
                ids=ids,
                embeddings=embeddings
            )
            
            logger.info(f"Added {len(documents)} documents to vector store")
            return True
            
        except Exception as e:
            logger.error(f"Error adding documents: {str(e)}")
            return False
    
    def query(self, query_text: str, n_results: int = 5) -> Dict[str, Any]:
        """Query the vector store"""
        try:
            # Generate query embedding
            query_embedding = self.embedding_model.encode([query_text]).tolist()
            
            # Search collection
            results = self.collection.query(
                query_embeddings=query_embedding,
                n_results=n_results,
                include=["documents", "metadatas", "distances"]
            )
            
            return {
                "documents": results["documents"][0] if results["documents"] else [],
                "metadatas": results["metadatas"][0] if results["metadatas"] else [],
                "distances": results["distances"][0] if results["distances"] else []
            }
            
        except Exception as e:
            logger.error(f"Error querying vector store: {str(e)}")
            return {"documents": [], "metadatas": [], "distances": []}
    
    def delete_collection(self):
        """Delete the entire collection"""
        try:
            self.client.delete_collection("kwekwe_polytechnic")
            logger.info("Vector collection deleted")
            return True
        except Exception as e:
            logger.error(f"Error deleting collection: {str(e)}")
            return False
    
    def get_collection_stats(self) -> Dict[str, Any]:
        """Get collection statistics"""
        try:
            count = self.collection.count()
            return {
                "document_count": count,
                "collection_name": "kwekwe_polytechnic",
                "embedding_model": settings.EMBEDDING_MODEL
            }
        except Exception as e:
            logger.error(f"Error getting collection stats: {str(e)}")
            return {}


# Global vector store instance
vector_store = VectorStore()
