/**
 * Kwekwe Polytechnic Chatbot Embed Script
 * 
 * Include this script on any page to embed the chatbot:
 * <script src="https://your-domain.com/embed.js" data-api-url="https://your-api.com"></script>
 */

(function() {
  'use strict';

  // Configuration
  const config = {
    apiUrl: window.KWEKWE_CHATBOT_API_URL || 'http://localhost:8000',
    position: window.KWEKWE_CHATBOT_POSITION || 'bottom-right',
    title: window.KWEKWE_CHATBOT_TITLE || 'Kwekwe Polytechnic Assistant',
    primaryColor: window.KWEKWE_CHATBOT_PRIMARY_COLOR || '#006633',
    welcomeMessage: window.KWEKWE_CHATBOT_WELCOME_MESSAGE || 'Hello! How can I help you today?'
  };

  // Get configuration from data attributes
  const script = document.currentScript || document.querySelector('script[src*="embed.js"]');
  if (script) {
    config.apiUrl = script.dataset.apiUrl || config.apiUrl;
    config.position = script.dataset.position || config.position;
    config.title = script.dataset.title || config.title;
    config.primaryColor = script.dataset.primaryColor || config.primaryColor;
    config.welcomeMessage = script.dataset.welcomeMessage || config.welcomeMessage;
  }

  // Create container for the chat widget
  const containerId = 'kwekwe-chatbot-container';
  let container = document.getElementById(containerId);
  
  if (!container) {
    container = document.createElement('div');
    container.id = containerId;
    document.body.appendChild(container);
  }

  // Load React and the chat widget
  function loadChatWidget() {
    // Set global configuration for React app
    window.KWEKWE_CHATBOT_CONFIG = config;
    
    // Create React app container
    const rootElement = document.createElement('div');
    rootElement.id = 'kwekwe-chatbot-root';
    container.appendChild(rootElement);

    // Load the React bundle
    const script = document.createElement('script');
    script.src = config.apiUrl.replace('/api/v1', '') + '/assets/kwekwe-chatbot.umd.js';
    script.onload = function() {
      console.log('Kwekwe Polytechnic Chatbot loaded successfully');
    };
    script.onerror = function() {
      console.error('Failed to load Kwekwe Polytechnic Chatbot');
      // Show fallback message
      rootElement.innerHTML = `
        <div style="
          position: fixed;
          bottom: 20px;
          right: 20px;
          background: #f44336;
          color: white;
          padding: 10px 15px;
          border-radius: 5px;
          font-family: Arial, sans-serif;
          font-size: 14px;
          z-index: 9999;
          box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        ">
          Chatbot unavailable. Please refresh the page or contact support.
        </div>
      `;
    };
    document.head.appendChild(script);

    // Load CSS
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = config.apiUrl.replace('/api/v1', '') + '/assets/style.css';
    document.head.appendChild(link);
  }

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadChatWidget);
  } else {
    loadChatWidget();
  }

  // Expose global API
  window.KwekweChatbot = {
    open: function() {
      const event = new CustomEvent('kwekwe-chatbot-open');
      window.dispatchEvent(event);
    },
    close: function() {
      const event = new CustomEvent('kwekwe-chatbot-close');
      window.dispatchEvent(event);
    },
    toggle: function() {
      const event = new CustomEvent('kwekwe-chatbot-toggle');
      window.dispatchEvent(event);
    },
    sendMessage: function(message) {
      const event = new CustomEvent('kwekwe-chatbot-message', { detail: message });
      window.dispatchEvent(event);
    }
  };

})();
