# Kwekwe Polytechnic Floating Widget Integration

## Overview

This widget is designed to mirror the compact floating assistant pattern already visible on the Kwekwe Polytechnic ICT page:

- floating launcher in the bottom-right corner
- teaser prompt that says "Try our new chatbot!"
- official assistant header with Kwekwe Polytechnic branding
- quick actions for `Fees`, `Courses`, `Staff`, and `Apply`
- same navy, gold, and cream palette used across the Kwekwe Polytechnic web presence

## Best Integration Method

Add one script tag to the shared website footer, template, or include file that is loaded by every page.

```html
<script
  src="https://your-chatbot-host/embed.js"
  data-api-url="https://your-chatbot-host"
  data-kwekwe-widget
  defer
></script>
```

If the chatbot backend is running on the same host as the script, that single line is enough.

## Local Example

```html
<script
  src="http://localhost:8000/embed.js"
  data-api-url="http://localhost:8000"
  data-kwekwe-widget
  defer
></script>
```

## What the Loader Does

`embed.js` automatically loads:

- `kwekwe-chat-widget.css`
- `kwekwe-chat-widget.js`

You do not need to add separate CSS or JavaScript includes when using the embed loader.

## Website-Wide Placement

To make the widget appear on every page, place the script in one shared layout file.

Common examples:

- PHP website: shared `footer.php`, `layout.php`, or reusable include
- WordPress: global footer or theme template
- plain HTML site: common page template before `</body>`

## Asset Endpoints

When served through the chatbot backend, these routes are available:

- `/embed.js`
- `/kwekwe-chat-widget.js`
- `/kwekwe-chat-widget.css`
- `/logo.png`

## Optional Customization

You can override default labels with data attributes:

```html
<script
  src="https://your-chatbot-host/embed.js"
  data-api-url="https://your-chatbot-host"
  data-launcher-label="Try our new chatbot!"
  data-title="Kwekwe Poly AI"
  data-subtitle="Official Assistant"
  data-greeting="Hi, I am Kwekwe Polytechnic's AI assistant. Ask me about fees, courses, staff contacts, applications, accommodation, or student support."
  data-footer-text="Kwekwe Polytechnic Official AI Assistant"
  data-kwekwe-widget
  defer
></script>
```

## Expected Backend API

The widget sends chat requests to:

- `/api/v1/chat/query`

It expects the chatbot backend to be reachable at the `data-api-url` you provide.

## Deployment Checklist

- serve the chatbot backend over HTTPS in production
- add the embed script once in the site-wide template
- verify the widget opens on desktop and mobile
- confirm the launcher appears on every page
- test the quick chips: `Fees`, `Courses`, `Staff`, and `Apply`
- verify the chatbot can reach the live `/api/v1/chat/query` endpoint

## Demo Page

Use [kwekwe-demo.html](/Users/honor/Documents/GitHub/kwekwe-poly%20chatbot/kwekwe-demo.html) to preview the widget locally.
