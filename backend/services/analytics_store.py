import asyncio
import json
from collections import Counter
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List, Optional

from backend.config.settings import settings


class AnalyticsStore:
    """Simple JSONL-backed analytics and feedback storage."""

    def __init__(self):
        self.analytics_path = Path(settings.ANALYTICS_FILE)
        self.feedback_path = Path(settings.FEEDBACK_FILE)
        self.analytics_path.parent.mkdir(parents=True, exist_ok=True)
        self.feedback_path.parent.mkdir(parents=True, exist_ok=True)

    async def record_chat_event(self, payload: Dict[str, Any]) -> None:
        await self._append_jsonl(self.analytics_path, payload)

    async def record_feedback(self, payload: Dict[str, Any]) -> None:
        await self._append_jsonl(self.feedback_path, payload)

    async def list_feedback(self, limit: int = 50) -> List[Dict[str, Any]]:
        items = await self._read_jsonl(self.feedback_path)
        return items[-limit:]

    async def get_summary(self) -> Dict[str, Any]:
        events = await self._read_jsonl(self.analytics_path)
        feedback = await self._read_jsonl(self.feedback_path)

        intents = Counter(event.get("intent", "unknown") for event in events)
        languages = Counter(event.get("language", "en") for event in events)
        handoffs = Counter(
            event.get("handoff", {}).get("office", "none")
            for event in events
            if event.get("handoff")
        )

        confidence_scores = [
            event.get("confidence", {}).get("score")
            for event in events
            if isinstance(event.get("confidence", {}).get("score"), (int, float))
        ]
        positive_feedback = sum(1 for item in feedback if item.get("helpful") is True)
        negative_feedback = sum(1 for item in feedback if item.get("helpful") is False)

        return {
            "total_queries": len(events),
            "total_feedback_entries": len(feedback),
            "positive_feedback": positive_feedback,
            "negative_feedback": negative_feedback,
            "queries_by_intent": dict(intents.most_common()),
            "queries_by_language": dict(languages.most_common()),
            "handoffs_by_office": dict(handoffs.most_common()),
            "low_confidence_queries": sum(
                1 for event in events if event.get("confidence", {}).get("label") == "low"
            ),
            "average_confidence": (
                round(sum(confidence_scores) / len(confidence_scores), 3)
                if confidence_scores
                else None
            ),
            "last_updated": datetime.utcnow().isoformat() + "Z",
        }

    async def _append_jsonl(self, path: Path, payload: Dict[str, Any]) -> None:
        path.parent.mkdir(parents=True, exist_ok=True)
        line = json.dumps(payload, ensure_ascii=True)
        await asyncio.to_thread(self._append_line, path, line)

    async def _read_jsonl(self, path: Path) -> List[Dict[str, Any]]:
        if not path.exists():
            return []

        return await asyncio.to_thread(self._read_lines, path)

    @staticmethod
    def _append_line(path: Path, line: str) -> None:
        with path.open("a", encoding="utf-8") as handle:
            handle.write(line + "\n")

    @staticmethod
    def _read_lines(path: Path) -> List[Dict[str, Any]]:
        items: List[Dict[str, Any]] = []
        with path.open("r", encoding="utf-8") as handle:
            for raw_line in handle:
                line = raw_line.strip()
                if not line:
                    continue
                try:
                    items.append(json.loads(line))
                except json.JSONDecodeError:
                    continue
        return items


analytics_store = AnalyticsStore()
