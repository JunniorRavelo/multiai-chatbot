(function () {
  "use strict";

  const STORAGE_KEY = "chatbot-plugin-session-v1";
  const OPEN_KEY = "chatbot-plugin-open-state-v1";
  const SESSION_KEY = "chatbot-plugin-anon-id";
  const CONV_KEY = "chatbot-plugin-conversation-v1";
  const HISTORY_TTL_MS = 24 * 60 * 60 * 1000;
  const MAX_MESSAGES = 60;

  const config = window.chatbotPluginConfig || {};
  const i18n = config.i18n || {};

  function buildHeaderHtml(labels) {
    const resetLabel = labels.resetLabel || "Reiniciar chat";
    const minimizeLabel = labels.minimizeLabel || "Minimizar chat";
    const closeLabel = labels.closeLabel || "Cerrar chat";
    return (
      '<div class="cb-header-brand">' +
      '<span class="cb-header-avatar" aria-hidden="true">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M12 8V4H8"/><path d="M16 12h2"/><path d="M6 12H4"/>' +
      '<rect width="16" height="12" x="4" y="8" rx="2"/><path d="M9 13v2"/><path d="M15 13v2"/>' +
      "</svg></span>" +
      '<div class="cb-header-info">' +
      '<h3 class="cb-header-title"></h3>' +
      '<p class="cb-header-sub"><span class="cb-header-status" aria-hidden="true"></span>' +
      '<span class="cb-header-sub-text"></span></p>' +
      "</div></div>" +
      '<div class="cb-header-actions">' +
      '<button type="button" class="cb-icon-btn cb-reset" title="' +
      resetLabel +
      '" aria-label="' +
      resetLabel +
      '">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>' +
      "</svg></button>" +
      '<button type="button" class="cb-icon-btn cb-minimize" title="' +
      minimizeLabel +
      '" aria-label="' +
      minimizeLabel +
      '">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
      '<path d="M5 12h14"/>' +
      "</svg></button>" +
      '<button type="button" class="cb-icon-btn cb-close" title="' +
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

  function mapErrorMessage(code) {
    const map = {
      RATE_LIMIT_GENERAL: "Demasiadas solicitudes. Espera un momento.",
      RATE_LIMIT_MODEL_MINUTE: "Se alcanzó el límite por minuto del modelo. Intenta en breve.",
      RATE_LIMIT_MODEL_DAILY: "Se alcanzó el límite diario del modelo. Intenta más tarde.",
      MODEL_ALL_EXHAUSTED: "Todos los modelos están temporalmente saturados.",
      MODEL_TEMP_UNAVAILABLE: "Los modelos no están disponibles en este momento.",
      PROVIDER_TIMEOUT: "El proveedor tardó demasiado en responder.",
      PROVIDER_UPSTREAM: "Error del proveedor de IA.",
      ORIGIN_FORBIDDEN: "Solicitud no autorizada.",
      INVALID_REQUEST: "Mensaje inválido.",
      CONFIGURATION_ERROR: "El servicio de IA no está configurado.",
      SERVER_ERROR: "Error interno del servidor.",
    };
    return map[code] || i18n.errorGeneric || "No se pudo enviar el mensaje.";
  }

  function sanitizeErrorMessage(message, status) {
    if (!message || typeof message !== "string") {
      if (status === 502 || status === 504) {
        return "El servidor no pudo completar la solicitud. Revisa la configuración del chatbot o intenta más tarde.";
      }
      return message;
    }
    if (/<!doctype html/i.test(message) || /<html/i.test(message)) {
      return "El servidor respondió con un error (502). Deja vacía la URL interna del chat y verifica la API key de DeepSeek.";
    }
    if (message.length > 280) {
      return message.slice(0, 280) + "…";
    }
    return message;
  }

  function applyStyleVars(el, style) {
    if (!style) return;
    if (style.vars) {
      const vars = style.vars;
      if (vars.primary) el.style.setProperty("--cb-primary", vars.primary);
      if (vars.accent) el.style.setProperty("--cb-accent", vars.accent);
      if (vars.radius) el.style.setProperty("--cb-radius", vars.radius);
    }
    if (style.offset) el.style.setProperty("--cb-offset", style.offset);
    if (style.panelWidth) el.style.setProperty("--cb-panel-width", style.panelWidth);
  }

  function launcherSide(position) {
    if (position.indexOf("left") !== -1) return "left";
    if (position === "bottom-center") return "center";
    return "right";
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

  function initRoot(root) {
    const mode = root.dataset.mode || config.mode || "floating";
    const style = config.style || {};
    const preset = style.preset || "default";
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
    wrap.className = "cb-widget cb-wrap" + (mode === "inline" ? " cb-inline-wrap" : "");
    wrap.classList.add("cb-preset-" + preset);
    wrap.dataset.preset = preset;
    applyStyleVars(wrap, style);

    const launcher = document.createElement("button");
    launcher.type = "button";
    launcher.className = "cb-launcher cb-launcher-" + launcherSide(position);
    launcher.setAttribute("aria-label", i18n.openLabel || "Abrir chat");
    launcher.innerHTML =
      '<span class="cb-launcher-icon" aria-hidden="true">💬</span>' +
      (launcherLabel
        ? '<span class="cb-launcher-text">' + (config.widgetTitle || "Agente IA") + "</span>"
        : "");
    if (mode === "inline" || isOpen) launcher.hidden = true;

    const panel = document.createElement("section");
    panel.className = "cb-panel cb-position-" + position;
    panel.setAttribute("aria-label", "Chatbot");
    if (!isOpen) panel.hidden = true;

    const header = document.createElement("header");
    header.className = "cb-header";
    header.innerHTML = buildHeaderHtml(i18n);

    header.querySelector(".cb-header-title").textContent = config.widgetTitle || "Agente IA";
    header.querySelector(".cb-header-sub-text").textContent =
      config.widgetSubtitle || i18n.onlineLabel || "Sistema en línea";

    const messagesEl = document.createElement("div");
    messagesEl.className = "cb-messages";
    messagesEl.setAttribute("role", "log");
    messagesEl.setAttribute("aria-live", "polite");

    const errorEl = document.createElement("div");
    errorEl.className = "cb-error";
    errorEl.hidden = true;

    const composer = document.createElement("form");
    composer.className = "cb-composer";
    composer.innerHTML =
      '<textarea class="cb-input" rows="1" placeholder="' +
      (i18n.placeholder || "Escribe tu mensaje…") +
      '" maxlength="700"></textarea>' +
      '<button type="submit" class="cb-send">' +
      (i18n.send || "Enviar") +
      "</button>";

    const input = composer.querySelector(".cb-input");
    const sendBtn = composer.querySelector(".cb-send");

    panel.appendChild(header);
    panel.appendChild(messagesEl);
    panel.appendChild(errorEl);
    panel.appendChild(composer);

    wrap.appendChild(launcher);
    wrap.appendChild(panel);
    root.appendChild(wrap);

    if (mode === "inline") {
      launcher.hidden = true;
      panel.hidden = false;
      isOpen = true;
      header.querySelectorAll(".cb-minimize, .cb-close").forEach((btn) => {
        btn.hidden = true;
      });
    }

    function scrollToBottom() {
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function renderMessages() {
      messagesEl.innerHTML = "";
      messages.forEach((msg) => {
        const div = document.createElement("div");
        div.className = "cb-msg cb-msg-" + (msg.role || "assistant");
        div.textContent = msg.content || "";
        if (msg.role === "assistant" && msg.model && msg.model !== "system") {
          const meta = document.createElement("span");
          meta.className = "cb-msg-meta";
          meta.textContent = msg.model;
          div.appendChild(meta);
        }
        messagesEl.appendChild(div);
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
          message: sanitizeErrorMessage(data.error || mapErrorMessage(data.errorCode), res.status),
          retryAfter: data.retryAfter,
        };
      }

      if (!contentType.includes("text/plain")) {
        return null;
      }

      const streamMeta = {
        model: res.headers.get("X-Chat-Model") || "",
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
          message: sanitizeErrorMessage(data.error || mapErrorMessage(data.errorCode), res.status),
          retryAfter: data.retryAfter,
        };
      }
      return data;
    }

    async function sendMessage(text) {
      if (isSending || !text.trim()) return;
      isSending = true;
      setError("");
      sendBtn.disabled = true;

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

      const thinking = document.createElement("div");
      thinking.className = "cb-thinking";
      thinking.textContent = i18n.thinking || "Pensando…";
      messagesEl.appendChild(thinking);
      scrollToBottom();

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
        if (config.streaming) {
          try {
            const streamResult = await requestStream(body, (chunk) => {
              thinking.remove();
              const idx = messages.findIndex((m) => m.id === assistantId);
              if (idx >= 0) {
                messages[idx].content += chunk;
                renderMessages();
              }
            });
            if (streamResult && typeof streamResult === "object") {
              modelUsed = streamResult.model || "";
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
              throw streamErr;
            }
          }
        }

        thinking.remove();

        const idx = messages.findIndex((m) => m.id === assistantId);
        if (idx >= 0 && !messages[idx].content.trim()) {
          const data = await requestChat(body);
          messages[idx].content = data.answer || "";
          messages[idx].model = (data.meta && data.meta.model) || "";
          if (data.meta && data.meta.conversationId) {
            setConversationId(data.meta.conversationId);
          }
        } else if (idx >= 0 && modelUsed) {
          messages[idx].model = modelUsed;
        }
      } catch (err) {
        thinking.remove();
        const idx = messages.findIndex((m) => m.id === assistantId);
        if (idx >= 0) {
          messages.splice(idx, 1);
        }
        setError(sanitizeErrorMessage(err && err.message, err && err.status) || mapErrorMessage(err && err.code));
      }

      saveState(messages);
      renderMessages();
      isSending = false;
      sendBtn.disabled = false;
      input.focus();
    }

    launcher.addEventListener("click", () => setOpen(true));
    header.querySelector(".cb-minimize").addEventListener("click", () => setOpen(false));
    header.querySelector(".cb-close").addEventListener("click", () => setOpen(false));

    header.querySelector(".cb-reset").addEventListener("click", () => {
      messages = welcome ? [welcome] : [];
      clearConversationId();
      saveState(messages);
      setError("");
      renderMessages();
    });

    composer.addEventListener("submit", (e) => {
      e.preventDefault();
      const value = input.value.trim();
      if (!value) return;
      input.value = "";
      sendMessage(value);
    });

    input.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        composer.requestSubmit();
      }
    });

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
    const roots = document.querySelectorAll("#chatbot-plugin-root");
    roots.forEach(initRoot);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
