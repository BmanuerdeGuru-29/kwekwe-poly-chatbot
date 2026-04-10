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
          "Hello! 👋 I'm your friendly AI assistant from Kwekwe Polytechnic. I'm here to help with any questions you might have about our programs, admissions, fees, or student life. What can I help you with today?",
        footerText: script?.dataset?.footerText || "Kwekwe Polytechnic Official AI Assistant<br>Powered by IT Unit &copy; 2025 Kwekwe Polytechnic",
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
        const response = await fetch(this.buildApiUrl(), {
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
          queryType: data.query_type || "local-search",
          answerMode: data.answer_mode || "local-knowledge",
          openai: data.openai || { used: false },
        });
      } catch (error) {
        this.pushAssistantMessage(
          "The Kwekwe Polytechnic assistant is temporarily unavailable. Please try again, or use the official contacts and application links."
        );
      } finally {
        this.setTyping(false);
      }
    }

    buildApiUrl() {
      const base = (this.config.apiBaseUrl || window.location.origin).replace(/\/$/, "");
      if (base.endsWith(".php")) {
        return base;
      }
      return `${base}/api/chat.php`;
    }

    isSafeHttpUrl(value) {
      return /^https?:\/\//i.test(String(value || "").trim());
    }

    buildSafeLinkMarkup(url, label) {
      if (!this.isSafeHttpUrl(url)) {
        return "";
      }
      const safeUrl = this.escapeHtml(String(url).trim());
      const safeLabel = this.escapeHtml(label || url);
      return `<a href="${safeUrl}" target="_blank" rel="noreferrer">${safeLabel}</a>`;
    }

    formatInlineText(text) {
      const placeholders = [];
      const escaped = this.escapeHtml(text);
      const withLinks = escaped.replace(/https?:\/\/[^\s<]+/gi, (url) => {
        const token = `__LINK_${placeholders.length}__`;
        placeholders.push(this.buildSafeLinkMarkup(url, url));
        return token;
      });

      let formatted = withLinks
        .replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>")
        .replace(/`([^`]+)`/g, "<code>$1</code>");

      placeholders.forEach((markup, index) => {
        formatted = formatted.replace(`__LINK_${index}__`, markup);
      });

      return formatted;
    }

    findNextNonEmptyLine(lines, startIndex) {
      for (let index = startIndex; index < lines.length; index += 1) {
        if (lines[index].trim()) {
          return index;
        }
      }
      return -1;
    }

    formatParagraphBlock(lines) {
      if (lines.length === 1) {
        const singleLine = lines[0];
        const labelMatch = singleLine.match(/^([^:]{1,60}:)\s+(.+)$/);
        if (labelMatch && !/^https?:/i.test(singleLine)) {
          return `<p><strong>${this.formatInlineText(labelMatch[1])}</strong> ${this.formatInlineText(labelMatch[2])}</p>`;
        }
      }
      return `<p>${lines.map((line) => this.formatInlineText(line)).join("<br>")}</p>`;
    }

    buildFormattedHtml(text) {
      const normalized = String(text || "").replace(/\r\n/g, "\n").trim();
      if (!normalized) {
        return "";
      }

      const lines = normalized.split("\n");
      const blocks = [];
      let index = 0;

      while (index < lines.length) {
        const trimmed = lines[index].trim();
        if (!trimmed) {
          index += 1;
          continue;
        }

        if (/^#{1,3}\s+/.test(trimmed)) {
          blocks.push(`<p class="kwekwe-widget-formatted-heading">${this.formatInlineText(trimmed.replace(/^#{1,3}\s+/, ""))}</p>`);
          index += 1;
          continue;
        }

        if (/^[-*]\s+/.test(trimmed)) {
          const items = [];
          while (index < lines.length && /^[-*]\s+/.test(lines[index].trim())) {
            items.push(lines[index].trim().replace(/^[-*]\s+/, ""));
            index += 1;
          }
          blocks.push(`<ul>${items.map((item) => `<li>${this.formatInlineText(item)}</li>`).join("")}</ul>`);
          continue;
        }

        if (/^\d+\.\s+/.test(trimmed)) {
          const items = [];
          while (index < lines.length && /^\d+\.\s+/.test(lines[index].trim())) {
            items.push(lines[index].trim().replace(/^\d+\.\s+/, ""));
            index += 1;
          }
          blocks.push(`<ol>${items.map((item) => `<li>${this.formatInlineText(item)}</li>`).join("")}</ol>`);
          continue;
        }

        const nextNonEmptyIndex = this.findNextNonEmptyLine(lines, index + 1);
        if (
          trimmed.endsWith(":") &&
          nextNonEmptyIndex !== -1 &&
          (/^[-*]\s+/.test(lines[nextNonEmptyIndex].trim()) || /^\d+\.\s+/.test(lines[nextNonEmptyIndex].trim()))
        ) {
          blocks.push(`<p class="kwekwe-widget-formatted-heading">${this.formatInlineText(trimmed)}</p>`);
          index += 1;
          continue;
        }

        const paragraphLines = [trimmed];
        index += 1;

        while (index < lines.length) {
          const nextTrimmed = lines[index].trim();
          if (!nextTrimmed) {
            index += 1;
            break;
          }
          if (/^[-*]\s+/.test(nextTrimmed) || /^\d+\.\s+/.test(nextTrimmed) || /^#{1,3}\s+/.test(nextTrimmed)) {
            break;
          }

          const lookaheadIndex = this.findNextNonEmptyLine(lines, index + 1);
          if (
            nextTrimmed.endsWith(":") &&
            lookaheadIndex !== -1 &&
            (/^[-*]\s+/.test(lines[lookaheadIndex].trim()) || /^\d+\.\s+/.test(lines[lookaheadIndex].trim()))
          ) {
            break;
          }

          paragraphLines.push(nextTrimmed);
          index += 1;
        }

        blocks.push(this.formatParagraphBlock(paragraphLines));
      }

      return `<div class="kwekwe-widget-formatted-content">${blocks.join("")}</div>`;
    }

    pushAssistantMessage(content, extras = {}) {
      this.pushMessage("assistant", content, extras);
    }

    buildAnswerBadges(message) {
      if (message.role !== "assistant") {
        return [];
      }
      if (!message.queryType && !message.answerMode && !message.handoff) {
        return [];
      }

      const badges = [];
      if (message.answerMode === "verified-handoff" || message.queryType === "verified-handoff") {
        badges.push({ label: "Verified handoff", className: "handoff" });
      } else if (message.queryType === "openai-fallback" || message.answerMode === "chatgpt-fallback") {
        badges.push({ label: "ChatGPT fallback", className: "fallback" });
      } else if (message.queryType === "fallback" || message.answerMode === "contacts-fallback") {
        badges.push({ label: "Official channels", className: "fallback" });
      } else {
        badges.push({ label: "Local knowledge", className: "local" });
      }

      if (message.handoff && message.answerMode !== "verified-handoff" && message.queryType !== "verified-handoff") {
        badges.push({ label: "Official follow-up", className: "handoff" });
      }

      return badges;
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
      // Instead of always scrolling to the absolute bottom (which can hide the start of a long reply),
      // scroll so that the newly inserted message is aligned to the top of the view. This ensures
      // users see the beginning of the answer (e.g. bullet points) rather than just the footer.
      const last = this.messagesEl.lastElementChild;
      if (last) {
        last.scrollIntoView({ block: 'start' });
      }
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
            ${this.buildFormattedHtml(message.content)}
          </div>
        `;
      } else {
        content.innerHTML = this.buildFormattedHtml(message.content);
      }

      const badges = this.buildAnswerBadges(message);
      if (badges.length) {
        const meta = document.createElement("div");
        meta.className = "kwekwe-widget-answer-meta";

        badges.forEach((badge) => {
          const pill = document.createElement("span");
          pill.className = `kwekwe-widget-answer-pill kwekwe-widget-answer-pill-${badge.className}`;
          pill.textContent = badge.label;
          meta.appendChild(pill);
        });

        content.insertBefore(meta, content.firstChild);
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

        const title = document.createElement("strong");
        title.textContent = message.handoff.office || "Official office";
        handoff.appendChild(title);

        if (message.handoff.status) {
          const status = document.createElement("div");
          status.className = "kwekwe-widget-handoff-status";
          status.textContent = message.handoff.status;
          handoff.appendChild(status);
        }

        if (message.handoff.reason) {
          const reason = document.createElement("p");
          reason.className = "kwekwe-widget-handoff-reason";
          reason.textContent = message.handoff.reason;
          handoff.appendChild(reason);
        }

        if (message.handoff.scope) {
          const scope = document.createElement("p");
          scope.className = "kwekwe-widget-handoff-scope";
          scope.textContent = `Best for: ${message.handoff.scope}`;
          handoff.appendChild(scope);
        }

        if (message.handoff.message) {
          const body = document.createElement("div");
          body.innerHTML = this.buildFormattedHtml(message.handoff.message);
          handoff.appendChild(body);
        }

        if (Array.isArray(message.handoff.channels) && message.handoff.channels.length) {
          const channels = document.createElement("div");
          channels.className = "kwekwe-widget-handoff-channels";
          message.handoff.channels.forEach((channel) => {
            const row = document.createElement("div");
            row.className = "kwekwe-widget-handoff-channel";
            if (channel.url) {
              row.innerHTML = `<strong>${this.escapeHtml(channel.label || "Link")}:</strong> <a href="${this.escapeHtml(channel.url)}" target="_blank" rel="noreferrer">${this.escapeHtml(channel.value || channel.url)}</a>`;
            } else {
              row.innerHTML = `<strong>${this.escapeHtml(channel.label || "Contact")}:</strong> ${this.escapeHtml(channel.value || "")}`;
            }
            channels.appendChild(row);
          });
          handoff.appendChild(channels);
        }

        if (message.handoff.recommended_action) {
          const action = document.createElement("p");
          action.className = "kwekwe-widget-handoff-action";
          action.textContent = message.handoff.recommended_action;
          handoff.appendChild(action);
        }

        content.appendChild(handoff);
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
