<?php

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kwekwe Polytechnic Admin Console</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{--navy:#16204f;--royal:#3252c2;--gold:#f4a01b;--ink:#18233d;--muted:#667085;--bg:#f6f2e8;--panel:rgba(255,255,255,.94);--border:rgba(22,32,79,.12);--shadow:0 24px 60px rgba(22,32,79,.12)}
    *{box-sizing:border-box}body{margin:0;font-family:"Poppins","Segoe UI",sans-serif;background:radial-gradient(circle at top left,rgba(244,160,27,.18),transparent 24%),radial-gradient(circle at top right,rgba(50,82,194,.14),transparent 26%),linear-gradient(180deg,#fbf9f4 0%,var(--bg) 100%);color:var(--ink)}a{color:inherit;text-decoration:none}[hidden]{display:none!important}
    .shell{max-width:1180px;margin:0 auto;padding:24px}.top,.row,.grid,.footer{display:flex;flex-wrap:wrap;gap:12px;align-items:center}.top{justify-content:space-between;margin-bottom:24px}.brand{display:flex;align-items:center;gap:16px}.brand img{width:78px;height:78px;border-radius:24px;background:#fff;padding:7px;border:1px solid var(--border);box-shadow:0 14px 28px rgba(22,32,79,.12)}.brand h1{margin:0;color:var(--navy)}.brand p{margin:6px 0 0;color:var(--muted)}
    .panel{background:var(--panel);border:1px solid var(--border);border-radius:28px;padding:24px;box-shadow:var(--shadow);margin-bottom:24px}.login-grid,.dashboard{display:grid;gap:24px}.login-grid{grid-template-columns:1.1fr .9fr}.dashboard{grid-template-columns:repeat(2,minmax(0,1fr))}.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 18px;border-radius:999px;border:1px solid transparent;cursor:pointer;font:inherit;transition:transform .18s ease}.btn:hover{transform:translateY(-1px)}.primary{background:linear-gradient(135deg,var(--navy) 0%,var(--royal) 100%);color:#fff;box-shadow:0 14px 28px rgba(22,32,79,.18)}.secondary{background:#fff;border-color:rgba(22,32,79,.14);color:var(--navy)}
    .badge,.eyebrow{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;background:rgba(22,32,79,.06);color:var(--navy);font-size:.92rem}.eyebrow{background:rgba(244,160,27,.14);font-weight:700;text-transform:uppercase;letter-spacing:.08em}.dot{width:10px;height:10px;border-radius:50%;background:var(--gold);box-shadow:0 0 0 4px rgba(244,160,27,.16)}.dot.online{background:#34a853;box-shadow:0 0 0 4px rgba(52,168,83,.16)}
    .field,input,textarea{width:100%;padding:14px 16px;border:1px solid rgba(22,32,79,.16);border-radius:18px;background:#fff;font:inherit;color:var(--ink)}textarea{min-height:120px;resize:vertical}.login-card{padding:32px;background:linear-gradient(145deg,rgba(255,255,255,.98),rgba(255,248,234,.94))}.login-card h2{margin:14px 0 10px;color:var(--navy);font-size:clamp(2rem,4vw,3rem);line-height:1.02}.login-card p,.panel p{margin:0;color:var(--muted);line-height:1.7}
    .sidebar{padding:24px;background:linear-gradient(180deg,rgba(22,32,79,.98),rgba(50,82,194,.94));color:#eef3ff;display:grid;gap:14px}.metric{padding:18px;border-radius:22px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.14)}.metric strong{display:block;margin-bottom:6px;color:#fff8e6}.metric span{color:rgba(255,255,255,.82)}pre{background:#102030;color:#eaf1f7;padding:16px;border-radius:20px;overflow:auto;white-space:pre-wrap}
    .records{display:grid;gap:12px;margin-top:18px}.record{padding:14px;border-radius:18px;background:#fff;border:1px solid rgba(22,32,79,.1)}.record strong{display:block;margin-bottom:6px;color:var(--navy)}.full{grid-column:1 / -1}
    .summary-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:18px}.summary-tile{padding:18px;border-radius:22px;background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(245,248,255,.92));border:1px solid rgba(22,32,79,.1)}.summary-tile strong{display:block;font-size:1.8rem;color:var(--navy);line-height:1}.summary-tile span{display:block;margin-top:6px;color:var(--muted);line-height:1.5}.record-meta{margin-top:8px;color:#667085;line-height:1.6}.pill-row{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}.pill{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:rgba(22,32,79,.06);color:var(--navy);font-size:.88rem}
    @media (max-width:960px){.login-grid,.dashboard,.summary-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="shell">
    <header class="top">
      <div class="brand">
        <img src="./logo.png" alt="Kwekwe Polytechnic logo">
        <div>
          <h1>Kwekwe Polytechnic Admin Console</h1>
          <p>Local-file analytics, knowledge uploads, and PHP session auth.</p>
        </div>
      </div>
      <div class="row">
        <span class="badge"><span class="dot" id="session-dot"></span><span id="session-label">Checking session</span></span>
        <a class="btn secondary" href="./index.php">Back to Assistant</a>
        <button id="logout-button" class="btn secondary" type="button" hidden>Log Out</button>
      </div>
    </header>

    <section id="login-view" class="login-grid">
      <div class="panel login-card">
        <div class="eyebrow">Admin sign-in</div>
        <h2>Manage the PHP-first Kwekwe assistant.</h2>
        <p>Use the configured admin key to review analytics, inspect feedback, upload new local source documents, and rebuild the knowledge index.</p>
        <form id="login-form" style="margin-top:24px">
          <label for="admin-key" style="display:block;margin-bottom:10px;color:var(--navy);font-weight:600">Admin key</label>
          <input id="admin-key" class="field" type="password" placeholder="Enter the configured admin key">
          <div class="row" style="margin-top:16px">
            <button class="btn primary" type="submit">Sign In</button>
            <button id="check-session" class="btn secondary" type="button">Check Access</button>
          </div>
        </form>
        <pre id="login-output" style="margin-top:18px">Checking admin session...</pre>
      </div>

      <aside class="panel sidebar">
        <div class="metric"><strong>No external services</strong><span>Analytics, feedback, uploads, and auth all live in local files and native PHP sessions.</span></div>
        <div class="metric"><strong>Python is optional</strong><span>The app rebuilds its own index in PHP; Python exists only as an offline helper for export and regeneration work.</span></div>
        <div class="metric"><strong>Local source driven</strong><span>Answers come from the checked-in sample docs plus any `.md` or `.txt` files uploaded here.</span></div>
      </aside>
    </section>

    <div id="admin-app" hidden>
      <div class="dashboard">
        <section class="panel">
          <h3>Session State</h3>
          <p>Current browser session details for the admin console.</p>
          <div class="row" style="margin-top:16px">
            <button id="load-session" class="btn primary" type="button">Refresh Session</button>
          </div>
          <pre id="session-output">No session details loaded yet.</pre>
        </section>

        <section class="panel">
          <h3>Knowledge Index</h3>
          <p>See how many source documents and chunks are currently available.</p>
          <div class="row" style="margin-top:16px">
            <button id="load-knowledge" class="btn secondary" type="button">Load Index</button>
            <button id="rebuild-knowledge" class="btn primary" type="button">Rebuild Index</button>
          </div>
          <pre id="knowledge-output">Index details not loaded yet.</pre>
        </section>

        <section class="panel">
          <h3>ChatGPT Fallback</h3>
          <p>Manage the OpenAI API key used when the local knowledge base has no strong answer. Saving a key overrides `.env`, reset returns to `.env`, and disable turns the fallback off.</p>
          <input id="openai-api-key" class="field" type="password" placeholder="Paste a new OpenAI API key">
          <div class="row" style="margin-top:16px">
            <button id="save-openai-key" class="btn primary" type="button">Save Key</button>
            <button id="load-openai" class="btn secondary" type="button">Refresh Status</button>
            <button id="reset-openai" class="btn secondary" type="button">Use .env Key</button>
            <button id="disable-openai" class="btn secondary" type="button">Disable Fallback</button>
          </div>
          <pre id="openai-output">OpenAI fallback status not loaded yet.</pre>
        </section>

        <section class="panel">
          <h3>Upload Local Sources</h3>
          <p>Add new `.md` or `.txt` files to extend the assistant without any database or vector service.</p>
          <input id="document-files" class="field" type="file" multiple accept=".md,.txt">
          <div class="row" style="margin-top:16px">
            <button id="upload-files" class="btn primary" type="button">Upload and Index</button>
          </div>
          <pre id="upload-output">No uploads yet.</pre>
        </section>

        <section class="panel">
          <h3>Analytics Summary</h3>
          <p>See how the assistant is being used from the flat-file analytics log.</p>
          <div class="row" style="margin-top:16px">
            <button id="load-analytics" class="btn primary" type="button">Load Analytics</button>
          </div>
          <pre id="analytics-output">Analytics not loaded yet.</pre>
        </section>

        <section class="panel full">
          <h3>Decision Insights</h3>
          <p>See the evidence score, strategy, source, and handoff logic behind each recent chatbot decision.</p>
          <div class="row" style="margin-top:16px">
            <button id="load-decisions" class="btn primary" type="button">Refresh Decision Metrics</button>
          </div>
          <div id="decision-summary" class="summary-grid">
            <div class="summary-tile"><strong>--</strong><span>Decision metrics not loaded yet.</span></div>
          </div>
          <div id="decision-breakdown" class="records">
            <div class="record"><strong>No decision breakdown loaded</strong><div>Load the analytics panel to inspect answer, escalation, and fallback behaviour.</div></div>
          </div>
          <div id="decision-output" class="records">
            <div class="record"><strong>No recent decisions loaded</strong><div>Recent query decisions will appear here.</div></div>
          </div>
        </section>

        <section class="panel full">
          <h3>Knowledge Gap Inbox</h3>
          <p>Review repeated no-match, ChatGPT fallback, weak-match, and poorly rated questions so you know what local knowledge to add next.</p>
          <div class="row" style="margin-top:16px">
            <button id="load-gaps" class="btn primary" type="button">Load Inbox</button>
          </div>
          <pre id="gaps-summary">Knowledge gap inbox not loaded yet.</pre>
          <div id="gaps-output" class="records"></div>
        </section>

        <section class="panel full">
          <h3>Recent Feedback</h3>
          <p>Review the latest helpful and not-helpful responses captured by the public UI.</p>
          <div class="row" style="margin-top:16px">
            <button id="load-feedback" class="btn primary" type="button">Load Feedback</button>
          </div>
          <div id="feedback-output" class="records"></div>
        </section>
      </div>
    </div>
  </div>

  <script>
    const API = {
      login: "./api/admin/login.php",
      logout: "./api/admin/logout.php",
      session: "./api/admin/session.php",
      openai: "./api/admin/openai.php",
      gaps: "./api/admin/gaps.php",
      analytics: "./api/admin/analytics.php",
      feedback: "./api/admin/feedback.php",
      knowledge: "./api/admin/knowledge.php",
      rebuild: "./api/admin/rebuild.php",
      upload: "./api/admin/upload.php"
    };

    const loginView = document.getElementById("login-view");
    const adminApp = document.getElementById("admin-app");
    const logoutButton = document.getElementById("logout-button");
    const sessionDot = document.getElementById("session-dot");
    const sessionLabel = document.getElementById("session-label");

    function setAuthenticated(authenticated, session = {}) {
      loginView.hidden = authenticated;
      adminApp.hidden = !authenticated;
      logoutButton.hidden = !authenticated;
      sessionDot.classList.toggle("online", Boolean(authenticated));
      sessionLabel.textContent = authenticated ? "Signed in via PHP session" : "Sign-in required";
      document.getElementById("session-output").textContent = JSON.stringify(session, null, 2);
    }

    async function request(url, options = {}) {
      const response = await fetch(url, options);
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        const error = new Error(payload.detail || `Request failed with status ${response.status}`);
        error.status = response.status;
        error.payload = payload;
        throw error;
      }
      return payload;
    }

    function renderError(targetId, error) {
      const target = document.getElementById(targetId);
      if (target) {
        target.textContent = error.message || String(error);
      }
    }

    function escapeHtml(value) {
      return String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
    }

    function formatBreakdown(counts) {
      const entries = Object.entries(counts || {});
      if (!entries.length) {
        return "No data yet.";
      }
      return entries
        .slice(0, 6)
        .map(([label, count]) => `${label}: ${count}`)
        .join(" | ");
    }

    function formatPercent(value) {
      if (typeof value !== "number" || Number.isNaN(value)) {
        return "n/a";
      }
      return `${Math.round(value * 100)}%`;
    }

    function renderOpenAIStatus(payload) {
      return [
        `Configured: ${payload.configured ? "yes" : "no"}`,
        `Ready: ${payload.ready ? "yes" : "no"}`,
        `Fallback enabled: ${payload.fallback_enabled ? "yes" : "no"}`,
        `Source: ${payload.source || "none"}`,
        `Management mode: ${payload.management_mode || "inherit"}`,
        `Model: ${payload.model || "n/a"}`,
        `API key: ${payload.api_key_masked || "not set"}`,
        `Status code: ${payload.status_code ?? "n/a"}`,
        `Detail: ${payload.detail || "n/a"}`,
        payload.last_error ? `Last error: ${payload.last_error}` : null
      ].filter(Boolean).join("\n");
    }

    function renderDecisionInsights(payload) {
      const summary = payload.decision_summary || {};
      const breakdowns = payload.decision_breakdowns || {};
      const summaryContainer = document.getElementById("decision-summary");
      const breakdownContainer = document.getElementById("decision-breakdown");
      const decisionsContainer = document.getElementById("decision-output");

      summaryContainer.innerHTML = [
        { value: summary.decision_event_count ?? 0, label: "Measured decision events" },
        { value: summary.average_evidence_score ?? "--", label: "Average evidence score" },
        { value: summary.local_answer_count ?? 0, label: "Answered locally" },
        { value: summary.escalated_query_count ?? 0, label: "Escalated with handoff" },
        { value: summary.openai_fallback_count ?? 0, label: "ChatGPT fallbacks" },
        { value: summary.verified_handoff_only_count ?? 0, label: "Verified handoff only" },
        { value: summary.verification_required_count ?? 0, label: "Verification required" }
      ].map((item) => `
        <div class="summary-tile">
          <strong>${escapeHtml(item.value)}</strong>
          <span>${escapeHtml(item.label)}</span>
        </div>
      `).join("");

      breakdownContainer.innerHTML = [
        { title: "Decision strategies", value: formatBreakdown(breakdowns.strategy) },
        { title: "Decision sources", value: formatBreakdown(breakdowns.source) },
        { title: "Evidence bands", value: formatBreakdown(breakdowns.evidence_label) },
        { title: "Handoff modes", value: formatBreakdown(breakdowns.handoff_mode) },
        { title: "Answer modes", value: formatBreakdown(breakdowns.answer_mode) },
        { title: "Query types", value: formatBreakdown(breakdowns.query_type) }
      ].map((item) => `
        <div class="record">
          <strong>${escapeHtml(item.title)}</strong>
          <div>${escapeHtml(item.value)}</div>
        </div>
      `).join("");

      if (!payload.recent_decisions?.length) {
        decisionsContainer.innerHTML = `<div class="record"><strong>No recent decisions</strong><div>The analytics log does not have decision records yet.</div></div>`;
        return;
      }

      decisionsContainer.innerHTML = "";
      payload.recent_decisions.forEach((record) => {
        const item = document.createElement("div");
        const missingTokens = Array.isArray(record.missing_specific_tokens) && record.missing_specific_tokens.length
          ? `Missing specific tokens: ${record.missing_specific_tokens.join(", ")}`
          : "";
        const verificationText = record.verification_required ? "required" : "not required";
        item.className = "record";
        item.innerHTML = `
          <strong>${escapeHtml(record.query || "Unlabelled question")}</strong>
          <div class="record-meta">
            ${escapeHtml(record.timestamp || "n/a")} | Intent: ${escapeHtml(record.intent || "general")} | Language: ${escapeHtml(record.language || "en")} | Query type: ${escapeHtml(record.query_type || "unknown")}
          </div>
          <div class="pill-row">
            <span class="pill">Strategy: ${escapeHtml(record.decision_strategy || "unknown")}</span>
            <span class="pill">Source: ${escapeHtml(record.decision_source || "unknown")}</span>
            <span class="pill">Answer mode: ${escapeHtml(record.answer_mode || "unknown")}</span>
            <span class="pill">Evidence: ${escapeHtml(record.evidence_score ?? "n/a")} (${escapeHtml(record.evidence_label || "unknown")})</span>
            <span class="pill">Coverage: ${escapeHtml(formatPercent(record.local_coverage))}</span>
            <span class="pill">Section focus: ${escapeHtml(formatPercent(record.section_focus))}</span>
            <span class="pill">Category match: ${escapeHtml(formatPercent(record.category_consistency))}</span>
            <span class="pill">Verification: ${escapeHtml(verificationText)}</span>
            <span class="pill">Handoff: ${escapeHtml(record.handoff_mode || "none")}</span>
            <span class="pill">Office: ${escapeHtml(record.handoff_office || "n/a")}</span>
            <span class="pill">Matches: ${escapeHtml(record.result_count ?? "n/a")}</span>
            <span class="pill">Top score: ${escapeHtml(record.top_score ?? "n/a")}</span>
            <span class="pill">OpenAI: ${escapeHtml(record.openai_used ? "used" : "not used")}</span>
            ${record.openai_model ? `<span class="pill">Model: ${escapeHtml(record.openai_model)}</span>` : ""}
          </div>
          ${missingTokens ? `<div class="record-meta">${escapeHtml(missingTokens)}</div>` : ""}
        `;
        decisionsContainer.appendChild(item);
      });
    }

    async function restoreSession() {
      try {
        const payload = await request(API.session);
        setAuthenticated(Boolean(payload.authenticated), payload);
        document.getElementById("login-output").textContent = JSON.stringify(payload, null, 2);
        if (payload.authenticated) {
          await loadKnowledge();
          await loadOpenAISettings();
          await loadAnalytics();
          await loadKnowledgeGaps();
        }
      } catch (error) {
        document.getElementById("login-output").textContent = error.message;
      }
    }

    async function signIn() {
      const adminKey = document.getElementById("admin-key").value.trim();
      const payload = await request(API.login, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ admin_key: adminKey })
      });
      document.getElementById("login-output").textContent = JSON.stringify(payload, null, 2);
      setAuthenticated(true, payload);
      await loadKnowledge();
      await loadOpenAISettings();
      await loadAnalytics();
      await loadKnowledgeGaps();
    }

    async function signOut() {
      const payload = await request(API.logout, { method: "POST" });
      setAuthenticated(false, payload);
      document.getElementById("login-output").textContent = JSON.stringify(payload, null, 2);
    }

    async function loadSession() {
      const payload = await request(API.session);
      setAuthenticated(Boolean(payload.authenticated), payload);
    }

    async function loadKnowledge() {
      const payload = await request(API.knowledge);
      document.getElementById("knowledge-output").textContent = JSON.stringify(payload, null, 2);
    }

    async function loadOpenAISettings() {
      const payload = await request(API.openai);
      document.getElementById("openai-output").textContent = renderOpenAIStatus(payload);
      document.getElementById("openai-api-key").value = "";
    }

    async function saveOpenAISettings() {
      const apiKey = document.getElementById("openai-api-key").value.trim();
      const payload = await request(API.openai, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "save", api_key: apiKey })
      });
      document.getElementById("openai-output").textContent = renderOpenAIStatus(payload);
      document.getElementById("openai-api-key").value = "";
    }

    async function resetOpenAISettings() {
      const payload = await request(API.openai, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "reset" })
      });
      document.getElementById("openai-output").textContent = renderOpenAIStatus(payload);
      document.getElementById("openai-api-key").value = "";
    }

    async function disableOpenAISettings() {
      const payload = await request(API.openai, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "disable" })
      });
      document.getElementById("openai-output").textContent = renderOpenAIStatus(payload);
      document.getElementById("openai-api-key").value = "";
    }

    async function rebuildKnowledge() {
      const payload = await request(API.rebuild, { method: "POST" });
      document.getElementById("knowledge-output").textContent = JSON.stringify(payload, null, 2);
    }

    async function uploadFiles() {
      const files = document.getElementById("document-files").files;
      if (!files.length) {
        throw new Error("Choose at least one .md or .txt file first.");
      }
      const formData = new FormData();
      Array.from(files).forEach((file) => formData.append("documents[]", file));
      const payload = await request(API.upload, { method: "POST", body: formData });
      document.getElementById("upload-output").textContent = JSON.stringify(payload, null, 2);
      await loadKnowledge();
    }

    async function loadAnalytics() {
      const payload = await request(`${API.analytics}?limit=20`);
      document.getElementById("analytics-output").textContent = [
        `Total queries: ${payload.total_queries ?? 0}`,
        `Measured decision events: ${payload.decision_summary?.decision_event_count ?? 0}`,
        `Top intents: ${formatBreakdown(payload.intent_breakdown)}`,
        `Languages: ${formatBreakdown(payload.language_breakdown)}`,
        `Answer modes: ${formatBreakdown(payload.decision_breakdowns?.answer_mode)}`,
        `Strategies: ${formatBreakdown(payload.decision_breakdowns?.strategy)}`
      ].join("\n");
      renderDecisionInsights(payload);
    }

    async function loadKnowledgeGaps() {
      const payload = await request(API.gaps);
      document.getElementById("gaps-summary").textContent = [
        `Total review items: ${payload.summary?.total_items ?? 0}`,
        `High priority: ${payload.summary?.high_priority ?? 0}`,
        `ChatGPT-reliant topics: ${payload.summary?.chatgpt_reliant ?? 0}`,
        `Negative feedback topics: ${payload.summary?.negative_feedback ?? 0}`
      ].join("\n");

      const container = document.getElementById("gaps-output");
      container.innerHTML = "";

      if (!payload.records?.length) {
        container.innerHTML = `<div class="record"><strong>No gaps detected</strong><div>The inbox is clear for the current analytics and feedback logs.</div></div>`;
        return;
      }

      payload.records.forEach((record) => {
        const item = document.createElement("div");
        item.className = "record";
        item.innerHTML = `
          <strong>${escapeHtml(record.query || "Unlabelled question")}</strong>
          <div>${escapeHtml(record.reason || "")}</div>
          <div style="margin-top:8px;color:#667085">
            Priority: ${escapeHtml(record.priority || "low")} | Seen: ${Number(record.occurrence_count || 0)} | Weak matches: ${Number(record.weak_match_count || 0)} | ChatGPT fallback: ${Number(record.openai_fallback_count || 0)} | Needs Work: ${Number(record.not_helpful_count || 0)}
          </div>
          <div style="margin-top:8px;color:#667085">
            Intent: ${escapeHtml(record.intent || "general")} | Last seen: ${escapeHtml(record.last_seen || "n/a")}
          </div>
          <div style="margin-top:8px;color:#18233d">${escapeHtml(record.suggested_action || "")}</div>
        `;
        container.appendChild(item);
      });
    }

    async function loadFeedback() {
      const payload = await request(API.feedback);
      const container = document.getElementById("feedback-output");
      container.innerHTML = "";

      if (!payload.records?.length) {
        container.innerHTML = `<div class="record"><strong>No feedback yet</strong><div>The public UI has not submitted feedback yet.</div></div>`;
        return;
      }

      payload.records.forEach((record) => {
        const item = document.createElement("div");
        item.className = "record";
        item.innerHTML = `
          <strong>${record.helpful ? "Helpful" : "Needs Work"}</strong>
          <div>${escapeHtml(record.user_query || "")}</div>
          <div style="margin-top:8px">${escapeHtml(record.message_content || "")}</div>
          <div style="margin-top:8px;color:#667085">${record.timestamp || ""}</div>
        `;
        container.appendChild(item);
      });
    }

    document.getElementById("login-form").addEventListener("submit", async (event) => {
      event.preventDefault();
      try {
        await signIn();
      } catch (error) {
        document.getElementById("login-output").textContent = error.message;
      }
    });

    document.getElementById("check-session").addEventListener("click", () => {
      restoreSession().catch((error) => renderError("login-output", error));
    });
    document.getElementById("logout-button").addEventListener("click", () => {
      signOut().catch((error) => renderError("login-output", error));
    });
    document.getElementById("load-session").addEventListener("click", () => {
      loadSession().catch((error) => renderError("session-output", error));
    });
    document.getElementById("load-knowledge").addEventListener("click", () => {
      loadKnowledge().catch((error) => renderError("knowledge-output", error));
    });
    document.getElementById("load-openai").addEventListener("click", () => {
      loadOpenAISettings().catch((error) => renderError("openai-output", error));
    });
    document.getElementById("load-gaps").addEventListener("click", () => {
      loadKnowledgeGaps().catch((error) => renderError("gaps-summary", error));
    });
    document.getElementById("save-openai-key").addEventListener("click", () => {
      saveOpenAISettings().catch((error) => renderError("openai-output", error));
    });
    document.getElementById("reset-openai").addEventListener("click", () => {
      resetOpenAISettings().catch((error) => renderError("openai-output", error));
    });
    document.getElementById("disable-openai").addEventListener("click", () => {
      disableOpenAISettings().catch((error) => renderError("openai-output", error));
    });
    document.getElementById("rebuild-knowledge").addEventListener("click", () => {
      rebuildKnowledge().catch((error) => renderError("knowledge-output", error));
    });
    document.getElementById("upload-files").addEventListener("click", () => {
      uploadFiles().catch((error) => renderError("upload-output", error));
    });
    document.getElementById("load-analytics").addEventListener("click", () => {
      loadAnalytics().catch((error) => renderError("analytics-output", error));
    });
    document.getElementById("load-decisions").addEventListener("click", () => {
      loadAnalytics().catch((error) => renderError("analytics-output", error));
    });
    document.getElementById("load-feedback").addEventListener("click", () => {
      loadFeedback().catch((error) => {
        const container = document.getElementById("feedback-output");
        container.innerHTML = `<div class="record"><strong>Request failed</strong><div>${error.message}</div></div>`;
      });
    });

    restoreSession().catch((error) => renderError("login-output", error));
  </script>
</body>
</html>
