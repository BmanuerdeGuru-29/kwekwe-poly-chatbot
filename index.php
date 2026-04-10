<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$config = kwekwe_config();
$links = $config['links'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kwekwe Polytechnic AI Assistant</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{--navy:#16204f;--royal:#3252c2;--gold:#f4a01b;--ink:#18233d;--muted:#667085;--bg:#f7f4ee;--panel:rgba(255,255,255,.94);--border:rgba(22,32,79,.12);--shadow:0 24px 60px rgba(22,32,79,.12)}
    *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:"Poppins","Segoe UI",sans-serif;color:var(--ink);background:radial-gradient(circle at top left,rgba(244,160,27,.16),transparent 24%),radial-gradient(circle at top right,rgba(50,82,194,.12),transparent 24%),linear-gradient(180deg,#fcfbf8 0%,var(--bg) 100%)}a{color:inherit;text-decoration:none}button,input,textarea,select{font:inherit}[hidden]{display:none!important}
    .shell{max-width:1220px;margin:0 auto;padding:24px}.topbar,.row,.cards,.actions,.footer{display:flex;flex-wrap:wrap;gap:12px;align-items:center}.topbar{justify-content:space-between;margin-bottom:28px}.brand{display:flex;align-items:center;gap:16px}.brand img{width:80px;height:80px;object-fit:contain;border-radius:24px;background:#fff;padding:8px;border:1px solid var(--border);box-shadow:0 12px 28px rgba(22,32,79,.12)}.brand h1{margin:0;color:var(--navy);font-size:clamp(1.6rem,2vw,2.2rem)}.brand p{margin:6px 0 0;color:var(--muted)}
    .btn,.chip,.card button{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 18px;border-radius:999px;border:1px solid transparent;cursor:pointer;transition:transform .18s ease,box-shadow .18s ease}.btn:hover,.chip:hover,.card button:hover{transform:translateY(-1px)}.btn.primary{background:linear-gradient(135deg,var(--navy) 0%,var(--royal) 100%);color:#fff;box-shadow:0 16px 28px rgba(22,32,79,.18)}.btn.secondary,.chip{background:#fff;border-color:var(--border);color:var(--navy)}
    .hero,.panel{background:var(--panel);border:1px solid var(--border);border-radius:30px;box-shadow:var(--shadow)}.hero{padding:28px;text-align:center}.eyebrow,.badge{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;background:rgba(22,32,79,.06);color:var(--navy);font-size:.92rem}.eyebrow{background:rgba(244,160,27,.16);font-weight:700;text-transform:uppercase;letter-spacing:.08em}.dot{width:10px;height:10px;border-radius:50%;background:var(--gold);box-shadow:0 0 0 4px rgba(244,160,27,.16)}.dot.online{background:#34a853;box-shadow:0 0 0 4px rgba(52,168,83,.16)}
    .hero h2{margin:18px auto 14px;max-width:840px;color:var(--navy);font-size:clamp(2.5rem,5vw,4.7rem);line-height:1.03}.hero p{max-width:760px;margin:0 auto;color:var(--muted);line-height:1.75}
    .workspace{display:grid;grid-template-columns:1.35fr .85fr;gap:24px;margin-top:24px}.stack{display:grid;gap:24px}.panel{padding:24px}.panel h3{margin:0 0 10px;color:var(--navy)}.panel p{margin:0;color:var(--muted);line-height:1.65}
    .cards{margin-top:18px}.card{flex:1 1 220px;padding:18px;border-radius:24px;border:1px solid var(--border);background:linear-gradient(180deg,#fff,#fff9ee);text-align:left}.card strong{display:block;color:var(--navy);margin-bottom:6px}.card span{display:block;color:var(--muted);font-size:.92rem}.card button{padding:0;border:0;background:none;justify-content:flex-start;text-align:left;color:inherit}
    .chat-window{min-height:420px;max-height:60vh;overflow:auto;padding:20px;border-radius:26px;background:linear-gradient(180deg,rgba(255,255,255,.95),rgba(245,247,255,.92));border:1px solid rgba(22,32,79,.08)}.message{display:flex;margin-bottom:16px}.message.user{justify-content:flex-end}.bubble{max-width:min(88%,720px);padding:16px 18px;border-radius:24px;line-height:1.68;box-shadow:0 10px 24px rgba(22,32,79,.06)}.message.user .bubble{background:linear-gradient(135deg,var(--navy) 0%,var(--royal) 100%);color:#fff;border-bottom-right-radius:8px}.message.assistant .bubble{background:#fff;border:1px solid rgba(22,32,79,.08);border-bottom-left-radius:8px}
    .formatted{display:grid;gap:10px}.formatted p,.formatted ul,.formatted ol{margin:0}.formatted ul,.formatted ol{padding-left:20px;display:grid;gap:6px}.formatted code{padding:2px 6px;border-radius:8px;background:rgba(22,32,79,.08);font-size:.92em}.formatted-heading{font-weight:700;color:var(--navy)}.message.user .formatted code{background:rgba(255,255,255,.16)}.message.user .formatted-heading{color:#fff}.message.user a{color:#fff;text-decoration:underline}
    .compose{display:grid;gap:14px;margin-top:18px}.field,textarea,select{width:100%;padding:16px 18px;border:1px solid rgba(22,32,79,.14);border-radius:20px;background:#fff;color:var(--ink)}textarea{min-height:110px;resize:vertical}.typing{display:none;color:var(--muted);font-style:italic;margin-top:10px}
    .results,.info-list{display:grid;gap:12px;margin-top:18px}.result,.info-card,.handoff{padding:16px;border-radius:22px;background:#fff;border:1px solid rgba(22,32,79,.1)}.result strong,.info-card strong{display:block;color:var(--navy);margin-bottom:6px}.result small{display:block;color:var(--muted);margin-top:8px}.handoff{background:rgba(22,32,79,.04);display:grid;gap:10px}.handoff-status{display:inline-flex;align-items:center;justify-content:center;width:max-content;padding:5px 10px;border-radius:999px;background:rgba(15,118,110,.12);color:#0f766e;font-size:.78rem;font-weight:700}.handoff-reason,.handoff-action,.handoff-scope{margin:0;color:var(--muted);line-height:1.6}.handoff-channels{display:grid;gap:6px}.handoff-channel strong{display:inline;color:var(--navy);margin:0}.sources,.feedback-row,.answer-meta{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:12px}
    .answer-meta{margin-top:0;margin-bottom:12px}.answer-pill{display:inline-flex;align-items:center;justify-content:center;padding:6px 10px;border-radius:999px;font-size:.78rem;font-weight:700;letter-spacing:.01em}.answer-pill.local{background:rgba(22,32,79,.08);color:var(--navy)}.answer-pill.fallback{background:rgba(244,160,27,.16);color:#8a5a00}.answer-pill.handoff{background:rgba(15,118,110,.12);color:#0f766e}
    .footer{justify-content:space-between;padding:26px 4px 12px;color:var(--muted)}.sr{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}
    @media (max-width:980px){.workspace{grid-template-columns:1fr}.hero h2{font-size:clamp(2rem,10vw,3rem)}}@media (max-width:760px){.shell{padding:16px}.chat-window{min-height:380px}.row.mobile-stack{flex-direction:column;align-items:stretch}}
  </style>
</head>
<body>
  <div class="shell">
    <header class="topbar">
      <div class="brand">
        <img src="./logo.png" alt="Kwekwe Polytechnic logo">
        <div>
          <h1>Kwekwe Polytechnic</h1>
          <p>Offline-ready AI assistant rebuilt in PHP.</p>
        </div>
      </div>
      <div class="row">
        <a class="btn secondary" href="<?= htmlspecialchars($links['website'], ENT_QUOTES) ?>" target="_blank" rel="noreferrer">Official Website</a>
        <a class="btn secondary" href="<?= htmlspecialchars($links['portal'], ENT_QUOTES) ?>" target="_blank" rel="noreferrer">Student Portal</a>
        <a class="btn primary" href="<?= htmlspecialchars($links['apply'], ENT_QUOTES) ?>" target="_blank" rel="noreferrer">Apply Now</a>
      </div>
    </header>

    <section class="hero">
      <div class="row" style="justify-content:center">
        <span class="badge"><span class="dot" id="status-dot"></span><span id="status-text">Checking local service</span></span>
        <span class="badge">PHP runtime</span>
        <span class="badge">No external server services</span>
      </div>
      <div class="eyebrow" style="margin-top:18px">Kwekwe Polytechnic AI Assistant</div>
      <h2>Ask about programmes, fees, admissions, portal access, accommodation, and exams.</h2>
      <p>The assistant now answers from local knowledge files and flat-file search, with PHP serving the whole experience and Python kept as an optional offline helper for rebuilding the knowledge index.</p>
    </section>

    <div class="workspace">
      <main class="stack">
        <section class="panel">
          <h3>Quick Starts</h3>
          <p>Jump into common student questions and continue the conversation in the same thread.</p>
          <div class="cards">
            <div class="card"><button type="button" class="ask" data-prompt="How do I apply to Kwekwe Polytechnic?"><strong>Admissions</strong><span>Application steps, documents, and intake guidance.</span></button></div>
            <div class="card"><button type="button" class="ask" data-prompt="Show me the fee payment options in USD and ZiG."><strong>Fees</strong><span>Tuition, payment methods, and follow-up routes.</span></button></div>
            <div class="card"><button type="button" class="ask" data-prompt="How do I access the student portal and get ICT help?"><strong>Portal Help</strong><span>E-learning access and ICT support guidance.</span></button></div>
            <div class="card"><button type="button" class="ask" data-prompt="Tell me about accommodation and hostels at Kwekwe Polytechnic."><strong>Accommodation</strong><span>Hostel availability and student affairs handoff.</span></button></div>
          </div>
        </section>

        <section class="panel">
          <div class="row" style="justify-content:space-between">
            <div>
              <h3>Conversation</h3>
              <p>The chat runs against local PHP endpoints and a file-based knowledge index.</p>
            </div>
            <div class="row">
              <span class="badge">Kwekwe-only scope</span>
              <span class="badge">Local search answers</span>
            </div>
          </div>

          <div id="chat-window" class="chat-window" aria-live="polite"></div>
          <div id="typing" class="typing">The assistant is searching the local knowledge base...</div>

          <form id="chat-form" class="compose">
            <label class="sr" for="message-input">Message</label>
            <textarea id="message-input" placeholder="Ask about admissions, engineering, commerce, fees, accommodation, ICT, HEXCO, or contact details..."></textarea>
            <div class="row mobile-stack" style="justify-content:space-between">
              <div class="row">
                <label class="sr" for="language-select">Language</label>
                <select id="language-select" style="max-width:180px">
                  <option value="en">English</option>
                  <option value="sn">Shona</option>
                  <option value="nd">Ndebele</option>
                </select>
                <button id="clear-chat" class="btn secondary" type="button">Clear Chat</button>
              </div>
              <button class="btn primary" type="submit">Send Message</button>
            </div>
          </form>
        </section>
      </main>

      <aside class="stack">
        <section class="panel">
          <h3>Search Knowledge</h3>
          <p>Search the indexed local knowledge base directly.</p>
          <form id="search-form" class="compose">
            <label class="sr" for="search-input">Search knowledge</label>
            <input id="search-input" class="field" type="search" placeholder="Search programmes, fees, exams, accommodation, portal help...">
            <button class="btn primary" type="submit">Search</button>
          </form>
          <div id="search-results" class="results" aria-live="polite"></div>
        </section>

        <section class="panel">
          <h3>Official Channels</h3>
          <p>Use these routes when you need formal confirmation or action.</p>
          <div class="info-list">
            <div class="info-card"><strong>Applications</strong><div>Apply through the official admissions portal for current intake processing.</div><div class="sources"><a class="chip" href="<?= htmlspecialchars($links['apply'], ENT_QUOTES) ?>" target="_blank" rel="noreferrer">Open Applications</a></div></div>
            <div class="info-card"><strong>Portal and E-learning</strong><div>Use the portal for online learning access and ICT follow-up.</div><div class="sources"><a class="chip" href="<?= htmlspecialchars($links['portal'], ENT_QUOTES) ?>" target="_blank" rel="noreferrer">Open Portal</a></div></div>
            <div class="info-card"><strong>General Contact</strong><div>Phone: <?= htmlspecialchars($config['contacts']['phone'], ENT_QUOTES) ?><br>WhatsApp: <?= htmlspecialchars($config['contacts']['whatsapp'], ENT_QUOTES) ?><br>Email: <?= htmlspecialchars($config['contacts']['email'], ENT_QUOTES) ?></div></div>
            <div class="info-card"><strong>Admin Console</strong><div>Review uploads, rebuild the knowledge index, and inspect analytics.</div><div class="sources"><a class="chip" href="./admin.php">Open Admin</a></div></div>
          </div>
        </section>
      </aside>
    </div>

    <footer class="footer">
      <span>PHP-first build with local-file search, analytics, feedback, and widget support.</span>
      <span><a href="./admin.php">Admin</a> | <a href="./kwekwe-demo.html">Widget Demo</a> | <a href="./README.md">Docs</a></span>
    </footer>
  </div>

  <script>
    const API = {
      chat: "./api/chat.php",
      search: "./api/search.php",
      feedback: "./api/feedback.php",
      health: "./api/health.php"
    };
    const SESSION_KEY = "kwekwe_php_chat_session_id";
    const LANG_KEY = "kwekwe_php_chat_language";
    let sessionId = localStorage.getItem(SESSION_KEY) || `session_${Math.random().toString(36).slice(2,10)}`;
    let language = localStorage.getItem(LANG_KEY) || "en";
    localStorage.setItem(SESSION_KEY, sessionId);
    document.getElementById("language-select").value = language;

    const state = {
      messages: [{
        role: "assistant",
        content: "Welcome to the Kwekwe Polytechnic AI assistant.\n\n- Ask about admissions, programmes, fees, accommodation, portal access, or HEXCO results.\n- Replies are grounded in the local knowledge files shipped with this PHP application.\n- Use the official channels when you need final confirmation or account-specific help.",
        suggested_actions: [
          {label: "Apply Online", type: "link", url: <?= json_encode($links['apply']) ?>},
          {label: "Ask About Fees", type: "prompt", prompt: "Show me the fee payment options in USD and ZiG."}
        ]
      }]
    };

    const chatWindow = document.getElementById("chat-window");
    const typing = document.getElementById("typing");

    function escapeHtml(value) {
      return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
    }

    function isSafeHttpUrl(value) {
      return /^https?:\/\//i.test(String(value || "").trim());
    }

    function safeLink(url, label) {
      if (!isSafeHttpUrl(url)) return "";
      return `<a href="${escapeHtml(url)}" target="_blank" rel="noreferrer">${escapeHtml(label || url)}</a>`;
    }

    function formatInline(text) {
      const placeholders = [];
      const escaped = escapeHtml(text);
      const withLinks = escaped.replace(/https?:\/\/[^\s<]+/gi, (url) => {
        const token = `__LINK_${placeholders.length}__`;
        placeholders.push(safeLink(url, url));
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

    function nextNonEmpty(lines, startIndex) {
      for (let index = startIndex; index < lines.length; index += 1) {
        if (lines[index].trim()) return index;
      }
      return -1;
    }

    function formatParagraph(lines) {
      if (lines.length === 1) {
        const match = lines[0].match(/^([^:]{1,60}:)\s+(.+)$/);
        if (match && !/^https?:/i.test(lines[0])) {
          return `<p><strong>${formatInline(match[1])}</strong> ${formatInline(match[2])}</p>`;
        }
      }
      return `<p>${lines.map((line) => formatInline(line)).join("<br>")}</p>`;
    }

    function formatContent(text) {
      const normalized = String(text ?? "").replace(/\r\n/g, "\n").trim();
      if (!normalized) return "";

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
          blocks.push(`<p class="formatted-heading">${formatInline(trimmed.replace(/^#{1,3}\s+/, ""))}</p>`);
          index += 1;
          continue;
        }

        if (/^[-*]\s+/.test(trimmed)) {
          const items = [];
          while (index < lines.length && /^[-*]\s+/.test(lines[index].trim())) {
            items.push(lines[index].trim().replace(/^[-*]\s+/, ""));
            index += 1;
          }
          blocks.push(`<ul>${items.map((item) => `<li>${formatInline(item)}</li>`).join("")}</ul>`);
          continue;
        }

        if (/^\d+\.\s+/.test(trimmed)) {
          const items = [];
          while (index < lines.length && /^\d+\.\s+/.test(lines[index].trim())) {
            items.push(lines[index].trim().replace(/^\d+\.\s+/, ""));
            index += 1;
          }
          blocks.push(`<ol>${items.map((item) => `<li>${formatInline(item)}</li>`).join("")}</ol>`);
          continue;
        }

        const lookahead = nextNonEmpty(lines, index + 1);
        if (trimmed.endsWith(":") && lookahead !== -1 && (/^[-*]\s+/.test(lines[lookahead].trim()) || /^\d+\.\s+/.test(lines[lookahead].trim()))) {
          blocks.push(`<p class="formatted-heading">${formatInline(trimmed)}</p>`);
          index += 1;
          continue;
        }

        const paragraph = [trimmed];
        index += 1;
        while (index < lines.length) {
          const next = lines[index].trim();
          if (!next) {
            index += 1;
            break;
          }
          if (/^[-*]\s+/.test(next) || /^\d+\.\s+/.test(next) || /^#{1,3}\s+/.test(next)) break;

          const marker = nextNonEmpty(lines, index + 1);
          if (next.endsWith(":") && marker !== -1 && (/^[-*]\s+/.test(lines[marker].trim()) || /^\d+\.\s+/.test(lines[marker].trim()))) break;

          paragraph.push(next);
          index += 1;
        }

        blocks.push(formatParagraph(paragraph));
      }

      return `<div class="formatted">${blocks.join("")}</div>`;
    }

    function buildAnswerBadges(message) {
      if (message.role !== "assistant") return [];
      if (!message.query_type && !message.answer_mode && !message.handoff) return [];

      const badges = [];
      if (message.answer_mode === "verified-handoff" || message.query_type === "verified-handoff") {
        badges.push({ label: "Verified handoff", className: "handoff" });
      } else if (message.query_type === "openai-fallback" || message.answer_mode === "chatgpt-fallback") {
        badges.push({ label: "ChatGPT fallback", className: "fallback" });
      } else if (message.query_type === "fallback" || message.answer_mode === "contacts-fallback") {
        badges.push({ label: "Official channels", className: "fallback" });
      } else {
        badges.push({ label: "Local knowledge", className: "local" });
      }

      if (message.handoff && message.answer_mode !== "verified-handoff" && message.query_type !== "verified-handoff") {
        badges.push({ label: "Official follow-up", className: "handoff" });
      }

      return badges;
    }

    function renderMessage(message) {
      const article = document.createElement("article");
      article.className = `message ${message.role}`;

      const bubble = document.createElement("div");
      bubble.className = "bubble";
      bubble.innerHTML = formatContent(message.content);

      const badges = buildAnswerBadges(message);
      if (badges.length) {
        const meta = document.createElement("div");
        meta.className = "answer-meta";
        badges.forEach((badge) => {
          const pill = document.createElement("span");
          pill.className = `answer-pill ${badge.className}`;
          pill.textContent = badge.label;
          meta.appendChild(pill);
        });
        bubble.insertBefore(meta, bubble.firstChild);
      }

      if (Array.isArray(message.suggested_actions) && message.suggested_actions.length) {
        const actions = document.createElement("div");
        actions.className = "actions";
        message.suggested_actions.forEach((action) => {
          const control = document.createElement(action.url ? "a" : "button");
          control.className = "chip";
          control.textContent = action.label;
          if (action.url) {
            control.href = action.url;
            control.target = "_blank";
            control.rel = "noreferrer";
          } else {
            control.type = "button";
            control.dataset.prompt = action.prompt || "";
            control.addEventListener("click", () => sendMessage(action.prompt));
          }
          actions.appendChild(control);
        });
        bubble.appendChild(actions);
      }

      if (message.handoff) {
        const handoff = document.createElement("div");
        handoff.className = "handoff";

        const title = document.createElement("strong");
        title.textContent = message.handoff.office || "Official office";
        handoff.appendChild(title);

        if (message.handoff.status) {
          const status = document.createElement("div");
          status.className = "handoff-status";
          status.textContent = message.handoff.status;
          handoff.appendChild(status);
        }

        if (message.handoff.reason) {
          const reason = document.createElement("p");
          reason.className = "handoff-reason";
          reason.textContent = message.handoff.reason;
          handoff.appendChild(reason);
        }

        if (message.handoff.scope) {
          const scope = document.createElement("p");
          scope.className = "handoff-scope";
          scope.textContent = `Best for: ${message.handoff.scope}`;
          handoff.appendChild(scope);
        }

        if (message.handoff.message) {
          const messageBlock = document.createElement("div");
          messageBlock.innerHTML = formatContent(message.handoff.message);
          handoff.appendChild(messageBlock);
        }

        if (Array.isArray(message.handoff.channels) && message.handoff.channels.length) {
          const channels = document.createElement("div");
          channels.className = "handoff-channels";
          message.handoff.channels.forEach((channel) => {
            const row = document.createElement("div");
            row.className = "handoff-channel";
            if (channel.url) {
              row.innerHTML = `<strong>${escapeHtml(channel.label || "Link")}:</strong> <a href="${escapeHtml(channel.url)}" target="_blank" rel="noreferrer">${escapeHtml(channel.value || channel.url)}</a>`;
            } else {
              row.innerHTML = `<strong>${escapeHtml(channel.label || "Contact")}:</strong> ${escapeHtml(channel.value || "")}`;
            }
            channels.appendChild(row);
          });
          handoff.appendChild(channels);
        }

        if (message.handoff.recommended_action) {
          const action = document.createElement("p");
          action.className = "handoff-action";
          action.textContent = message.handoff.recommended_action;
          handoff.appendChild(action);
        }

        bubble.appendChild(handoff);
      }

      if (message.role === "assistant" && !message.feedbackAttached) {
        const feedbackRow = document.createElement("div");
        feedbackRow.className = "feedback-row";
        const helpful = document.createElement("button");
        helpful.type = "button";
        helpful.className = "chip";
        helpful.textContent = "Helpful";
        helpful.addEventListener("click", () => sendFeedback(message, true));
        const notHelpful = document.createElement("button");
        notHelpful.type = "button";
        notHelpful.className = "chip";
        notHelpful.textContent = "Needs Work";
        notHelpful.addEventListener("click", () => sendFeedback(message, false));
        feedbackRow.append(helpful, notHelpful);
        bubble.appendChild(feedbackRow);
      }

      article.appendChild(bubble);
      chatWindow.appendChild(article);
      chatWindow.scrollTop = chatWindow.scrollHeight;
    }

    function resetChat() {
      chatWindow.innerHTML = "";
      state.messages.forEach(renderMessage);
    }

    async function apiRequest(url, options = {}) {
      const response = await fetch(url, options);
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(payload.detail || `Request failed with status ${response.status}`);
      }
      return payload;
    }

    async function sendMessage(text) {
      const message = String(text || "").trim();
      if (!message) return;

      state.messages.push({ role: "user", content: message });
      renderMessage({ role: "user", content: message });
      typing.style.display = "block";
      document.getElementById("message-input").value = "";

      try {
        const payload = await apiRequest(API.chat, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ message, session_id: sessionId, language })
        });

        sessionId = payload.session_id || sessionId;
        localStorage.setItem(SESSION_KEY, sessionId);

        const assistantMessage = {
          role: "assistant",
          content: payload.response || "I could not generate a response right now.",
          sources: payload.sources || [],
          handoff: payload.handoff || null,
          suggested_actions: payload.suggested_actions || [],
          query_type: payload.query_type || "local-search",
          answer_mode: payload.answer_mode || "local-knowledge",
          confidence: payload.confidence || null,
          decision: payload.decision || null,
          evidence: payload.evidence || null,
          intent: payload.intent || "general",
          openai: payload.openai || { used: false },
          user_query: message
        };
        state.messages.push(assistantMessage);
        renderMessage(assistantMessage);
      } catch (error) {
        renderMessage({
          role: "assistant",
          content: `The local PHP assistant is temporarily unavailable.\n\n- ${error.message}\n- Please try again, or use the official website and contacts.`
        });
      } finally {
        typing.style.display = "none";
      }
    }

    async function sendFeedback(message, helpful) {
      try {
        await apiRequest(API.feedback, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            session_id: sessionId,
            message_content: message.content,
            helpful,
            comment: "",
            intent: message.intent || "general",
            user_query: message.user_query || "",
            query_type: message.query_type || "",
            answer_mode: message.answer_mode || "",
            confidence_label: message.confidence?.label || "",
            confidence_score: message.confidence?.score ?? null,
            decision_strategy: message.decision?.strategy || "",
            decision_source: message.decision?.source || "",
            handoff_mode: message.decision?.handoff_mode || "",
            evidence_label: message.evidence?.label || "",
            evidence_score: message.evidence?.score ?? null,
            openai_used: Boolean(message.openai?.used)
          })
        });
      } catch (error) {
        console.error(error);
      }
    }

    async function searchKnowledge(query) {
      const resultsNode = document.getElementById("search-results");
      resultsNode.innerHTML = "";

      try {
        const payload = await apiRequest(`${API.search}?query=${encodeURIComponent(query)}&limit=5`);
        if (!payload.results?.length) {
          resultsNode.innerHTML = `<div class="result"><strong>No strong matches</strong><div>Try a broader question or ask directly in the chat.</div></div>`;
          return;
        }

        payload.results.forEach((result) => {
          const card = document.createElement("div");
          card.className = "result";
          const sourceLabel = result.metadata?.filename ? `Source: ${escapeHtml(result.metadata.filename)}` : "";
          card.innerHTML = `<strong>${escapeHtml(result.title)}</strong><div>${escapeHtml(result.heading || "Local knowledge match")}</div><p>${escapeHtml(result.excerpt || "")}</p><small>${sourceLabel}</small>`;
          resultsNode.appendChild(card);
        });
      } catch (error) {
        resultsNode.innerHTML = `<div class="result"><strong>Search unavailable</strong><div>${escapeHtml(error.message)}</div></div>`;
      }
    }

    async function checkHealth() {
      try {
        const payload = await apiRequest(API.health);
        document.getElementById("status-dot").classList.add("online");
        document.getElementById("status-text").textContent = `Ready - ${payload.document_count} docs indexed`;
      } catch (error) {
        document.getElementById("status-text").textContent = "Health check failed";
      }
    }

    document.getElementById("chat-form").addEventListener("submit", (event) => {
      event.preventDefault();
      sendMessage(document.getElementById("message-input").value);
    });

    document.querySelectorAll(".ask").forEach((button) => {
      button.addEventListener("click", () => sendMessage(button.dataset.prompt || ""));
    });

    document.getElementById("search-form").addEventListener("submit", (event) => {
      event.preventDefault();
      const query = document.getElementById("search-input").value.trim();
      if (query) searchKnowledge(query);
    });

    document.getElementById("clear-chat").addEventListener("click", () => {
      sessionId = `session_${Math.random().toString(36).slice(2,10)}`;
      localStorage.setItem(SESSION_KEY, sessionId);
      state.messages.splice(1);
      resetChat();
    });

    document.getElementById("language-select").addEventListener("change", (event) => {
      language = event.target.value;
      localStorage.setItem(LANG_KEY, language);
    });

    resetChat();
    checkHealth();
  </script>
</body>
</html>
