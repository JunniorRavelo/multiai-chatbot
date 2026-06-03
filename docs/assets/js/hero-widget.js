(function () {
  "use strict";

  var STRINGS = {
    es: {
      COPY: {
        welcome:
          "Hola. Soy un agente de IA. Puedo cometer errores; verifique la información importante antes de tomar decisiones. ¿En qué puedo ayudarle?",
        welcomeMeta: "Mensaje de bienvenida",
        placeholder: "Escribe tu mensaje…",
        title: "Agente IA",
        online: "Sistema en línea",
        minimize: "Minimizar",
        minimizeLabel: "Minimizar chat",
        reset: "Reiniciar",
        resetLabel: "Reiniciar conversación",
        close: "Cerrar",
        closeLabel: "Cerrar chat",
        openChat: "Abrir chat",
        openChatLabel: "Abrir chat de Agente IA",
        thinking: "El agente está escribiendo",
        panelLabel: "Agente IA",
        previewOpen: "Vista previa del chat MultiAI ChatBot abierto",
        previewClosed: "Vista previa del chat MultiAI ChatBot cerrado",
      },
      DEMO_SCENARIOS: [
        {
          question: "¿Cuál es su horario de atención?",
          reply: "Estamos abiertos de lunes a viernes, de 9:00 a 18:00.",
          meta: "gemini-3.5-flash (la API usó este; respaldo configurado: gemini-3.1-flash-lite)",
        },
        {
          question: "¿Se integra con mi sitio WordPress?",
          reply:
            "Sí. Activa el widget global desde el panel o inserta el shortcode en cualquier página o entrada.",
          meta: "gemini-3.1-flash-lite",
        },
        {
          question: "¿Qué proveedores de IA admite?",
          reply:
            "Puede usar WordPress AI, Google Gemini u Ollama local; el plugin enruta la conversación al conector configurado.",
          meta: "gemini-3.5-flash",
        },
        {
          question: "¿Puedo cambiar el aspecto del chat?",
          reply:
            "Sí. Hay varios temas visuales, colores personalizables y posición del botón flotante desde Ajustes.",
          meta: "gemini-3.5-flash (respaldo: gemini-3.1-flash-lite)",
        },
        {
          question: "¿Las respuestas se transmiten en tiempo real?",
          reply:
            "Sí. Las respuestas pueden mostrarse en streaming, igual que en un chat en vivo.",
          meta: "gemini-3.1-flash-lite (respaldo: gemini-3.5-flash)",
        },
        {
          question: "¿Guarda el historial de conversaciones?",
          reply:
            "Opcionalmente. Puede activar el historial en el panel para revisar sesiones anteriores y estadísticas.",
          meta: "gemini-3.5-flash",
        },
      ],
    },
    en: {
      COPY: {
        welcome:
          "Hello. I am an AI agent. I may make mistakes; please verify important information before making decisions. How can I help you?",
        welcomeMeta: "Welcome message",
        placeholder: "Type your message…",
        title: "AI Agent",
        online: "System online",
        minimize: "Minimize",
        minimizeLabel: "Minimize chat",
        reset: "Reset",
        resetLabel: "Reset conversation",
        close: "Close",
        closeLabel: "Close chat",
        openChat: "Open chat",
        openChatLabel: "Open AI Agent chat",
        thinking: "Agent is typing",
        panelLabel: "AI Agent",
        previewOpen: "MultiAI ChatBot chat preview open",
        previewClosed: "MultiAI ChatBot chat preview closed",
      },
      DEMO_SCENARIOS: [
        {
          question: "What are your opening hours?",
          reply: "We are open Monday through Friday, 9:00 AM to 6:00 PM.",
          meta: "gemini-3.5-flash (API used this; fallback configured: gemini-3.1-flash-lite)",
        },
        {
          question: "Does it integrate with my WordPress site?",
          reply:
            "Yes. Enable the global widget from the dashboard or insert the shortcode on any page or post.",
          meta: "gemini-3.1-flash-lite",
        },
        {
          question: "Which AI providers does it support?",
          reply:
            "You can use WordPress AI, Google Gemini, or local Ollama; the plugin routes the conversation to the configured connector.",
          meta: "gemini-3.5-flash",
        },
        {
          question: "Can I change how the chat looks?",
          reply:
            "Yes. There are several visual themes, customizable colors, and floating button position in Settings.",
          meta: "gemini-3.5-flash (fallback: gemini-3.1-flash-lite)",
        },
        {
          question: "Are responses streamed in real time?",
          reply:
            "Yes. Replies can be shown with streaming, just like a live chat.",
          meta: "gemini-3.1-flash-lite (fallback: gemini-3.5-flash)",
        },
        {
          question: "Does it save conversation history?",
          reply:
            "Optionally. You can enable history in the dashboard to review past sessions and statistics.",
          meta: "gemini-3.5-flash",
        },
      ],
    },
  };

  var AUTO_LOOP_PAUSE_MS = 4800;
  var RESET_PULSE_MS = 620;

  var ROBOT_SVG =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' +
    '<path d="M12 8V4H8"/><path d="M16 12h2"/><path d="M6 12H4"/>' +
    '<rect width="16" height="12" x="4" y="8" rx="2"/><path d="M9 13v2"/><path d="M15 13v2"/>' +
    "</svg>";

  var LAUNCHER_INNER =
    '<span class="maicb-launcher-icon-wrap" aria-hidden="true">' +
    '<span class="maicb-launcher-pulse"></span>' +
    '<span class="maicb-launcher-icon">' +
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
    '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>' +
    '<path d="M8 10h.01"/><path d="M12 10h.01"/><path d="M16 10h.01"/>' +
    "</svg></span></span>";

  function getPageLang() {
    if (window.MultchDocsI18n && window.MultchDocsI18n.getLang) {
      return window.MultchDocsI18n.getLang();
    }
    var lang = document.documentElement.lang || "es";
    return lang.indexOf("en") === 0 ? "en" : "es";
  }

  function getBundle() {
    var lang = getPageLang();
    return STRINGS[lang] || STRINGS.es;
  }

  function buildHeaderHtml(copy) {
    return (
      '<header class="maicb-header">' +
      '<div class="maicb-header-brand">' +
      '<span class="maicb-header-avatar" aria-hidden="true">' +
      ROBOT_SVG +
      "</span>" +
      '<div class="maicb-header-info">' +
      '<h3 class="maicb-header-title">' +
      copy.title +
      "</h3>" +
      '<p class="maicb-header-sub">' +
      '<span class="maicb-header-status" aria-hidden="true"></span>' +
      '<span class="maicb-header-sub-text">' +
      copy.online +
      "</span>" +
      "</p></div></div>" +
      '<div class="maicb-header-actions">' +
      '<button type="button" class="maicb-icon-btn maicb-minimize" title="' +
      copy.minimize +
      '" aria-label="' +
      copy.minimizeLabel +
      '">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
      '<path d="M5 12h14"/></svg></button>' +
      '<button type="button" class="maicb-icon-btn maicb-reset" title="' +
      copy.reset +
      '" aria-label="' +
      copy.resetLabel +
      '">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></button>' +
      '<button type="button" class="maicb-icon-btn maicb-close" title="' +
      copy.close +
      '" aria-label="' +
      copy.closeLabel +
      '">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
      '<path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>' +
      "</div></header>"
    );
  }

  function createMessageRow(role, text, meta) {
    var row = document.createElement("div");
    row.className = "maicb-msg-row maicb-msg-row-" + role;

    if (role === "assistant") {
      var avatar = document.createElement("span");
      avatar.className = "maicb-msg-avatar";
      avatar.setAttribute("aria-hidden", "true");
      avatar.innerHTML = ROBOT_SVG;
      row.appendChild(avatar);
    }

    var bubble = document.createElement("div");
    bubble.className = "maicb-msg maicb-msg-" + role;
    bubble.textContent = text;

    if (meta) {
      var metaEl = document.createElement("span");
      metaEl.className = "maicb-msg-meta";
      metaEl.textContent = meta;
      bubble.appendChild(metaEl);
    }

    row.appendChild(bubble);
    return row;
  }

  function createThinkingRow(thinkingLabel) {
    var row = document.createElement("div");
    row.className = "maicb-msg-row maicb-msg-row-assistant maicb-hero-thinking-row";

    var avatar = document.createElement("span");
    avatar.className = "maicb-msg-avatar";
    avatar.setAttribute("aria-hidden", "true");
    avatar.innerHTML = ROBOT_SVG;
    row.appendChild(avatar);

    var bubble = document.createElement("div");
    bubble.className = "maicb-msg maicb-msg-assistant maicb-thinking";
    bubble.setAttribute("role", "status");
    bubble.setAttribute("aria-live", "polite");
    bubble.setAttribute("aria-label", thinkingLabel);

    var dotsWrap = document.createElement("span");
    dotsWrap.className = "maicb-thinking-dots";
    dotsWrap.setAttribute("aria-hidden", "true");
    for (var i = 0; i < 3; i += 1) {
      var dot = document.createElement("span");
      dot.className = "maicb-thinking-dot";
      dotsWrap.appendChild(dot);
    }
    bubble.appendChild(dotsWrap);
    row.appendChild(bubble);
    return row;
  }

  function buildLauncherHtml(copy) {
    return (
      '<button type="button" class="maicb-launcher maicb-launcher-bottom-right" ' +
      'aria-label="' +
      copy.openChatLabel +
      '" title="' +
      copy.openChat +
      '" hidden>' +
      LAUNCHER_INNER +
      '<span class="maicb-launcher-text">' +
      copy.title +
      "</span>" +
      "</button>"
    );
  }

  function buildPanelHtml(copy) {
    return (
      '<section class="maicb-panel maicb-position-bottom-center" aria-label="' +
      copy.panelLabel +
      '">' +
      buildHeaderHtml(copy) +
      '<div class="maicb-messages" role="log" aria-live="polite"></div>' +
      '<form class="maicb-composer">' +
      '<div class="maicb-composer-inner">' +
      '<textarea class="maicb-input" rows="1" readonly placeholder="' +
      copy.placeholder +
      '" aria-label="' +
      copy.placeholder +
      '"></textarea>' +
      '<button type="button" class="maicb-send" tabindex="-1" aria-hidden="true" disabled>' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg></button>' +
      "</div></form></section>"
    );
  }

  function createDemoRunner(panel, wrap, isPanelOpen, bundle) {
    var copy = bundle.COPY;
    var scenarios = bundle.DEMO_SCENARIOS;
    var messagesEl = panel.querySelector(".maicb-messages");
    var input = panel.querySelector(".maicb-input");
    var composerInner = panel.querySelector(".maicb-composer-inner");
    var resetBtn = panel.querySelector(".maicb-reset");
    var generation = 0;
    var scenarioIndex = 0;
    var autoLoopTimer = null;
    var resetPulseTimer = null;

    function scrollMessages() {
      if (!messagesEl) return;
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function clearComposer() {
      if (!input) return;
      input.value = "";
      input.placeholder = copy.placeholder;
      input.classList.remove("maicb-hero-input-typing");
      if (composerInner) {
        composerInner.classList.remove("maicb-hero-composer-active");
      }
    }

    function clearAutoLoopTimer() {
      if (autoLoopTimer !== null) {
        window.clearTimeout(autoLoopTimer);
        autoLoopTimer = null;
      }
    }

    function getScenario() {
      return scenarios[scenarioIndex % scenarios.length];
    }

    function advanceScenario() {
      scenarioIndex = (scenarioIndex + 1) % scenarios.length;
    }

    function setBusy(on) {
      wrap.classList.toggle("maicb-hero-preview--demo-busy", on);
      if (resetBtn) {
        resetBtn.disabled = on;
        resetBtn.setAttribute("aria-busy", on ? "true" : "false");
      }
    }

    function isCancelled(gen) {
      return gen !== generation;
    }

    function wait(ms, gen) {
      return new Promise(function (resolve) {
        if (isCancelled(gen)) {
          resolve(false);
          return;
        }
        window.setTimeout(function () {
          resolve(!isCancelled(gen));
        }, ms);
      });
    }

    function pulseResetButton() {
      if (!resetBtn) return;
      resetBtn.classList.remove("maicb-hero-reset-pulse");
      void resetBtn.offsetWidth;
      resetBtn.classList.add("maicb-hero-reset-pulse");
      if (resetPulseTimer !== null) {
        window.clearTimeout(resetPulseTimer);
      }
      resetPulseTimer = window.setTimeout(function () {
        resetBtn.classList.remove("maicb-hero-reset-pulse");
        resetPulseTimer = null;
      }, RESET_PULSE_MS);
    }

    function typeIntoInput(text, gen) {
      return new Promise(function (resolve) {
        if (!input || isCancelled(gen)) {
          resolve(false);
          return;
        }

        input.placeholder = "";
        input.classList.add("maicb-hero-input-typing");
        if (composerInner) {
          composerInner.classList.add("maicb-hero-composer-active");
        }
        var index = 0;

        function step() {
          if (isCancelled(gen)) {
            resolve(false);
            return;
          }
          if (index <= text.length) {
            input.value = text.slice(0, index);
            index += 1;
            scrollMessages();
            window.setTimeout(step, index === 1 ? 280 : 42);
            return;
          }
          input.classList.remove("maicb-hero-input-typing");
          if (composerInner) {
            composerInner.classList.remove("maicb-hero-composer-active");
          }
          resolve(true);
        }

        step();
      });
    }

    function scheduleAutoLoop(gen) {
      clearAutoLoopTimer();
      autoLoopTimer = window.setTimeout(function () {
        autoLoopTimer = null;
        if (isCancelled(gen) || !isPanelOpen()) return;
        autoRestart();
      }, AUTO_LOOP_PAUSE_MS);
    }

    async function runSequence() {
      if (!messagesEl || !input) return;

      clearAutoLoopTimer();
      generation += 1;
      var gen = generation;
      var scenario = getScenario();
      setBusy(true);

      messagesEl.innerHTML = "";
      clearComposer();

      messagesEl.appendChild(
        createMessageRow("assistant", copy.welcome, copy.welcomeMeta)
      );
      scrollMessages();

      if (!(await wait(900, gen))) return;

      if (!(await typeIntoInput(scenario.question, gen))) return;
      if (!(await wait(450, gen))) return;

      clearComposer();
      messagesEl.appendChild(createMessageRow("user", scenario.question, ""));
      scrollMessages();

      if (!(await wait(500, gen))) return;

      var thinkingRow = createThinkingRow(copy.thinking);
      messagesEl.appendChild(thinkingRow);
      scrollMessages();

      if (!(await wait(1100, gen))) return;

      if (thinkingRow.parentNode) {
        thinkingRow.parentNode.removeChild(thinkingRow);
      }

      messagesEl.appendChild(
        createMessageRow("assistant", scenario.reply, scenario.meta)
      );
      scrollMessages();

      if (isCancelled(gen)) return;

      advanceScenario();
      setBusy(false);
      scheduleAutoLoop(gen);
    }

    function cancel() {
      generation += 1;
      clearAutoLoopTimer();
      setBusy(false);
      clearComposer();
    }

    function autoRestart() {
      if (!isPanelOpen()) return;
      pulseResetButton();
      generation += 1;
      setBusy(false);
      clearComposer();
      window.setTimeout(function () {
        if (!isPanelOpen()) return;
        runSequence();
      }, RESET_PULSE_MS);
    }

    function restart() {
      cancel();
      runSequence();
    }

    return { restart: restart, runSequence: runSequence, cancel: cancel };
  }

  function wirePreview(wrap, bundle) {
    var copy = bundle.COPY;
    var launcher = wrap.querySelector(".maicb-launcher");
    var panel = wrap.querySelector(".maicb-panel");
    if (!launcher || !panel) return;

    var panelOpen = true;

    function isPanelOpen() {
      return panelOpen;
    }

    var demo = createDemoRunner(panel, wrap, isPanelOpen, bundle);
    var input = panel.querySelector(".maicb-input");

    if (input) {
      input.addEventListener("keydown", function (e) {
        e.preventDefault();
      });
      input.addEventListener("paste", function (e) {
        e.preventDefault();
      });
    }

    function setOpen(open) {
      panelOpen = open;
      panel.hidden = !open;
      launcher.hidden = open;
      wrap.setAttribute("data-panel-open", open ? "true" : "false");
      wrap.classList.toggle("maicb-hero-preview-open", open);
      wrap.setAttribute(
        "aria-label",
        open ? copy.previewOpen : copy.previewClosed
      );
      if (open) {
        demo.runSequence();
      } else {
        demo.cancel();
      }
    }

    launcher.addEventListener("click", function () {
      setOpen(true);
    });

    panel.querySelector(".maicb-minimize").addEventListener("click", function () {
      setOpen(false);
    });

    panel.querySelector(".maicb-close").addEventListener("click", function () {
      setOpen(false);
    });

    panel.querySelector(".maicb-reset").addEventListener("click", function () {
      demo.restart();
    });

    panel.querySelector(".maicb-composer").addEventListener("submit", function (e) {
      e.preventDefault();
    });

    setOpen(true);
  }

  function mountPreview(host) {
    var bundle = getBundle();
    var copy = bundle.COPY;

    host.innerHTML =
      '<div class="hero-site-mock" aria-hidden="true">' +
      '<div class="hero-site-mock__chrome">' +
      '<span class="hero-site-mock__dot"></span>' +
      '<span class="hero-site-mock__dot"></span>' +
      '<span class="hero-site-mock__dot"></span>' +
      "</div>" +
      '<div class="hero-site-mock__hero"></div>' +
      '<div class="hero-site-mock__line hero-site-mock__line--wide"></div>' +
      '<div class="hero-site-mock__line"></div>' +
      '<div class="hero-site-mock__line"></div>' +
      '<div class="hero-site-mock__line hero-site-mock__line--short"></div>' +
      "</div>" +
      '<div class="maicb-widget maicb-wrap maicb-preset-default maicb-hero-preview" id="multch-style-preview" data-panel-open="true" role="region" aria-label="' +
      copy.previewOpen +
      '">' +
      buildLauncherHtml(copy) +
      buildPanelHtml(copy) +
      "</div>";

    wirePreview(host.querySelector("#multch-style-preview"), bundle);
  }

  function boot() {
    var host = document.getElementById("hero-widget-host");
    if (host) mountPreview(host);
  }

  document.addEventListener("multch:langchange", boot);

  if (!window.MultchDocsI18n) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", boot);
    } else {
      boot();
    }
  }
})();
