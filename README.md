# Kwekwe Polytechnic Chatbot

A rebuilt Kwekwe Polytechnic chatbot that now runs as a PHP-first application with no external server-side services. It serves the public assistant, admin console, search API, feedback capture, analytics, and floating website widget from plain PHP files and local disk storage.

## What Changed

- The old FastAPI, React, Redis, and vector-database stack has been replaced.
- Live runtime is now PHP only.
- Python is optional and used only as an offline helper to rebuild the local knowledge index.
- Answers come from local Markdown and text knowledge files, not from external APIs or hosted AI services.

## Architecture

The application is intentionally simple:

- `index.php`: public assistant page
- `admin.php`: admin console with session-based sign-in
- `api/*.php`: chat, search, feedback, and health endpoints
- `api/admin/*.php`: analytics, knowledge, rebuild, upload, login, logout
- `data/sample_docs`: checked-in source knowledge
- `data/knowledge/index.json`: generated searchable index
- `storage/analytics` and `storage/feedback`: flat-file runtime data
- `embed.js` and `kwekwe-chat-widget.js`: portable website widget assets

## Requirements

- PHP 8.1+ recommended
- Python 3.11+ optional, only if you want to regenerate `data/knowledge/index.json` with the helper script

## Quick Start

1. Copy the environment file.

```powershell
Copy-Item .env.example .env
```

2. Set a real admin key in `.env`.

```env
ADMIN_KEY=choose-a-strong-admin-key
```

3. Optionally rebuild the knowledge index with Python.

```powershell
python scripts\build_knowledge_index.py
```

4. Start the PHP app.

```powershell
php -S 127.0.0.1:8000
```

5. Open:

- `http://127.0.0.1:8000/index.php`
- `http://127.0.0.1:8000/admin.php`
- `http://127.0.0.1:8000/kwekwe-demo.html`

## Environment

Supported environment variables:

```env
APP_URL=http://127.0.0.1:8000
APP_TIMEZONE=Africa/Johannesburg
ADMIN_KEY=change-this-admin-key
```

## APIs

- `POST /api/chat.php`
- `GET|POST /api/search.php`
- `POST /api/feedback.php`
- `GET /api/health.php`
- `POST /api/admin/login.php`
- `POST /api/admin/logout.php`
- `GET /api/admin/session.php`
- `GET /api/admin/analytics.php`
- `GET /api/admin/feedback.php`
- `GET /api/admin/knowledge.php`
- `POST /api/admin/rebuild.php`
- `POST /api/admin/upload.php`

## Widget Embed

Use the floating widget on any site with:

```html
<script
  src="https://your-php-chat-domain.example/embed.js"
  data-api-url="https://your-php-chat-domain.example"
  data-kwekwe-widget
  defer
></script>
```

There is also a reusable PHP include in `kwekwe-widget-include.php`.

## Knowledge Sources

The app indexes:

- `data/sample_docs/*.md`
- `storage/uploads/*.md`
- `storage/uploads/*.txt`

The admin console can upload new local documents and rebuild the index without needing Python.

## Python Helper

Python is not needed at runtime. The helper exists only for offline regeneration of the checked-in search index:

```powershell
python scripts\build_knowledge_index.py
```

It uses the Python standard library only.

## Storage

Runtime data is stored locally:

- analytics: `storage/analytics/chat_events.jsonl`
- feedback: `storage/feedback/feedback.jsonl`
- uploads: `storage/uploads`

## License

This project is licensed under the MIT License. See `LICENSE`.
