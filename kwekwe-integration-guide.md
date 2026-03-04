# Kwekwe Polytechnic Chat Widget Integration Guide

## Overview
This guide will help you integrate the Kwekwe Polytechnic chat widget into your existing website. The widget is a standalone, self-contained component that can be easily added to any web page.

## Files Required
1. `kwekwe-chat-widget.css` - Styles for the chat widget
2. `kwekwe-chat-widget.js` - JavaScript functionality
3. Font Awesome (automatically loaded by the widget)

## Quick Integration (5 minutes)

### Step 1: Add CSS to Your Website
Add this CSS link to the `<head>` section of your HTML pages:

```html
<link rel="stylesheet" href="path/to/kwekwe-chat-widget.css">
```

### Step 2: Add JavaScript to Your Website
Add this script before the closing `</body>` tag:

```html
<script src="path/to/kwekwe-chat-widget.js"></script>
```

### Step 3: Done!
The chat widget will automatically appear on your website with a floating chat button in the bottom-right corner.

## Advanced Configuration

### Custom API Endpoint
If your chatbot API is hosted at a different URL, you can update it:

```javascript
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Wait for widget to initialize
    setTimeout(() => {
        if (window.kwekweChatWidget) {
            window.kwekweChatWidget.setApiBaseUrl('https://your-api-domain.com');
        }
    }, 100);
});
</script>
```

### Custom Styling
You can override the default styles by adding your own CSS:

```css
/* Custom chat button color */
.kwekwe-chat-button {
    background: linear-gradient(45deg, #your-color-1, #your-color-2) !important;
}

/* Custom chat container size */
.kwekwe-chat-container {
    width: 400px !important;
    height: 600px !important;
}

/* Custom positioning */
.kwekwe-chat-button,
.kwekwe-chat-container {
    bottom: 40px !important;
    right: 40px !important;
}
```

## WordPress Integration

### Method 1: Using Theme Customizer
1. Go to Appearance → Theme Customizer → Additional CSS
2. Paste the CSS content from `kwekwe-chat-widget.css`
3. Go to Appearance → Theme Editor → footer.php
4. Add the script before `</body>`:
```html
<script src="https://your-domain.com/kwekwe-chat-widget.js"></script>
```

### Method 2: Using Plugin
1. Install a custom CSS/JS plugin like "Simple Custom CSS and JS"
2. Create a new CSS custom code and paste the CSS content
3. Create a new HTML custom code and paste:
```html
<script src="https://your-domain.com/kwekwe-chat-widget.js"></script>
```

## Static HTML Website Integration

### Step 1: Upload Files
Upload `kwekwe-chat-widget.css` and `kwekwe-chat-widget.js` to your website's assets folder.

### Step 2: Update HTML Template
Add to your HTML template:

```html
<!DOCTYPE html>
<html>
<head>
    <!-- Your existing head content -->
    <link rel="stylesheet" href="assets/kwekwe-chat-widget.css">
</head>
<body>
    <!-- Your existing body content -->
    
    <script src="assets/kwekwe-chat-widget.js"></script>
</body>
</html>
```

## Content Management System (CMS) Integration

### General Steps:
1. Upload the CSS and JS files to your CMS media library
2. Add CSS link to your site's header template
3. Add JS script to your site's footer template
4. Clear cache and test

### Example for Different CMS:

**Joomla:**
- CSS: Extensions → Templates → Your Template → Add CSS
- JS: Extensions → Templates → Your Template → Add JavaScript

**Drupal:**
- CSS: Appearance → Settings → Your Theme → Additional CSS
- JS: Create a custom block with JavaScript and add to footer region

**Shopify:**
- CSS: Online Store → Themes → Customize → Theme settings → Custom CSS
- JS: Online Store → Themes → Edit code → theme.liquid (add before </body>)

## Hosting Requirements

### For the Widget Files:
- Any web server that can serve static files
- HTTPS recommended for production
- No server-side processing required

### For the Chat API:
- The chatbot backend (main-simple.py) needs to be hosted
- Recommended: PythonAnywhere, Heroku, AWS, or similar
- API endpoint must be accessible from your website

## Testing Checklist

Before going live, test:
- [ ] Chat button appears and is clickable
- [ ] Chat window opens and closes properly
- [ ] Messages can be sent and received
- [ ] Typing indicator works
- [ ] Responsive design on mobile devices
- [ ] No JavaScript errors in browser console
- [ ] API connectivity is working

## Troubleshooting

### Chat Button Not Showing
- Check if CSS file is loading correctly
- Verify JavaScript file is included
- Check browser console for errors

### Messages Not Sending
- Verify API endpoint is accessible
- Check CORS settings on your API server
- Ensure API is running and responding

### Styling Issues
- Check for CSS conflicts with existing styles
- Use browser developer tools to inspect elements
- Add `!important` to override conflicting styles

### Mobile Issues
- Test on actual mobile devices
- Check responsive breakpoints in CSS
- Ensure touch events work properly

## Production Deployment

### 1. Update API URL
Change the API URL in the JavaScript file to your production endpoint:
```javascript
this.apiBaseUrl = 'https://your-production-api.com';
```

### 2. Enable HTTPS
Both your website and API should use HTTPS for security.

### 3. Optimize Performance
- Minify CSS and JS files
- Enable gzip compression
- Use CDN for faster delivery

### 4. Monitor and Maintain
- Monitor API performance
- Check error logs regularly
- Update content as needed

## Support

For technical support:
1. Check this guide first
2. Test in a clean environment
3. Check browser console for errors
4. Verify API connectivity

## Security Notes

- The widget communicates with your API via HTTPS
- User messages are processed server-side
- No sensitive data is stored in the browser
- Regular security updates recommended

---

**The chat widget is now ready to be integrated into your Kwekwe Polytechnic website!**
