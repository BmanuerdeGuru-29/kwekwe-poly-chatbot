import pytest
import asyncio
from backend.core.rag_engine import rag_engine
from backend.core.document_ingestion import document_ingestion


@pytest.mark.asyncio
async def test_document_ingestion():
    """Test document ingestion"""
    # Create sample documents
    success = await document_ingestion.create_sample_documents()
    assert success is True


@pytest.mark.asyncio
async def test_rag_query():
    """Test RAG query functionality"""
    # Ensure documents are ingested first
    await document_ingestion.create_sample_documents()
    
    # Test query
    result = await rag_engine.query("What are the entry requirements for Engineering?")
    
    assert "answer" in result
    assert len(result["answer"]) > 0
    assert "timestamp" in result


@pytest.mark.asyncio
async def test_rag_health_check():
    """Test RAG engine health check"""
    health = await rag_engine.health_check()
    
    assert "status" in health
    assert "vector_store_stats" in health


@pytest.mark.asyncio
async def test_multiple_queries():
    """Test multiple concurrent queries"""
    await document_ingestion.create_sample_documents()
    
    queries = [
        "What are the entry requirements for Engineering?",
        "How much are the tuition fees?",
        "What payment methods are accepted?",
        "Who heads the Automotive Engineering department?"
    ]
    
    # Run queries concurrently
    tasks = [rag_engine.query(query) for query in queries]
    results = await asyncio.gather(*tasks)
    
    assert len(results) == len(queries)
    for result in results:
        assert "answer" in result
        assert len(result["answer"]) > 0
