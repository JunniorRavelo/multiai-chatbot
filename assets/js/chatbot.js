(function () {
  "use strict";

  const STORAGE_KEY = "chatbot-plugin-session-v1";
  const OPEN_KEY = "chatbot-plugin-open-state-v1";
  const SESSION_KEY = "chatbot-plugin-anon-id";
  const HISTORY_TTL_MS = 24 * 60 * 60 * 1000;
  const MAX_MESSAGES = 60;

  const config = window.chatbotPluginConfig || {};
  const i18n = config.i18n || {};

  function generateId() {
    return "m-" + Date.now().toString(36) + "-" + Math.random().toString(36).slice(2, 8);
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
    };
    return map[code] || i18n.errorGeneric || "No se pudo enviar el mensaje.";
  }

  function applyStyleVars(el, style) {
    if (!style || !style.vars) return;
    const vars = style.vars;
    if (vars.primary) el.style.setProperty("--cb-primary", vars.primary);
    if (vars.accent) el.style.setProperty("--cb-accent", vars.accent);
    if (vars.radius) el.style.setProperty("--cb-radius", vars.radius);
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
    const position = style.position || "center-right";

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
    launcher.className = "cb-launcher";
    launcher.setAttribute("aria-label", i18n.openLabel || "Abrir chat");
    launcher.innerHTML =
      '<span class="cb-launcher-icon" aria-hidden="true">💬</span><span>' +
      (config.widgetTitle || "Agente IA") +
      "</span>";
    if (mode === "inline" || isOpen) launcher.hidden = true;

    const panel = document.createElement("section");
    panel.className = "cb-panel cb-position-" + position;
    panel.setAttribute("aria-label", "Chatbot");
    if (!isOpen) panel.hidden = true;

    const header = document.createElement("header");
    header.className = "cb-header";
    header.innerHTML =
      '<div><h3 class="cb-header-title"></h3><p class="cb-header-sub"></p></div>' +
      '<div class="cb-header-actions">' +
      '<button type="button" class="cb-icon-btn cb-reset" title="' +
      (i18n.resetLabel || "Reiniciar") +
      '">↻</button>' +
      '<button type="button" class="cb-icon-btn cb-close" title="' +
      (i18n.closeLabel || "Cerrar") +
      '">✕</button>' +
      "</div>";

    header.querySelector(".cb-header-title").textContent = config.widgetTitle || "Agente IA";
    header.querySelector(".cb-header-sub").textContent = config.widgetSubtitle || "";

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
          message: data.error || mapErrorMessage(data.errorCode),
          retryAfter: data.retryAfter,
        };
      }

      if (!contentType.includes("text/plain")) {
        return null;
      }

      const reader = res.body && res.body.getReader ? res.body.getReader() : null;
      if (!reader) {
        const text = await res.text();
        if (text) onChunk(text);
        return res.headers.get("X-Chat-Model") || "";
      }

      const decoder = new TextDecoder();
      let full = "";
      while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        const chunk = decoder.decode(value, { stream: true });
        full += chunk;
        onChunk(chunk);
      }
      return res.headers.get("X-Chat-Model") || "";
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
          message: data.error || mapErrorMessage(data.errorCode),
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

      const body = {
        message: userMsg.content,
        history: getHistoryForApi().slice(0, -1),
        currentPath: window.location.pathname,
        currentUrl: window.location.href,
      };

      try {
        let modelUsed = "";
        if (config.streaming) {
          try {
            modelUsed = await requestStream(body, (chunk) => {
              thinking.remove();
              const idx = messages.findIndex((m) => m.id === assistantId);
              if (idx >= 0) {
                messages[idx].content += chunk;
                renderMessages();
              }
            });
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
        } else if (idx >= 0 && modelUsed) {
          messages[idx].model = modelUsed;
        }
      } catch (err) {
        thinking.remove();
        const idx = messages.findIndex((m) => m.id === assistantId);
        if (idx >= 0) {
          messages.splice(idx, 1);
        }
        setError((err && err.message) || mapErrorMessage(err && err.code));
      }

      saveState(messages);
      renderMessages();
      isSending = false;
      sendBtn.disabled = false;
      input.focus();
    }

    launcher.addEventListener("click", () => setOpen(true));
    header.querySelector(".cb-close").addEventListener("click", () => setOpen(false));

    header.querySelector(".cb-reset").addEventListener("click", () => {
      messages = welcome ? [welcome] : [];
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
