import chromadb
from chromadb.config import Settings as ChromaSettings
from sentence_transformers import SentenceTransformer
import hashlib
import re
import numpy as np
from typing import List, Dict, Any, Optional
import logging
from backend.config.settings import settings

logger = logging.getLogger(__name__)


class LocalHashEmbeddingModel:
    """Deterministic local fallback embeddings for offline environments."""

    token_pattern = re.compile(r"[a-z0-9]+", re.IGNORECASE)

    def __init__(self, dimension: int = 384):
        self.dimension = dimension

    def encode(self, texts):
        if isinstance(texts, str):
            texts = [texts]

        vectors = [self._encode_single(text or "") for text in texts]
        return np.vstack(vectors).astype("float32")

    def _encode_single(self, text: str) -> np.ndarray:
        vector = np.zeros(self.dimension, dtype=np.float32)
        tokens = self._tokenize(text)

        if not tokens:
            return vector

        for index, token in enumerate(tokens):
            self._accumulate_feature(vector, token, base_weight=1.0)
            if index < len(tokens) - 1:
                self._accumulate_feature(vector, f"{token}_{tokens[index + 1]}", base_weight=0.65)

        norm = np.linalg.norm(vector)
        if norm > 0:
            vector /= norm

        return vector

    def _accumulate_feature(self, vector: np.ndarray, token: str, base_weight: float) -> None:
        digest = hashlib.sha1(token.encode("utf-8")).digest()

        for offset in (0, 4, 8):
            chunk = digest[offset:offset + 4]
            feature_index = int.from_bytes(chunk[:2], "big") % self.dimension
            sign = 1.0 if chunk[2] % 2 == 0 else -1.0
            weight = base_weight * (1.0 + (chunk[3] / 255.0))
            vector[feature_index] += sign * weight

    def _tokenize(self, text: str) -> List[str]:
        lowered = text.lower()
        return self.token_pattern.findall(lowered)


class VectorStore:
    def __init__(self):
        self.client = chromadb.PersistentClient(
            path=settings.CHROMA_DB_PATH,
            settings=ChromaSettings(
                anonymized_telemetry=False,
                allow_reset=True
            )
        )
        self.embedding_backend = "sentence-transformers"
        self.embedding_model_name = settings.EMBEDDING_MODEL
        self.embedding_model = self._load_embedding_model()
        self.collection_name = self._resolve_collection_name()
        self.collection = None
        self._initialize_collection()

    def _load_embedding_model(self):
        """Load a cached/local sentence-transformer model, or fall back offline."""
        requested_model = settings.EMBEDDING_MODEL

        try:
            model = SentenceTransformer(
                requested_model,
                cache_folder=settings.EMBEDDING_CACHE_DIR,
                local_files_only=True,
            )
            self.embedding_backend = "sentence-transformers"
            logger.info("Loaded embedding model '%s' from local cache or local path", requested_model)
            return model
        except Exception as local_error:
            logger.warning(
                "Local embedding model '%s' is unavailable: %s",
                requested_model,
                local_error,
            )

        if settings.EMBEDDING_DOWNLOAD_ON_MISS:
            try:
                model = SentenceTransformer(
                    requested_model,
                    cache_folder=settings.EMBEDDING_CACHE_DIR,
                    local_files_only=False,
                )
                self.embedding_backend = "sentence-transformers"
                logger.info("Downloaded embedding model '%s' from Hugging Face", requested_model)
                return model
            except Exception as remote_error:
                logger.warning(
                    "Could not download embedding model '%s': %s",
                    requested_model,
                    remote_error,
                )

        self.embedding_backend = "hashing-fallback"
        self.embedding_model_name = f"{requested_model} (offline fallback)"
        logger.warning(
            "Using local hashing fallback embeddings. Retrieval quality may be lower, "
            "but the app can run without internet access."
        )
        logger.warning(
            "To use the full sentence-transformer model offline, download '%s' once and set "
            "EMBEDDING_MODEL to the local folder path.",
            requested_model,
        )
        return LocalHashEmbeddingModel(dimension=384)

    def _resolve_collection_name(self) -> str:
        """Keep incompatible embedding spaces in separate Chroma collections."""
        if self.embedding_backend == "hashing-fallback":
            return "kwekwe_polytechnic_hashing"
        return "kwekwe_polytechnic"
    
    def _initialize_collection(self):
        """Initialize or get the collection"""
        try:
            self.collection = self.client.get_collection(self.collection_name)
            logger.info("Connected to existing vector collection '%s'", self.collection_name)
        except Exception:
            self.collection = self.client.create_collection(
                name=self.collection_name,
                metadata={
                    "description": "Kwekwe Polytechnic institutional knowledge",
                    "embedding_backend": self.embedding_backend,
                }
            )
            logger.info("Created new vector collection '%s'", self.collection_name)

    def _encode_texts(self, texts: List[str]) -> List[List[float]]:
        """Generate embeddings in a backend-agnostic way."""
        embeddings = self.embedding_model.encode(texts)
        if hasattr(embeddings, "tolist"):
            return embeddings.tolist()
        return list(embeddings)
    
    def add_documents(self, documents: List[Dict[str, Any]]):
        """Add documents to the vector store"""
        try:
            if not documents:
                return False

            texts = [doc["text"] for doc in documents]
            metadatas = [doc.get("metadata", {}) for doc in documents]
            ids = [doc.get("id", f"doc_{i}") for i, doc in enumerate(documents)]
            
            # Generate embeddings
            embeddings = self._encode_texts(texts)
            
            # Add to collection
            self.collection.upsert(
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
            collection_count = self.collection.count()
            if collection_count == 0:
                return {"documents": [], "metadatas": [], "distances": []}

            # Generate query embedding
            query_embedding = self._encode_texts([query_text])
            
            # Search collection
            results = self.collection.query(
                query_embeddings=query_embedding,
                n_results=min(n_results, collection_count),
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
            self.client.delete_collection(self.collection_name)
            logger.info("Vector collection '%s' deleted", self.collection_name)
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
                "collection_name": self.collection_name,
                "embedding_model": self.embedding_model_name,
                "embedding_backend": self.embedding_backend,
            }
        except Exception as e:
            logger.error(f"Error getting collection stats: {str(e)}")
            return {}


# Global vector store instance
vector_store = VectorStore()
