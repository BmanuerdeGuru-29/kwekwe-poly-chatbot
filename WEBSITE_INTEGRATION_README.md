# Kwekwe Polytechnic Website Integration Guide

The chatbot is now a PHP-only web application. Deploy it anywhere PHP is available, then embed the floating widget into the main Kwekwe Polytechnic website.

## Deploy

1. Copy `.env.example` to `.env`.
2. Set `ADMIN_KEY`.
3. Optionally run:

```powershell
python scripts\build_knowledge_index.py
```

4. Serve the project with PHP.

```powershell
php -S 127.0.0.1:8000
```

## Verify

Check these URLs:

- `/index.php`
- `/admin.php`
- `/embed.js`
- `/api/health.php`

## Embed Snippet

```html
<script
  src="https://your-php-chat-domain.example/embed.js"
  data-api-url="https://your-php-chat-domain.example"
  data-title="Kwekwe Poly AI"
  data-subtitle="Official Assistant"
  data-launcher-label="Chat with Kwekwe Poly"
  data-greeting="Hello! 👋 I'm your friendly AI assistant from Kwekwe Polytechnic. I'm here to help with any questions you might have about our programs, admissions, fees, or student life. What can I help you with today?"
  data-footer-text="Kwekwe Polytechnic Official AI Assistant<br>Powered by IT Unit &copy; 2025 Kwekwe Polytechnic"
  data-logo-url="https://your-php-chat-domain.example/logo.png"
  data-kwekwe-widget
  defer
></script>
```

## Admin Console

Use `/admin.php` to:

- sign in with the configured admin key
- upload local Markdown or text documents
- rebuild the index
- inspect analytics and feedback

## Notes

- Runtime does not require Python.
- Runtime does not require Redis, a database server, or external AI APIs.
- Python remains available only as an offline helper.
