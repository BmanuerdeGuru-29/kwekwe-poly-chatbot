#!/usr/bin/env python3
"""Build the local knowledge index for the PHP application.

This helper is intentionally standard-library only. The live application does
not need Python, but this script makes it easy to regenerate the checked-in
search index from Markdown or text sources.
"""

from __future__ import annotations

import json
import re
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Iterable


ROOT = Path(__file__).resolve().parent.parent
SOURCE_DIRS = [ROOT / "data" / "sample_docs", ROOT / "storage" / "uploads"]
OUTPUT = ROOT / "data" / "knowledge" / "index.json"
STOPWORDS = {
    "a", "an", "and", "are", "at", "be", "by", "can", "do", "for", "from", "how", "i",
    "if", "in", "is", "it", "me", "my", "of", "on", "or", "please", "tell", "that",
    "the", "their", "there", "this", "to", "what", "when", "where", "which", "who",
    "why", "with", "you", "your", "kwekwe", "poly", "polytechnic",
}


@dataclass
class Section:
    heading: str
    content: str


def slugify(text: str) -> str:
    text = normalize(text)
    text = re.sub(r"[^a-z0-9]+", "-", text).strip("-")
    return text or "item"


def normalize(text: str) -> str:
    text = text.lower()
    text = re.sub(r"[^\w\s]+", " ", text, flags=re.UNICODE)
    text = re.sub(r"\s+", " ", text, flags=re.UNICODE)
    return text.strip()


def excerpt(text: str, limit: int = 220) -> str:
    text = re.sub(r"\s+", " ", text, flags=re.UNICODE).strip()
    if len(text) <= limit:
        return text
    return text[: limit - 3].rstrip() + "..."


def tokenize(text: str) -> list[str]:
    seen: set[str] = set()
    tokens: list[str] = []
    for token in normalize(text).split():
        if len(token) < 3 and token not in {"it", "nd", "nc", "hnd"}:
            continue
        if token in STOPWORDS:
            continue
        if token not in seen:
            seen.add(token)
            tokens.append(token)
    return tokens


def category_for(filename: str) -> str:
    filename = filename.lower()
    if "fee" in filename or "admission" in filename:
        return "fees"
    if "engineering" in filename:
        return "engineering"
    if "commerce" in filename:
        return "commerce"
    if "applied" in filename:
        return "applied_sciences"
    if "btech" in filename:
        return "btech"
    if "ace" in filename:
        return "ace"
    if "hexco" in filename or "exam" in filename:
        return "exams"
    return "general"


def source_url_for(category: str) -> str:
    if category == "fees":
        return "https://apply.kwekwepoly.ac.zw/"
    return "https://www.kwekwepoly.ac.zw/"


def iter_source_files() -> Iterable[Path]:
    for directory in SOURCE_DIRS:
        if not directory.exists():
            continue
        for path in sorted(directory.rglob("*")):
            if path.is_file() and path.suffix.lower() in {".md", ".txt"}:
                yield path


def parse_sections(content: str) -> list[Section]:
    sections: list[Section] = []
    heading = "Overview"
    buffer: list[str] = []

    for line in content.splitlines():
        match = re.match(r"^#{1,3}\s+(.+)$", line.strip())
        if match:
            joined = "\n".join(buffer).strip()
            if len(joined) >= 40:
                sections.append(Section(heading=heading, content=joined))
            heading = match.group(1).strip()
            buffer = []
            continue
        buffer.append(line)

    joined = "\n".join(buffer).strip()
    if len(joined) >= 40:
        sections.append(Section(heading=heading, content=joined))

    return sections


def build_index() -> dict:
    documents: list[dict] = []
    chunks: list[dict] = []

    for path in iter_source_files():
        content = path.read_text(encoding="utf-8").strip()
        if not content:
            continue

        title_match = re.search(r"^#\s+(.+)$", content, flags=re.MULTILINE)
        title = title_match.group(1).strip() if title_match else path.stem.replace("_", " ").replace("-", " ").title()
        category = category_for(path.name)
        document_id = slugify(path.stem)
        relative_path = path.relative_to(ROOT).as_posix()

        documents.append(
            {
                "id": document_id,
                "title": title,
                "category": category,
                "source_path": relative_path,
                "source_url": source_url_for(category),
            }
        )

        for index, section in enumerate(parse_sections(content), start=1):
            chunks.append(
                {
                    "id": f"{document_id}-{index}",
                    "document_id": document_id,
                    "title": title,
                    "category": category,
                    "heading": section.heading,
                    "content": section.content,
                    "excerpt": excerpt(section.content),
                    "tokens": tokenize(f"{title} {section.heading} {section.content}"),
                    "metadata": {
                        "filename": path.name,
                        "source_path": relative_path,
                        "source_url": source_url_for(category),
                    },
                }
            )

    return {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "document_count": len(documents),
        "chunk_count": len(chunks),
        "documents": documents,
        "chunks": chunks,
    }


def main() -> None:
    OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    index = build_index()
    OUTPUT.write_text(json.dumps(index, indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"Wrote {OUTPUT} with {index['document_count']} documents and {index['chunk_count']} chunks.")


if __name__ == "__main__":
    main()
