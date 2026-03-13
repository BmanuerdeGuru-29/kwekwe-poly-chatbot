from pathlib import Path
from typing import Any, Dict, Optional

from backend.config.settings import settings


ENV_FILE_PATH = Path(".env")


def _mask_secret(secret: Optional[str]) -> Optional[str]:
    if not secret:
        return None
    if len(secret) <= 8:
        return "*" * len(secret)
    return f"{secret[:4]}{'*' * (len(secret) - 8)}{secret[-4:]}"


def _read_env_lines() -> list[str]:
    if not ENV_FILE_PATH.exists():
        return []
    return ENV_FILE_PATH.read_text(encoding="utf-8").splitlines()


def _write_env_lines(lines: list[str]) -> None:
    content = "\n".join(lines).rstrip()
    if content:
        content += "\n"
    ENV_FILE_PATH.write_text(content, encoding="utf-8")


def _upsert_env_var(key: str, value: str) -> None:
    lines = _read_env_lines()
    updated = False
    prefix = f"{key}="
    new_lines = []

    for line in lines:
        if line.startswith(prefix):
            new_lines.append(f"{key}={value}")
            updated = True
        else:
            new_lines.append(line)

    if not updated:
        if new_lines and new_lines[-1].strip():
            new_lines.append("")
        new_lines.append(f"{key}={value}")

    _write_env_lines(new_lines)


def _remove_env_var(key: str) -> None:
    prefix = f"{key}="
    new_lines = [line for line in _read_env_lines() if not line.startswith(prefix)]
    _write_env_lines(new_lines)


def get_openai_key_status() -> Dict[str, Any]:
    api_key = settings.OPENAI_API_KEY
    env_lines = _read_env_lines()
    persisted = any(line.startswith("OPENAI_API_KEY=") for line in env_lines)

    return {
        "configured": bool(api_key),
        "masked_key": _mask_secret(api_key),
        "persisted_in_env": persisted,
        "env_file": str(ENV_FILE_PATH.resolve()),
        "model": settings.OPENAI_MODEL,
        "base_url": settings.OPENAI_BASE_URL,
    }


def set_openai_api_key(api_key: str, persist: bool = True) -> Dict[str, Any]:
    cleaned = api_key.strip()
    if not cleaned:
        raise ValueError("OpenAI API key cannot be empty")

    settings.OPENAI_API_KEY = cleaned
    if persist:
        _upsert_env_var("OPENAI_API_KEY", cleaned)

    return get_openai_key_status()


def clear_openai_api_key(persist: bool = True) -> Dict[str, Any]:
    settings.OPENAI_API_KEY = None
    if persist:
        _remove_env_var("OPENAI_API_KEY")

    return get_openai_key_status()
