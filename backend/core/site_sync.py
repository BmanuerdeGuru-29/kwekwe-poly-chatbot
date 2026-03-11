from collections import deque
import hashlib
from io import BytesIO
from typing import Any, Deque, Dict, List, Optional, Set, Tuple
from urllib.parse import urljoin, urlparse

import httpx
import PyPDF2
from bs4 import BeautifulSoup

from backend.config.settings import settings
from backend.core.vector_store import vector_store


class WebsiteSyncService:
    """Fetch and index official Kwekwe Polytechnic website content."""

    def __init__(self):
        self.default_seeds = [seed.strip() for seed in settings.WEBSITE_SYNC_SEEDS.split(",") if seed.strip()]
        self.allowed_domains = {
            "www.kwekwepoly.ac.zw",
            "kwekwepoly.ac.zw",
            "apply.kwekwepoly.ac.zw",
            "elearning.kwekwepoly.ac.zw",
        }

    async def sync(
        self,
        seeds: Optional[List[str]] = None,
        max_pages: Optional[int] = None,
        max_depth: int = 1,
    ) -> Dict[str, Any]:
        queue: Deque[Tuple[str, int]] = deque((url, 0) for url in (seeds or self.default_seeds))
        visited: Set[str] = set()
        documents: List[Dict[str, Any]] = []
        indexed_urls: List[str] = []
        page_limit = max_pages or settings.MAX_WEBSITE_SYNC_PAGES

        async with httpx.AsyncClient(timeout=20, follow_redirects=True) as client:
            while queue and len(indexed_urls) < page_limit:
                url, depth = queue.popleft()
                normalized_url = self._normalize_url(url)
                if not normalized_url or normalized_url in visited:
                    continue
                visited.add(normalized_url)

                if not self._is_allowed(normalized_url):
                    continue

                try:
                    response = await client.get(normalized_url)
                    response.raise_for_status()
                except Exception:
                    continue

                content_type = response.headers.get("content-type", "").lower()
                if "application/pdf" in content_type or normalized_url.lower().endswith(".pdf"):
                    text = self._extract_pdf_text(response.content)
                    if text:
                        indexed_urls.append(normalized_url)
                        documents.extend(self._build_documents(normalized_url, text, "pdf"))
                    continue

                if "text/html" not in content_type and "application/xhtml+xml" not in content_type:
                    continue

                html = response.text
                page_text, discovered_links, title = self._extract_page_content(normalized_url, html)
                if page_text:
                    indexed_urls.append(normalized_url)
                    documents.extend(self._build_documents(normalized_url, page_text, "html", title))

                if depth < max_depth:
                    for link in discovered_links:
                        if len(indexed_urls) + len(queue) >= page_limit * 3:
                            break
                        queue.append((link, depth + 1))

        if documents:
            vector_store.add_documents(documents)

        return {
            "status": "success",
            "indexed_pages": len(indexed_urls),
            "documents_created": len(documents),
            "sources": indexed_urls,
        }

    def _extract_page_content(self, base_url: str, html: str) -> Tuple[str, List[str], str]:
        soup = BeautifulSoup(html, "html.parser")
        for tag in soup(["script", "style", "noscript"]):
            tag.decompose()

        title = soup.title.string.strip() if soup.title and soup.title.string else base_url
        text_fragments = []

        for selector in ["main", "article", ".content", "#content", "body"]:
            nodes = soup.select(selector)
            if nodes:
                for node in nodes:
                    text = node.get_text(" ", strip=True)
                    if text and len(text) > 80:
                        text_fragments.append(text)
                if text_fragments:
                    break

        text = "\n\n".join(dict.fromkeys(text_fragments))
        links: List[str] = []
        for anchor in soup.find_all("a", href=True):
            href = anchor.get("href", "").strip()
            if not href or href.startswith("#") or href.startswith("mailto:") or href.startswith("tel:"):
                continue
            absolute = self._normalize_url(urljoin(base_url, href))
            if absolute and self._is_allowed(absolute):
                links.append(absolute)

        return text, list(dict.fromkeys(links)), title

    def _extract_pdf_text(self, content: bytes) -> str:
        try:
            reader = PyPDF2.PdfReader(BytesIO(content))
            pages = []
            for page in reader.pages:
                pages.append(page.extract_text() or "")
            return "\n".join(pages).strip()
        except Exception:
            return ""

    def _build_documents(
        self,
        source_url: str,
        content: str,
        page_type: str,
        title: Optional[str] = None,
    ) -> List[Dict[str, Any]]:
        documents: List[Dict[str, Any]] = []
        chunks = self._chunk_text(content)

        for chunk_index, chunk in enumerate(chunks):
            digest = hashlib.sha1(f"{source_url}:{chunk_index}".encode("utf-8")).hexdigest()[:16]
            doc_id = f"site_{digest}"
            documents.append(
                {
                    "id": doc_id,
                    "text": chunk,
                    "metadata": {
                        "source": source_url,
                        "source_url": source_url,
                        "filename": title or source_url,
                        "file_type": page_type,
                        "category": self._categorize_url(source_url),
                        "chunk_index": chunk_index,
                        "chunk_count": len(chunks),
                    },
                }
            )

        return documents

    @staticmethod
    def _chunk_text(text: str, chunk_size: int = 1200, overlap: int = 200) -> List[str]:
        normalized = " ".join(text.split())
        if not normalized:
            return []
        if len(normalized) <= chunk_size:
            return [normalized]

        chunks: List[str] = []
        start = 0
        while start < len(normalized):
            end = min(start + chunk_size, len(normalized))
            if end < len(normalized):
                split = normalized.rfind(". ", start, end)
                if split > start + overlap:
                    end = split + 1
            chunk = normalized[start:end].strip()
            if chunk:
                chunks.append(chunk)
            if end >= len(normalized):
                break
            start = max(end - overlap, start + 1)
        return chunks

    def _is_allowed(self, url: str) -> bool:
        parsed = urlparse(url)
        return parsed.scheme in {"http", "https"} and parsed.netloc in self.allowed_domains

    @staticmethod
    def _normalize_url(url: str) -> Optional[str]:
        if not url:
            return None
        parsed = urlparse(url)
        if parsed.scheme not in {"http", "https"} or not parsed.netloc:
            return None
        normalized = parsed._replace(fragment="", query="")
        return normalized.geturl().rstrip("/")

    @staticmethod
    def _categorize_url(url: str) -> str:
        lower = url.lower()
        if "portal" in lower or "elearning" in lower:
            return "student_services"
        if "hostel" in lower or "accommodation" in lower:
            return "accommodation"
        if "admission" in lower or "apply" in lower or "intake" in lower:
            return "admissions"
        if "fee" in lower or "payment" in lower:
            return "fees"
        if "commerce" in lower:
            return "commerce"
        if "engineering" in lower:
            return "engineering"
        return "website"


website_sync_service = WebsiteSyncService()
