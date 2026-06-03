(function () {
  "use strict";

  const STORAGE_KEY = "multch-plugin-session-v1";
  const OPEN_KEY = "multch-plugin-open-state-v1";
  const SESSION_KEY = "multch-plugin-anon-id";
  const CONV_KEY = "multch-plugin-conversation-v1";
  const HISTORY_TTL_MS = 24 * 60 * 60 * 1000;
  const MAX_MESSAGES = 60;

  const config = window.multchPluginConfig || {};
  const i18n = config.i18n || {};
  const ROOT_SELECTOR = "[data-maicb-root]";

  function injectThinkingAnimationStyles() {
    if (document.getElementById("maicb-thinking-keyframes")) {
      return;
    }
    const style = document.createElement("style");
    style.id = "maicb-thinking-keyframes";
    style.textContent =
      "@keyframes maicb-typing-pulse{" +
      "0%,70%,100%{opacity:.28;transform:scale(.82)}" +
      "35%{opacity:1;transform:scale(1)}" +
      "}" +
      "@keyframes maicb-typing-fade{" +
      "0%,100%{opacity:.3}50%{opacity:1}" +
      "}" +
      ".maicb-widget .maicb-thinking-dots{" +
      "display:inline-flex;align-items:center;gap:.3rem;height:1.1rem}" +
      ".maicb-widget .maicb-thinking-dot{" +
      "display:block;width:.4rem;height:.4rem;border-radius:50%;" +
      "background:currentColor;" +
      "animation:maicb-typing-pulse 1.35s ease-in-out infinite}" +
      ".maicb-widget .maicb-thinking-dot:nth-child(2){animation-delay:.2s}" +
      ".maicb-widget .maicb-thinking-dot:nth-child(3){animation-delay:.4s}" +
      ".maicb-widget.maicb-reduce-motion .maicb-thinking-dot{" +
      "animation:maicb-typing-fade 1.5s ease-in-out infinite;transform:none}" +
      "@media (prefers-reduced-motion:reduce){" +
      ".maicb-widget .maicb-thinking-dot{" +
      "animation:maicb-typing-fade 1.5s ease-in-out infinite;transform:none}" +
      "}";
    document.head.appendChild(style);
  }

  injectThinkingAnimationStyles();

  function escapeHtml(text) {
    return String(text)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function sanitizeLinkUrl(url) {
    const raw = String(url || "").trim();
    if (!raw) {
      return "";
    }
    try {
      const parsed = new URL(raw, window.location.href);
      if (
        parsed.protocol === "http:" ||
        parsed.protocol === "https:" ||
        parsed.protocol === "mailto:"
      ) {
        return parsed.href;
      }
    } catch (_) {
      return "";
    }
    return "";
  }

  function formatInlineMarkdown(raw) {
    let text = escapeHtml(raw);
    const tokens = [];

    function stash(html) {
      const index = tokens.length;
      tokens.push(html);
      return "\uE000" + index + "\uE001";
    }

    text = text.replace(/`([^`\n]+)`/g, function (_, code) {
      return stash("<code>" + code + "</code>");
    });
    text = text.replace(/\*\*([^*]+)\*\*/g, function (_, inner) {
      return stash("<strong>" + inner + "</strong>");
    });
    text = text.replace(/__([^_]+)__/g, function (_, inner) {
      return stash("<strong>" + inner + "</strong>");
    });
    text = text.replace(/\*([^*\n]+)\*/g, function (_, inner) {
      return stash("<em>" + inner + "</em>");
    });
    text = text.replace(/_([^_\n]+)_/g, function (_, inner) {
      return stash("<em>" + inner + "</em>");
    });
    text = text.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (_, label, url) {
      const safeUrl = sanitizeLinkUrl(url);
      if (!safeUrl) {
        return label;
      }
      return stash(
        '<a href="' +
          escapeHtml(safeUrl) +
          '" target="_blank" rel="noopener noreferrer">' +
          label +
          "</a>"
      );
    });
    text = text.replace(/\uE000(\d+)\uE001/g, function (_, index) {
      return tokens[Number(index)] || "";
    });
    return text;
  }

  function isBlockStarter(line) {
    const trimmed = line.trim();
    if (!trimmed) {
      return false;
    }
    if (/^```/.test(trimmed)) {
      return true;
    }
    if (/^#{1,3}\s+/.test(trimmed)) {
      return true;
    }
    if (/^[*+\-]\s+/.test(line)) {
      return true;
    }
    if (/^\d+\.\s+/.test(line)) {
      return true;
    }
    return false;
  }

  function renderMarkdown(text) {
    const lines = String(text || "").replace(/\r\n/g, "\n").split("\n");
    const blocks = [];
    let index = 0;

    while (index < lines.length) {
      const line = lines[index];
      if (!line.trim()) {
        index += 1;
        continue;
      }

      const fence = line.trim().match(/^```(\w*)$/);
      if (fence) {
        index += 1;
        const codeLines = [];
        while (index < lines.length && !/^```\s*$/.test(lines[index].trim())) {
          codeLines.push(lines[index]);
          index += 1;
        }
        if (index < lines.length) {
          index += 1;
        }
        blocks.push("<pre><code>" + escapeHtml(codeLines.join("\n")) + "</code></pre>");
        continue;
      }

      if (/^[*+\-]\s+/.test(line)) {
        const items = [];
        while (index < lines.length && /^[*+\-]\s+/.test(lines[index])) {
          const match = lines[index].match(/^[*+\-]\s+(.+)$/);
          items.push("<li>" + formatInlineMarkdown(match ? match[1] : "") + "</li>");
          index += 1;
        }
        blocks.push("<ul>" + items.join("") + "</ul>");
        continue;
      }

      if (/^\d+\.\s+/.test(line)) {
        const items = [];
        while (index < lines.length && /^\d+\.\s+/.test(lines[index])) {
          const match = lines[index].match(/^\d+\.\s+(.+)$/);
          items.push("<li>" + formatInlineMarkdown(match ? match[1] : "") + "</li>");
          index += 1;
        }
        blocks.push("<ol>" + items.join("") + "</ol>");
        continue;
      }

      const heading = line.trim().match(/^(#{1,3})\s+(.+)$/);
      if (heading) {
        const level = heading[1].length;
        blocks.push(
          "<h" + level + ">" + formatInlineMarkdown(heading[2]) + "</h" + level + ">"
        );
        index += 1;
        continue;
      }

      const paragraph = [];
      while (index < lines.length && lines[index].trim() && !isBlockStarter(lines[index])) {
        paragraph.push(lines[index]);
        index += 1;
      }
      blocks.push(
        "<p>" + paragraph.map((part) => formatInlineMarkdown(part)).join("<br>") + "</p>"
      );
    }

    return blocks.join("");
  }

  function setAssistantBubbleContent(bubble, content) {
    bubble.classList.add("maicb-msg-rich");
    const body = document.createElement("div");
    body.className = "maicb-msg-body";
    body.innerHTML = renderMarkdown(content);
    bubble.appendChild(body);
  }

  function q(scope, name) {
    return scope.querySelector('[data-maicb="' + name + '"]');
  }

  function appendDeveloperCredit(composer, style) {
    if (!composer || !style || !style.showCredit) {
      return;
    }
    const credit = config.credit || {};
    if (!credit.productUrl) {
      return;
    }

    const line = document.createElement("p");
    line.className = "maicb-credit";
    line.dataset.maicb = "credit";
    line.setAttribute("role", "contentinfo");

    const productLink = document.createElement("a");
    productLink.href = credit.productUrl;
    productLink.target = "_blank";
    productLink.rel = "noopener noreferrer";
    productLink.textContent = credit.productName || "MultiAI Chatbot";

    const authorLink = document.createElement("a");
    authorLink.href = credit.authorUrl || credit.productUrl;
    authorLink.target = "_blank";
    authorLink.rel = "noopener noreferrer";
    authorLink.textContent = credit.authorName || "Jsravelo";

    line.appendChild(productLink);
    line.appendChild(document.createTextNode(" \u00b7 "));
    line.appendChild(authorLink);
    composer.appendChild(line);
  }

  function prepareRoot(root) {
    if (!root.dataset.maicbRoot) {
      root.dataset.maicbRoot = "1";
    }
    if (!root.id) {
      root.id = "multch-plugin-root";
    }
    if (!root.classList.contains("maicb-root")) {
      root.classList.add("maicb-root");
    }
  }

  function launcherMarkup(showLabel, labelText) {
    return (
      '<span class="maicb-launcher-icon-wrap" aria-hidden="true">' +
      '<span class="maicb-launcher-pulse"></span>' +
      '<span class="maicb-launcher-icon">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>' +
      '<path d="M8 10h.01"/><path d="M12 10h.01"/><path d="M16 10h.01"/>' +
      "</svg></span></span>" +
      (showLabel ? '<span class="maicb-launcher-text">' + labelText + "</span>" : "")
    );
  }

  function buildHeaderHtml(labels) {
    const resetLabel = labels.resetLabel || "Reset chat";
    const minimizeLabel = labels.minimizeLabel || "Minimize chat";
    const closeLabel = labels.closeLabel || "Close chat";
    return (
      '<div class="maicb-header-brand">' +
      '<span class="maicb-header-avatar" aria-hidden="true">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M12 8V4H8"/><path d="M16 12h2"/><path d="M6 12H4"/>' +
      '<rect width="16" height="12" x="4" y="8" rx="2"/><path d="M9 13v2"/><path d="M15 13v2"/>' +
      "</svg></span>" +
      '<div class="maicb-header-info">' +
      '<h3 class="maicb-header-title" data-maicb="header-title"></h3>' +
      '<p class="maicb-header-sub"><span class="maicb-header-status" aria-hidden="true"></span>' +
      '<span class="maicb-header-sub-text" data-maicb="header-sub"></span></p>' +
      "</div></div>" +
      '<div class="maicb-header-actions">' +
      '<button type="button" class="maicb-icon-btn maicb-minimize" data-maicb="minimize" title="' +
      minimizeLabel +
      '" aria-label="' +
      minimizeLabel +
      '">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
      '<path d="M5 12h14"/>' +
      "</svg></button>" +
      '<button type="button" class="maicb-icon-btn maicb-reset" data-maicb="reset" title="' +
      resetLabel +
      '" aria-label="' +
      resetLabel +
      '">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>' +
      "</svg></button>" +
      '<button type="button" class="maicb-icon-btn maicb-close" data-maicb="close" title="' +
      closeLabel +
      '" aria-label="' +
      closeLabel +
      '">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
      '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>' +
      "</svg></button></div>"
    );
  }

  function generateId() {
    return "m-" + Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 8);
  }

  function getConversationId() {
    try {
      return localStorage.getItem(CONV_KEY) || "";
    } catch {
      return "";
    }
  }

  function setConversationId(id) {
    if (!id) return;
    try {
      localStorage.setItem(CONV_KEY, String(id));
    } catch {
      /* ignore */
    }
  }

  function clearConversationId() {
    try {
      localStorage.removeItem(CONV_KEY);
    } catch {
      /* ignore */
    }
  }

  function getSessionId() {
    try {
      let id = localStorage.getItem(SESSION_KEY);
      if (!id) {
        id = typeof crypto !== "undefined" && crypto.randomUUID
          ? crypto.randomUUID()
          : "sess-" + Date.now();
        localStorage.setItem(SESSION_KEY, id);
      }
      return id;
    } catch {
      return "anonymous-session";
    }
  }

  function loadState() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return { messages: [], updatedAt: new Date().toISOString() };
      const parsed = JSON.parse(raw);
      const updated = new Date(parsed.updatedAt || 0).getTime();
      if (Date.now() - updated > HISTORY_TTL_MS) {
        clearConversationId();
        return { messages: [], updatedAt: new Date().toISOString() };
      }
      return {
        messages: Array.isArray(parsed.messages) ? parsed.messages.slice(-MAX_MESSAGES) : [],
        updatedAt: parsed.updatedAt || new Date().toISOString(),
      };
    } catch {
      return { messages: [], updatedAt: new Date().toISOString() };
    }
  }

  function saveState(messages) {
    try {
      localStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({
          messages: messages.slice(-MAX_MESSAGES),
          updatedAt: new Date().toISOString(),
        })
      );
    } catch {
      /* ignore */
    }
  }

  function loadOpenState() {
    try {
      return localStorage.getItem(OPEN_KEY) === "1";
    } catch {
      return false;
    }
  }

  function saveOpenState(open) {
    try {
      localStorage.setItem(OPEN_KEY, open ? "1" : "0");
    } catch {
      /* ignore */
    }
  }

  const PLUGIN_RATE_LIMIT_CODES = new Set([
    "RATE_LIMIT_GENERAL",
    "RATE_LIMIT_MODEL_MINUTE",
    "RATE_LIMIT_MODEL_DAILY",
    "IP_SUSPENDED",
  ]);

  function mapErrorMessage(code) {
    const map = {
      RATE_LIMIT_GENERAL: "Too many requests. Please wait a moment.",
      RATE_LIMIT_MODEL_MINUTE: "This site’s chat limit for AI messages was reached. Wait a moment before sending another.",
      RATE_LIMIT_MODEL_DAILY: "This site’s daily chat limit for AI messages was reached. Try again later.",
      RATE_LIMIT_PROVIDER: "The AI provider rate limit was reached. Try again shortly.",
      QUOTA_EXHAUSTED: "The AI provider quota was reached. The chat tried your configured models. Wait a few minutes or change models in MultiAI ChatBot → AI Model.",
      MODEL_ALL_EXHAUSTED: "All models are temporarily saturated.",
      MODEL_TEMP_UNAVAILABLE: "The model did not return a valid response. Check Connectors and the model in AI Model settings.",
      PROVIDER_TIMEOUT: "The provider took too long to respond.",
      PROVIDER_UPSTREAM: "AI provider error.",
      ORIGIN_FORBIDDEN: "Unauthorized request.",
      INVALID_REQUEST: "Invalid message.",
      CONFIGURATION_ERROR: "The AI service is not configured.",
      SERVER_ERROR: "Internal server error.",
    };
    return map[code] || i18n.errorGeneric || "Could not send the message.";
  }

  function truncateMessage(message, maxLen) {
    if (!message || typeof message !== "string") {
      return message;
    }
    if (message.length > maxLen) {
      return message.slice(0, maxLen) + "…";
    }
    return message;
  }

  function resolveErrorMessage(err) {
    const code = err && err.code;
    const rawMessage = err && err.message;

    if (code && PLUGIN_RATE_LIMIT_CODES.has(code)) {
      return mapErrorMessage(code);
    }

    if (code && code !== "UNKNOWN" && code !== "PROVIDER_UPSTREAM" && code !== "SERVER_ERROR") {
      if (code === "RATE_LIMIT_PROVIDER" || code === "QUOTA_EXHAUSTED") {
        if (
          rawMessage &&
          /exceeded your (current )?quota|check your plan and billing|resource exhausted|quota exceeded/i.test(
            rawMessage
          )
        ) {
          return truncateMessage(rawMessage, 220);
        }
      }
      return mapErrorMessage(code);
    }

    return sanitizeErrorMessage(rawMessage, err && err.status) || mapErrorMessage(code);
  }

  function sanitizeErrorMessage(message, status) {
    if (!message || typeof message !== "string") {
      if (status === 502 || status === 504) {
        return "The server could not complete the request. Check the chatbot configuration or try again later.";
      }
      return message;
    }
    if (/<!doctype html/i.test(message) || /<html/i.test(message)) {
      return "The server returned an error (502). Leave the internal chat URL empty and verify the DeepSeek API key.";
    }
    if (
      /exceeded your (current )?quota|check your plan and billing|resource exhausted|quota exceeded/i.test(
        message
      )
    ) {
      return truncateMessage(message, 220);
    }
    if (message.length > 220) {
      return message.slice(0, 220) + "…";
    }
    return message;
  }

  const PRESET_IDS = [
    "default",
    "dark-glass",
    "obsidian",
    "minimal",
    "ocean",
    "sunset",
    "forest",
    "lavender",
    "plum",
  ];

  function getStyleForRoot(root) {
    const base = config.style || {};
    const style = {
      preset: base.preset,
      position: base.position,
      offset: base.offset,
      panelWidth: base.panelWidth,
      panelMaxHeight: base.panelMaxHeight,
      launcherLabel: base.launcherLabel,
      showCredit: !!base.showCredit,
      showWelcomeLabel: base.showWelcomeLabel !== false,
      showModelLabel: base.showModelLabel !== false,
      fontFamily: base.fontFamily,
      zIndex: base.zIndex,
      reduceMotion: base.reduceMotion,
      presetAuto: base.presetAuto,
      presetAutoDark: base.presetAutoDark,
      vars: base.vars ? Object.assign({}, base.vars) : {},
    };
    const raw = root.getAttribute("data-style-override");
    if (raw) {
      try {
        const override = JSON.parse(raw);
        Object.keys(override).forEach(function (key) {
          if (key === "vars" && override.vars) {
            style.vars = Object.assign({}, style.vars, override.vars);
          } else if (key !== "vars") {
            style[key] = override[key];
          }
        });
      } catch (e) {
        /* ignore invalid JSON */
      }
    }
    return style;
  }

  function applyPresetClasses(wrap, style) {
    PRESET_IDS.forEach(function (p) {
      wrap.classList.remove("maicb-preset-" + p);
    });
    const light = style.preset || "default";
    const dark = style.presetAutoDark || "dark-glass";
    if (!style.presetAuto) {
      wrap.classList.add("maicb-preset-" + light);
      wrap.dataset.preset = light;
      return;
    }
    const mq = window.matchMedia("(prefers-color-scheme: dark)");
    const apply = function () {
      PRESET_IDS.forEach(function (p) {
        wrap.classList.remove("maicb-preset-" + p);
      });
      const active = mq.matches ? dark : light;
      wrap.classList.add("maicb-preset-" + active);
      wrap.dataset.preset = active;
    };
    apply();
    if (typeof mq.addEventListener === "function") {
      mq.addEventListener("change", apply);
    } else if (typeof mq.addListener === "function") {
      mq.addListener(apply);
    }
  }

  function applyStyleVars(el, style, rootEl) {
    if (!style) return;
    const varKeys = ["primary", "accent", "radius", "bg", "fg"];
    const cssMap = {
      primary: "--maicb-primary",
      accent: "--maicb-accent",
      radius: "--maicb-radius",
      bg: "--maicb-bg",
      fg: "--maicb-fg",
    };
    const bgDerivedVars = ["--maicb-body-bg", "--maicb-card", "--maicb-composer-bg"];
    const fgDerivedVars = ["--maicb-header-title-color"];
    varKeys.forEach(function (key) {
      el.style.removeProperty(cssMap[key]);
    });
    bgDerivedVars.forEach(function (cssVar) {
      el.style.removeProperty(cssVar);
    });
    fgDerivedVars.forEach(function (cssVar) {
      el.style.removeProperty(cssVar);
    });
    el.style.removeProperty("--maicb-offset");
    el.style.removeProperty("--maicb-panel-width");
    el.style.removeProperty("--maicb-panel-max-height");

    if (style.vars) {
      varKeys.forEach(function (key) {
        if (style.vars[key]) {
          el.style.setProperty(cssMap[key], style.vars[key]);
        }
      });
      if (style.vars.bg) {
        bgDerivedVars.forEach(function (cssVar) {
          el.style.setProperty(cssVar, style.vars.bg);
        });
      }
      if (style.vars.fg) {
        el.style.setProperty("--maicb-header-title-color", style.vars.fg);
      }
    }
    if (style.offset) el.style.setProperty("--maicb-offset", style.offset);
    if (style.panelWidth) el.style.setProperty("--maicb-panel-width", style.panelWidth);
    if (style.panelMaxHeight) {
      el.style.setProperty("--maicb-panel-max-height", style.panelMaxHeight);
    }
    if (style.fontFamily) {
      el.style.fontFamily = style.fontFamily;
    }
    if (rootEl) {
      if (style.zIndex) {
        rootEl.style.setProperty("--maicb-z-base", String(style.zIndex));
        rootEl.style.zIndex = String(style.zIndex);
      } else {
        rootEl.style.removeProperty("--maicb-z-base");
        rootEl.style.removeProperty("z-index");
      }
    }
  }

  /**
   * CSS class suffix for launcher placement (must match chatbot.css).
   */
  function launcherClassForPosition(position) {
    const allowed = {
      "bottom-right": "bottom-right",
      "center-right": "center-right",
      "bottom-left": "bottom-left",
      "center-left": "center-left",
      "bottom-center": "center",
    };
    return allowed[position] || "bottom-right";
  }

  function buildWelcomeMessage() {
    const text = (config.welcomeMessage || "").trim();
    if (!text) return null;
    return {
      id: "welcome",
      role: "assistant",
      content: text,
      createdAt: new Date().toISOString(),
      model: "system",
    };
  }

  function mountFloatingRoot(root) {
    if (!root || root.dataset.mode !== "floating") {
      return;
    }
    root.classList.add("maicb-is-floating");
    if (root.parentNode !== document.body) {
      document.body.appendChild(root);
    }
  }

  function initRoot(root) {
    if (root.dataset.maicbInitialized === "1") {
      return;
    }
    root.dataset.maicbInitialized = "1";
    prepareRoot(root);
    const mode = root.dataset.mode || config.mode || "floating";
    if (mode === "floating") {
      mountFloatingRoot(root);
    }
    const style = getStyleForRoot(root);
    const position = style.position || "bottom-right";
    const launcherLabel = style.launcherLabel !== false;

    const state = loadState();
    let messages = state.messages.length ? state.messages : [];
    const welcome = buildWelcomeMessage();
    if (welcome && !messages.some((m) => m.id === "welcome")) {
      messages = [welcome, ...messages];
    }

    let isOpen = mode === "inline" ? true : loadOpenState();
    let isSending = false;
    let errorText = "";

    const wrap = document.createElement("div");
    wrap.className = "maicb-widget maicb-wrap" + (mode === "inline" ? " maicb-inline-wrap" : "");
    if (style.reduceMotion) {
      wrap.classList.add("maicb-reduce-motion");
    }
    applyPresetClasses(wrap, style);
    applyStyleVars(wrap, style, root);

    const launcher = document.createElement("button");
    launcher.type = "button";
    launcher.dataset.maicb = "launcher";
    launcher.className =
      "maicb-launcher maicb-launcher-" +
      launcherClassForPosition(position) +
      (launcherLabel ? "" : " maicb-launcher--icon-only");
    launcher.setAttribute("aria-label", i18n.openLabel || "Open chat");
    launcher.innerHTML = launcherMarkup(launcherLabel, config.widgetTitle || "AI Agent");
    if (mode === "inline" || isOpen) launcher.hidden = true;

    const panel = document.createElement("section");
    panel.dataset.maicb = "panel";
    panel.className = "maicb-panel maicb-position-" + position;
    panel.setAttribute("aria-label", config.widgetTitle || "MultiAI ChatBot");
    if (!isOpen) panel.hidden = true;

    const header = document.createElement("header");
    header.dataset.maicb = "header";
    header.className = "maicb-header";
    header.innerHTML = buildHeaderHtml(i18n);

    const messagesEl = document.createElement("div");
    messagesEl.dataset.maicb = "messages";
    messagesEl.className = "maicb-messages";
    messagesEl.setAttribute("role", "log");
    messagesEl.setAttribute("aria-live", "polite");

    const errorEl = document.createElement("div");
    errorEl.dataset.maicb = "error";
    errorEl.className = "maicb-error";
    errorEl.hidden = true;

    const composer = document.createElement("form");
    composer.dataset.maicb = "composer";
    composer.className = "maicb-composer";
    composer.innerHTML =
      '<div class="maicb-composer-inner">' +
      '<textarea class="maicb-input" data-maicb="input" rows="1" placeholder="' +
      (i18n.placeholder || "Type your message…") +
      '" maxlength="700" aria-label="' +
      (i18n.placeholder || "Type your message…") +
      '"></textarea>' +
      '<button type="submit" class="maicb-send" data-maicb="send" aria-label="' +
      (i18n.send || "Send") +
      '">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>' +
      "</svg></button></div>";
    appendDeveloperCredit(composer, style);

    panel.appendChild(header);
    panel.appendChild(messagesEl);
    panel.appendChild(errorEl);
    panel.appendChild(composer);

    wrap.appendChild(launcher);
    wrap.appendChild(panel);
    root.appendChild(wrap);

    const input = q(root, "input");
    const sendBtn = q(root, "send");
    const minimizeBtn = q(root, "minimize");
    const closeBtn = q(root, "close");
    const resetBtn = q(root, "reset");

    q(root, "header-title").textContent = config.widgetTitle || "AI Agent";
    q(root, "header-sub").textContent = config.widgetSubtitle || i18n.onlineLabel || "System online";

    if (mode === "inline") {
      launcher.hidden = true;
      panel.hidden = false;
      isOpen = true;
      if (minimizeBtn) minimizeBtn.hidden = true;
      if (closeBtn) closeBtn.hidden = true;
    }

    function createMessageRow(msg, showThinking) {
      const role = msg.role || "assistant";

      if (role === "system") {
        const system = document.createElement("div");
        system.className = "maicb-msg maicb-msg-system";
        system.textContent = msg.content || "";
        return system;
      }

      const row = document.createElement("div");
      row.className = "maicb-msg-row maicb-msg-row-" + role;

      if (role === "assistant") {
        const avatar = document.createElement("span");
        avatar.className = "maicb-msg-avatar";
        avatar.setAttribute("aria-hidden", "true");
        avatar.innerHTML =
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' +
          '<path d="M12 8V4H8"/><path d="M16 12h2"/><path d="M6 12H4"/>' +
          '<rect width="16" height="12" x="4" y="8" rx="2"/><path d="M9 13v2"/><path d="M15 13v2"/>' +
          "</svg>";
        row.appendChild(avatar);
      }

      const bubble = document.createElement("div");
      bubble.className = "maicb-msg maicb-msg-" + role;

      if (showThinking) {
        bubble.classList.add("maicb-msg-pending");
        bubble.setAttribute("aria-label", i18n.thinking || "Thinking…");

        const thinking = document.createElement("div");
        thinking.className = "maicb-thinking";
        thinking.setAttribute("role", "status");
        thinking.setAttribute("aria-live", "polite");

        const dotsWrap = document.createElement("span");
        dotsWrap.className = "maicb-thinking-dots";
        dotsWrap.setAttribute("aria-hidden", "true");
        for (let i = 0; i < 3; i += 1) {
          const dot = document.createElement("span");
          dot.className = "maicb-thinking-dot";
          dotsWrap.appendChild(dot);
        }

        thinking.appendChild(dotsWrap);
        bubble.appendChild(thinking);
      } else if (role === "assistant") {
        setAssistantBubbleContent(bubble, msg.content || "");
        if (msg.id === "welcome" && style.showWelcomeLabel !== false) {
          const meta = document.createElement("span");
          meta.className = "maicb-msg-meta";
          meta.textContent = i18n.welcomeLabel || "Welcome message";
          bubble.appendChild(meta);
        } else if (
          style.showModelLabel !== false &&
          msg.model &&
          msg.model !== "system"
        ) {
          const meta = document.createElement("span");
          meta.className = "maicb-msg-meta";
          meta.textContent = msg.model;
          bubble.appendChild(meta);
        }
      } else {
        bubble.textContent = msg.content || "";
      }

      row.appendChild(bubble);
      return row;
    }

    function scrollToBottom() {
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function renderMessages() {
      const pendingIndex = isSending
        ? messages.findIndex(
            (msg, index) =>
              msg.role === "assistant" &&
              !(msg.content || "").trim() &&
              index === messages.length - 1
          )
        : -1;

      if (pendingIndex >= 0) {
        const rows = messagesEl.querySelectorAll(".maicb-msg-row");
        if (
          rows.length === messages.length &&
          rows[pendingIndex] &&
          rows[pendingIndex].querySelector(".maicb-msg-pending")
        ) {
          scrollToBottom();
          return;
        }
      }

      messagesEl.innerHTML = "";
      messages.forEach((msg, index) => {
        const showThinking =
          isSending &&
          msg.role === "assistant" &&
          !(msg.content || "").trim() &&
          index === messages.length - 1;
        messagesEl.appendChild(createMessageRow(msg, showThinking));
      });
      scrollToBottom();
    }

    function setOpen(open) {
      isOpen = open;
      panel.hidden = !open;
      if (mode !== "inline") {
        launcher.hidden = open;
        saveOpenState(open);
      }
    }

    function setError(text) {
      errorText = text || "";
      errorEl.textContent = errorText;
      errorEl.hidden = !errorText;
    }

    function getHistoryForApi() {
      return messages
        .filter((m) => m.role === "user" || m.role === "assistant")
        .filter((m) => m.id !== "welcome")
        .slice(-12)
        .map((m) => ({ role: m.role, content: m.content }));
    }

    async function parseJsonResponse(res) {
      const text = await res.text();
      try {
        return JSON.parse(text);
      } catch {
        return { error: text || "Error desconocido" };
      }
    }

    async function requestStream(body, onChunk) {
      const res = await fetch(config.streamUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": config.nonce,
          "X-Chat-Session-Id": getSessionId(),
        },
        body: JSON.stringify(body),
      });

      const contentType = res.headers.get("Content-Type") || "";
      if (!res.ok) {
        const data = await parseJsonResponse(res);
        throw {
          status: res.status,
          code: data.errorCode || "UNKNOWN",
          message: resolveErrorMessage({ code: data.errorCode || "UNKNOWN", message: data.error, status: res.status }),
          retryAfter: data.retryAfter,
        };
      }

      if (!contentType.includes("text/plain")) {
        return null;
      }

      const streamMeta = {
        model: res.headers.get("X-Chat-Model") || "",
        modelLabel: res.headers.get("X-Chat-Model-Label") || "",
        conversationId: res.headers.get("X-Chat-Conversation-Id") || "",
      };

      const reader = res.body && res.body.getReader ? res.body.getReader() : null;
      if (!reader) {
        const text = await res.text();
        if (text) onChunk(text);
        return streamMeta;
      }

      const decoder = new TextDecoder();
      while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        const chunk = decoder.decode(value, { stream: true });
        onChunk(chunk);
      }
      return streamMeta;
    }

    async function requestChat(body) {
      const res = await fetch(config.restUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": config.nonce,
          "X-Chat-Session-Id": getSessionId(),
        },
        body: JSON.stringify(body),
      });

      const data = await parseJsonResponse(res);
      if (!res.ok) {
        throw {
          status: res.status,
          code: data.errorCode || "UNKNOWN",
          message: resolveErrorMessage({ code: data.errorCode || "UNKNOWN", message: data.error, status: res.status }),
          retryAfter: data.retryAfter,
        };
      }
      return data;
    }

    async function sendMessage(text) {
      if (isSending || !text.trim()) return;
      isSending = true;
      setError("");
      if (sendBtn) sendBtn.disabled = true;

      const userMsg = {
        id: generateId(),
        role: "user",
        content: text.trim(),
        createdAt: new Date().toISOString(),
      };
      messages.push(userMsg);
      renderMessages();

      const assistantId = generateId();
      const assistantMsg = {
        id: assistantId,
        role: "assistant",
        content: "",
        createdAt: new Date().toISOString(),
        model: "",
      };
      messages.push(assistantMsg);
      renderMessages();

      const convId = getConversationId();
      const body = {
        message: userMsg.content,
        history: getHistoryForApi().slice(0, -1),
        currentPath: window.location.pathname,
        currentUrl: window.location.href,
      };
      if (convId) {
        body.conversationId = convId;
      }

      try {
        let modelUsed = "";
        let streamConversationId = "";
        let streamFailed = false;
        if (config.streaming) {
          try {
            const streamResult = await requestStream(body, (chunk) => {
              const idx = messages.findIndex((m) => m.id === assistantId);
              if (idx >= 0) {
                messages[idx].content += chunk;
                renderMessages();
              }
            });
            if (streamResult && typeof streamResult === "object") {
              modelUsed =
                streamResult.modelLabel || streamResult.model || "";
              streamConversationId = streamResult.conversationId || "";
            } else if (typeof streamResult === "string") {
              modelUsed = streamResult;
            }
            if (streamConversationId) {
              setConversationId(streamConversationId);
            }
          } catch (streamErr) {
            if (streamErr && streamErr.status === 404) {
              /* streaming disabled */
            } else {
              streamFailed = true;
              throw streamErr;
            }
          }
        }

        const idx = messages.findIndex((m) => m.id === assistantId);
        if (idx >= 0 && !messages[idx].content.trim() && !streamFailed) {
          const data = await requestChat(body);
          messages[idx].content = data.answer || "";
          messages[idx].model =
            (data.meta && (data.meta.modelLabel || data.meta.model)) || "";
          if (data.meta && data.meta.conversationId) {
            setConversationId(data.meta.conversationId);
          }
        } else if (idx >= 0 && modelUsed) {
          messages[idx].model = modelUsed;
        }
      } catch (err) {
        const idx = messages.findIndex((m) => m.id === assistantId);
        if (idx >= 0) {
          messages.splice(idx, 1);
        }
        setError(resolveErrorMessage(err));
      }

      saveState(messages);
      renderMessages();
      isSending = false;
      if (sendBtn) sendBtn.disabled = false;
      input.focus();
    }

    launcher.addEventListener("click", () => setOpen(true));
    if (minimizeBtn) minimizeBtn.addEventListener("click", () => setOpen(false));
    if (closeBtn) closeBtn.addEventListener("click", () => setOpen(false));

    if (resetBtn) resetBtn.addEventListener("click", () => {
      messages = welcome ? [welcome] : [];
      clearConversationId();
      saveState(messages);
      setError("");
      renderMessages();
    });

    function resizeInput() {
      if (!input) return;
      input.style.height = "auto";
      input.style.height = Math.min(input.scrollHeight, 96) + "px";
    }

    composer.addEventListener("submit", (e) => {
      e.preventDefault();
      if (!input) return;
      const value = input.value.trim();
      if (!value) return;
      input.value = "";
      resizeInput();
      sendMessage(value);
    });

    if (input) {
      input.addEventListener("input", resizeInput);
      input.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        composer.requestSubmit();
      }
      });
    }

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && isOpen && mode !== "inline") setOpen(false);
    });

    document.addEventListener("click", (e) => {
      if (!isOpen || mode === "inline") return;
      if (!wrap.contains(e.target)) setOpen(false);
    });

    renderMessages();
    if (isOpen) scrollToBottom();
  }

  function boot() {
    const roots = document.querySelectorAll(ROOT_SELECTOR);
    roots.forEach(initRoot);
    document.querySelectorAll('[id^="multch-plugin-root"]:not([data-maicb-root])').forEach((legacy) => {
      legacy.dataset.maicbRoot = "1";
      initRoot(legacy);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
