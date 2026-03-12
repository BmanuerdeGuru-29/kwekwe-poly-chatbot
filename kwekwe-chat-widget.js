(function () {
  "use strict";

  if (window.KwekweFloatingChatWidget) {
    return;
  }

  const CHAT_ICON = `
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path d="M12 3C6.92 3 3 6.63 3 11.15c0 2.59 1.27 4.89 3.45 6.39L5.4 21l3.81-1.92c.89.23 1.82.35 2.79.35 5.08 0 9-3.63 9-8.28S17.08 3 12 3Zm-4 7.5a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4Zm4 0a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4Zm4 0a1.2 1.2 0 1 1 0-2.4 1.2 1.2 0 0 1 0 2.4Z"></path>
    </svg>
  `;

  const CLOSE_ICON = `
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path d="M18.3 5.71 12 12l6.3 6.29-1.41 1.41L10.59 13.4 4.29 19.7 2.88 18.29 9.17 12 2.88 5.71 4.29 4.3l6.3 6.29 6.29-6.3z"></path>
    </svg>
  `;

  const SEND_ICON = `
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path d="M3.4 20.4 21 12 3.4 3.6 3 10l12 2-12 2 .4 6.4Z"></path>
    </svg>
  `;

  const DEFAULT_PROMPTS = [
    { label: "Fees", prompt: "What are the official fee payment options at Kwekwe Polytechnic?" },
    { label: "Courses", prompt: "Which courses and programmes are offered at Kwekwe Polytechnic?" },
    { label: "Staff", prompt: "How do I contact Kwekwe Polytechnic staff or the right office?" },
    { label: "Apply", prompt: "How do I apply to Kwekwe Polytechnic online?" },
  ];

  function resolveCurrentScript() {
    return (
      document.currentScript ||
      document.querySelector('script[data-kwekwe-widget], script[src*="embed.js"], script[src*="kwekwe-chat-widget.js"]')
    );
  }

  function uniqueSessionId() {
    return `widget_${Math.random().toString(36).slice(2, 10)}`;
  }

  class KwekweFloatingChatWidget {
    constructor(config = {}) {
      const script = resolveCurrentScript();
      const inferredAssetBase = script?.src ? new URL(".", script.src).href.replace(/\/$/, "") : window.location.origin;
      const inferredApiBase = script?.dataset?.apiUrl || window.KWEKWE_WIDGET_API_URL || inferredAssetBase;
      const storedSession = localStorage.getItem("kwekwe_widget_session_id");

      this.config = {
        assetBaseUrl: inferredAssetBase,
        apiBaseUrl: inferredApiBase,
        logoUrl: script?.dataset?.logoUrl || window.KWEKWE_WIDGET_LOGO_URL || `${inferredAssetBase}/logo.png`,
        launcherLabel: script?.dataset?.launcherLabel || "Try our new chatbot!",
        title: script?.dataset?.title || "Kwekwe Poly AI",
        subtitle: script?.dataset?.subtitle || "Official Assistant",
        greeting:
          script?.dataset?.greeting ||
          "Hi, I am Kwekwe Polytechnic's AI assistant. Ask me about fees, courses, staff contacts, applications, accommodation, or student support.",
        footerText: script?.dataset?.footerText || "Kwekwe Polytechnic Official AI Assistant",
        quickPrompts: DEFAULT_PROMPTS,
        ...config,
      };

      this.sessionId = storedSession || uniqueSessionId();
      this.messages = [];
      this.isOpen = false;
      this.isTyping = false;
      this.root = null;
      this.promptEl = null;
      this.launcherEl = null;
      this.panelEl = null;
      this.messagesEl = null;
      this.typingEl = null;
      this.inputEl = null;
      this.sendEl = null;
    }

    init() {
      if (document.getElementById("kwekwe-floating-widget")) {
        return;
      }

      localStorage.setItem("kwekwe_widget_session_id", this.sessionId);
      this.createWidget();
      this.bindEvents();
      this.pushAssistantMessage(this.config.greeting, { isWelcome: true, suggestedActions: this.config.quickPrompts });
    }

    createWidget() {
      const root = document.createElement("div");
      root.id = "kwekwe-floating-widget";
      root.className = "kwekwe-widget-shell";
      root.innerHTML = `
        <button class="kwekwe-widget-prompt" type="button" aria-label="${this.escapeHtml(this.config.launcherLabel)}">
          <span class="kwekwe-widget-prompt-pill">${this.escapeHtml(this.config.launcherLabel)}</span>
        </button>
        <button class="kwekwe-widget-launcher" type="button" aria-label="Open Kwekwe Polytechnic AI assistant">
          <span class="kwekwe-widget-launcher-ring"></span>
          <img src="${this.escapeHtml(this.config.logoUrl)}" alt="Kwekwe Polytechnic logo" class="kwekwe-widget-launcher-logo">
          <span class="kwekwe-widget-launcher-icon">${CHAT_ICON}</span>
        </button>
        <section class="kwekwe-widget-panel" aria-hidden="true" aria-label="Kwekwe Polytechnic AI Assistant">
          <header class="kwekwe-widget-header">
            <div class="kwekwe-widget-brand">
              <img src="${this.escapeHtml(this.config.logoUrl)}" alt="Kwekwe Polytechnic logo" class="kwekwe-widget-brand-logo">
              <div class="kwekwe-widget-brand-copy">
                <strong>${this.escapeHtml(this.config.title)}</strong>
                <span>${this.escapeHtml(this.config.subtitle)}</span>
              </div>
            </div>
            <button class="kwekwe-widget-close" type="button" aria-label="Close chat">${CLOSE_ICON}</button>
          </header>
          <div class="kwekwe-widget-badges">
            <span class="kwekwe-widget-badge">Information</span>
            <span class="kwekwe-widget-badge kwekwe-widget-badge-status">Official Website Assistant</span>
          </div>
          <div class="kwekwe-widget-messages"></div>
          <div class="kwekwe-widget-typing" hidden>
            <span></span><span></span><span></span>
          </div>
          <form class="kwekwe-widget-compose">
            <input class="kwekwe-widget-input" type="text" placeholder="Send a message..." autocomplete="off">
            <button class="kwekwe-widget-send" type="submit" aria-label="Send message">${SEND_ICON}</button>
          </form>
          <footer class="kwekwe-widget-footer">${this.escapeHtml(this.config.footerText)}</footer>
        </section>
      `;

      document.body.appendChild(root);

      this.root = root;
      this.promptEl = root.querySelector(".kwekwe-widget-prompt");
      this.launcherEl = root.querySelector(".kwekwe-widget-launcher");
      this.panelEl = root.querySelector(".kwekwe-widget-panel");
      this.messagesEl = root.querySelector(".kwekwe-widget-messages");
      this.typingEl = root.querySelector(".kwekwe-widget-typing");
      this.inputEl = root.querySelector(".kwekwe-widget-input");
      this.sendEl = root.querySelector(".kwekwe-widget-send");
      this.panelEl.setAttribute("aria-hidden", "true");

      const images = root.querySelectorAll("img");
      images.forEach((image) => {
        image.addEventListener("error", () => {
          if (image.src !== "https://apply.kwekwepoly.ac.zw/static/images/logo1.png") {
            image.src = "https://apply.kwekwepoly.ac.zw/static/images/logo1.png";
          }
        });
      });
    }

    bindEvents() {
      this.promptEl.addEventListener("click", () => this.open());
      this.launcherEl.addEventListener("click", () => this.toggle());
      this.root.querySelector(".kwekwe-widget-close").addEventListener("click", () => this.close());
      this.root.querySelector(".kwekwe-widget-compose").addEventListener("submit", (event) => {
        event.preventDefault();
        this.sendCurrentInput();
      });
      this.messagesEl.addEventListener("click", (event) => {
        const chip = event.target.closest("[data-kwekwe-prompt]");
        if (!chip) {
          return;
        }
        this.sendMessage(chip.getAttribute("data-kwekwe-prompt"));
      });
    }

    toggle() {
      if (this.isOpen) {
        this.close();
      } else {
        this.open();
      }
    }

    open() {
      this.isOpen = true;
      this.root.classList.add("is-open");
      this.panelEl.setAttribute("aria-hidden", "false");
      this.inputEl.focus();
      this.scrollToBottom();
    }

    close() {
      this.isOpen = false;
      this.root.classList.remove("is-open");
      this.panelEl.setAttribute("aria-hidden", "true");
    }

    async sendCurrentInput() {
      const text = this.inputEl.value.trim();
      if (!text) {
        return;
      }
      await this.sendMessage(text);
    }

    async sendMessage(text) {
      if (!text || this.isTyping) {
        return;
      }

      this.open();
      this.inputEl.value = "";
      this.pushMessage("user", text);
      this.setTyping(true);

      try {
        const response = await fetch(this.buildApiUrl("/chat/query"), {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            message: text,
            session_id: this.sessionId,
            language: "en",
            use_tools: false,
          }),
        });

        if (!response.ok) {
          throw new Error(`Chat request failed with status ${response.status}`);
        }

        const data = await response.json();
        if (data.session_id) {
          this.sessionId = data.session_id;
          localStorage.setItem("kwekwe_widget_session_id", this.sessionId);
        }

        this.pushAssistantMessage(data.response || "I could not generate a response just now.", {
          suggestedActions: Array.isArray(data.suggested_actions) ? data.suggested_actions : [],
          handoff: data.handoff || null,
          sources: Array.isArray(data.sources) ? data.sources : [],
        });
      } catch (error) {
        this.pushAssistantMessage(
          "The Kwekwe Polytechnic assistant is temporarily unavailable. Please try again, or use the official contacts and application links."
        );
      } finally {
        this.setTyping(false);
      }
    }

    buildApiUrl(path) {
      const base = (this.config.apiBaseUrl || window.location.origin).replace(/\/$/, "");
      if (base.endsWith("/api/v1")) {
        return `${base}${path}`;
      }
      return `${base}/api/v1${path}`;
    }

    pushAssistantMessage(content, extras = {}) {
      this.pushMessage("assistant", content, extras);
    }

    pushMessage(role, content, extras = {}) {
      const message = {
        id: `${role}_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
        role,
        content,
        ...extras,
      };

      this.messages.push(message);
      this.renderMessage(message);
      this.scrollToBottom();
    }

    renderMessage(message) {
      const article = document.createElement("article");
      article.className = `kwekwe-widget-message kwekwe-widget-message-${message.role}`;

      const content = document.createElement("div");
      content.className = "kwekwe-widget-message-content";

      if (message.role === "assistant" && message.isWelcome) {
        content.innerHTML = `
          <div class="kwekwe-widget-welcome">
            <span class="kwekwe-widget-welcome-kicker">Kwekwe Polytechnic</span>
            <p>${this.escapeHtml(message.content)}</p>
          </div>
        `;
      } else {
        const paragraph = document.createElement("p");
        paragraph.textContent = message.content;
        content.appendChild(paragraph);
      }

      if (Array.isArray(message.suggestedActions) && message.suggestedActions.length) {
        const actions = document.createElement("div");
        actions.className = "kwekwe-widget-actions";

        message.suggestedActions.forEach((action) => {
          const button = document.createElement(action.url ? "a" : "button");
          button.className = "kwekwe-widget-chip";
          button.textContent = action.label;

          if (action.url) {
            button.href = action.url;
            button.target = "_blank";
            button.rel = "noreferrer";
          } else {
            button.type = "button";
            button.setAttribute("data-kwekwe-prompt", action.prompt);
          }

          actions.appendChild(button);
        });

        content.appendChild(actions);
      }

      if (message.handoff) {
        const handoff = document.createElement("div");
        handoff.className = "kwekwe-widget-handoff";
        handoff.innerHTML = `
          <strong>${this.escapeHtml(message.handoff.office || "Official office")}</strong>
          <span>${this.escapeHtml(message.handoff.message || "Use the official office for follow-up support.")}</span>
        `;
        content.appendChild(handoff);
      }

      if (Array.isArray(message.sources) && message.sources.length) {
        const sources = document.createElement("div");
        sources.className = "kwekwe-widget-sources";

        message.sources.slice(0, 2).forEach((source) => {
          const url = source.metadata?.source_url || source.metadata?.source;
          if (!url || !/^https?:\/\//.test(url)) {
            return;
          }
          const link = document.createElement("a");
          link.href = url;
          link.target = "_blank";
          link.rel = "noreferrer";
          link.textContent = source.metadata?.filename || "Official source";
          sources.appendChild(link);
        });

        if (sources.children.length) {
          content.appendChild(sources);
        }
      }

      article.appendChild(content);
      this.messagesEl.appendChild(article);
    }

    setTyping(typing) {
      this.isTyping = typing;
      this.typingEl.hidden = !typing;
      this.inputEl.disabled = typing;
      this.sendEl.disabled = typing;
      if (!typing) {
        this.inputEl.focus();
      }
      this.scrollToBottom();
    }

    scrollToBottom() {
      requestAnimationFrame(() => {
        this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
      });
    }

    escapeHtml(value) {
      return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
    }
  }

  window.KwekweFloatingChatWidget = KwekweFloatingChatWidget;

  function initWidget() {
    if (window.kwekweFloatingChatWidgetInstance) {
      return;
    }
    const config = window.KWEKWE_WIDGET_CONFIG || {};
    window.kwekweFloatingChatWidgetInstance = new KwekweFloatingChatWidget(config);
    window.kwekweFloatingChatWidgetInstance.init();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initWidget, { once: true });
  } else {
    initWidget();
  }
})();
