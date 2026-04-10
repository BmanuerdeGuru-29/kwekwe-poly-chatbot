# System Audit

## Current State

The project has been rewritten as a PHP-first application.

## Runtime Stack

- PHP pages: `index.php`, `admin.php`
- PHP APIs: `api/*.php`, `api/admin/*.php`
- Local storage: JSON and JSONL files under `data/knowledge` and `storage`
- Widget assets: `embed.js`, `kwekwe-chat-widget.js`, `kwekwe-chat-widget.css`

## No Longer Required

- FastAPI
- React/Vite
- Redis
- ChromaDB
- Docker-based service orchestration
- External AI APIs

## Python Role

Python remains only as an offline helper for regenerating `data/knowledge/index.json` via:

```powershell
python scripts\build_knowledge_index.py
```

## Operational Notes

- The live app can run on plain PHP hosting.
- Admin auth uses native PHP sessions.
- Analytics and feedback are stored as flat files.
- Knowledge uploads are local Markdown or text files and can be indexed from the admin console.
