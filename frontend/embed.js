(function () {
  "use strict";

  if (window.__KWEKWE_WIDGET_EMBED_LOADED__) {
    return;
  }
  window.__KWEKWE_WIDGET_EMBED_LOADED__ = true;

  const script =
    document.currentScript ||
    document.querySelector('script[data-kwekwe-widget], script[src*="embed.js"]');

  const scriptUrl = script?.src ? new URL(script.src, window.location.href) : new URL(window.location.href);
  const assetBaseUrl = new URL(".", scriptUrl).href.replace(/\/$/, "");
  const apiBaseUrl = script?.dataset?.apiUrl || window.KWEKWE_WIDGET_API_URL || assetBaseUrl;

  window.KWEKWE_WIDGET_CONFIG = {
    assetBaseUrl,
    apiBaseUrl,
    logoUrl: script?.dataset?.logoUrl || `${assetBaseUrl}/logo.png`,
    launcherLabel: script?.dataset?.launcherLabel || "Try our new chatbot!",
    title: script?.dataset?.title || "Kwekwe Poly AI",
    subtitle: script?.dataset?.subtitle || "Official Assistant",
    greeting:
      script?.dataset?.greeting ||
      "Hi, I am Kwekwe Polytechnic's AI assistant. Ask me about fees, courses, staff contacts, applications, accommodation, or student support.",
    footerText: script?.dataset?.footerText || "Kwekwe Polytechnic Official AI Assistant",
  };

  function loadStyle(href) {
    if (document.querySelector(`link[data-kwekwe-widget-css="${href}"]`)) {
      return;
    }

    const link = document.createElement("link");
    link.rel = "stylesheet";
    link.href = href;
    link.setAttribute("data-kwekwe-widget-css", href);
    document.head.appendChild(link);
  }

  function loadScript(src) {
    if (document.querySelector(`script[data-kwekwe-widget-js="${src}"]`)) {
      return;
    }

    const widgetScript = document.createElement("script");
    widgetScript.src = src;
    widgetScript.defer = true;
    widgetScript.setAttribute("data-kwekwe-widget-js", src);
    document.head.appendChild(widgetScript);
  }

  loadStyle("https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap");
  loadStyle(`${assetBaseUrl}/kwekwe-chat-widget.css`);
  loadScript(`${assetBaseUrl}/kwekwe-chat-widget.js`);
})();
