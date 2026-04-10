# Kwekwe Polytechnic Floating Widget Integration

This project now serves the widget directly from the PHP application. You do not need a Python backend, Node frontend, Redis instance, or vector database.

## Basic Embed

Paste this before `</body>` on the target website:

```html
<script
  src="https://your-php-chat-domain.example/embed.js"
  data-api-url="https://your-php-chat-domain.example"
  data-kwekwe-widget
  defer
></script>
```

## What Gets Loaded

The loader automatically pulls in:

- `kwekwe-chat-widget.css`
- `kwekwe-chat-widget.js`

## Expected API

The widget talks to:

- `POST /api/chat.php`

## Recommended Hosting Pattern

- Host the PHP chatbot on its own domain or subdomain.
- Keep the main Kwekwe Polytechnic website unchanged except for the embed script.
- Serve the widget and API over HTTPS in production.

## Useful Endpoints

- `/embed.js`
- `/kwekwe-chat-widget.js`
- `/kwekwe-chat-widget.css`
- `/logo.png`
- `/api/chat.php`
- `/api/health.php`

## Local Test

Run:

```powershell
php -S 127.0.0.1:8000
```

Then open [kwekwe-demo.html](/Users/honor/Documents/GitHub/kwekwe-poly-chatbot/kwekwe-demo.html).
