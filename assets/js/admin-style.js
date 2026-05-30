(function () {
  "use strict";

  const PRESETS = ["default", "dark-glass", "minimal", "ocean"];
  const POSITIONS = [
    "bottom-right",
    "center-right",
    "bottom-left",
    "center-left",
    "bottom-center",
  ];

  const cfg = window.chatbotStylePreview || {};
  const optionKey = cfg.optionKey || "chatbot_plugin_settings";

  function buildHeaderHtml() {
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
      '<button type="button" class="cb-icon-btn cb-minimize" title="Minimizar" aria-label="Minimizar">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
      '<path d="M5 12h14"/>' +
      "</svg></button>" +
      '<button type="button" class="cb-icon-btn cb-reset" title="Reiniciar" aria-label="Reiniciar">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>' +
      "</svg></button>" +
      '<button type="button" class="cb-icon-btn cb-close" title="Cerrar" aria-label="Cerrar">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
      '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>' +
      "</svg></button></div>"
    );
  }

  function field(name) {
    return document.querySelector('[name="' + optionKey + "[" + name + ']"]');
  }

  function checkboxField(name) {
    return document.querySelector(
      '[name="' + optionKey + "[" + name + ']"][type="checkbox"]'
    );
  }

  function readSettings() {
    const presetEl = field("style_preset");
    const positionEl = field("style_position");
    const primaryEl = field("style_primary");
    const accentEl = field("style_accent");
    const radiusEl = field("style_radius");
    const offsetEl = field("style_offset");
    const widthEl = field("style_panel_width");
    const launcherLabelEl = checkboxField("style_launcher_label");

    return {
      preset: presetEl ? presetEl.value : "default",
      position: positionEl ? positionEl.value : "bottom-right",
      primary: primaryEl ? primaryEl.value.trim() : "",
      accent: accentEl ? accentEl.value.trim() : "",
      radius: radiusEl ? radiusEl.value.trim() : "",
      offset: offsetEl ? offsetEl.value.trim() : "1rem",
      panelWidth: widthEl ? widthEl.value.trim() : "",
      launcherLabel: launcherLabelEl ? launcherLabelEl.checked : true,
      title: cfg.widgetTitle || "Agente IA",
      subtitle: cfg.widgetSubtitle || "Sistema en línea",
      welcome: cfg.welcomeMessage || "¡Hola! ¿En qué puedo ayudarte?",
    };
  }

  function launcherSide(position) {
    if (position.indexOf("left") !== -1) return "left";
    if (position === "bottom-center") return "center";
    return "right";
  }

  function applyStyleVars(wrap, settings) {
    wrap.style.removeProperty("--cb-primary");
    wrap.style.removeProperty("--cb-accent");
    wrap.style.removeProperty("--cb-radius");
    wrap.style.removeProperty("--cb-offset");
    wrap.style.removeProperty("--cb-panel-width");

    if (settings.primary) wrap.style.setProperty("--cb-primary", settings.primary);
    if (settings.accent) wrap.style.setProperty("--cb-accent", settings.accent);
    if (settings.radius) wrap.style.setProperty("--cb-radius", settings.radius);
    if (settings.offset) wrap.style.setProperty("--cb-offset", settings.offset);
    if (settings.panelWidth) wrap.style.setProperty("--cb-panel-width", settings.panelWidth);
  }

  function updatePositionButtons(position) {
    document.querySelectorAll(".chatbot-position-btn").forEach(function (btn) {
      btn.classList.toggle("is-active", btn.dataset.position === position);
    });
    const label = document.getElementById("chatbot-position-label");
    if (label && cfg.positionLabels && cfg.positionLabels[position]) {
      label.textContent = cfg.positionLabels[position];
    }
  }

  function buildPreviewDOM(viewport) {
    viewport.innerHTML = "";

    const wrap = document.createElement("div");
    wrap.className = "cb-widget cb-wrap cb-preview-widget";
    wrap.id = "chatbot-style-preview";

    const launcher = document.createElement("button");
    launcher.type = "button";
    launcher.className = "cb-launcher cb-launcher-right";
    launcher.setAttribute("aria-label", "Abrir chat");
    launcher.innerHTML =
      '<span class="cb-launcher-icon" aria-hidden="true">💬</span>' +
      '<span class="cb-launcher-text"></span>';

    const panel = document.createElement("section");
    panel.className = "cb-panel cb-position-bottom-right";
    panel.setAttribute("aria-label", "Chatbot");

    panel.innerHTML =
      '<header class="cb-header">' +
      buildHeaderHtml() +
      "</header>" +
      '<div class="cb-messages" role="log"></div>' +
      '<form class="cb-composer">' +
      '<div class="cb-composer-inner">' +
      '<textarea class="cb-input" rows="1" placeholder="Escribe tu mensaje…" readonly aria-label="Escribe tu mensaje…"></textarea>' +
      '<button type="submit" class="cb-send" aria-label="Enviar">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>' +
      "</svg></button></div>" +
      '<p class="cb-composer-hint">Enter para enviar</p>' +
      "</form>";

    wrap.appendChild(launcher);
    wrap.appendChild(panel);
    viewport.appendChild(wrap);

    let isOpen = false;

    function setOpen(open) {
      isOpen = open;
      panel.hidden = !open;
      launcher.hidden = open;
    }

    launcher.addEventListener("click", function () {
      setOpen(true);
    });
    panel.querySelector(".cb-minimize").addEventListener("click", function () {
      setOpen(false);
    });
    panel.querySelector(".cb-close").addEventListener("click", function () {
      setOpen(false);
    });
    panel.querySelector(".cb-composer").addEventListener("submit", function (e) {
      e.preventDefault();
    });

    const toggleBtn = document.getElementById("chatbot-preview-toggle");
    if (toggleBtn) {
      toggleBtn.addEventListener("click", function () {
        setOpen(!isOpen);
        toggleBtn.setAttribute("aria-pressed", isOpen ? "true" : "false");
        toggleBtn.textContent = isOpen ? cfg.i18n.closePanel : cfg.i18n.openPanel;
      });
    }

    setOpen(false);
    if (toggleBtn) {
      toggleBtn.setAttribute("aria-pressed", "false");
      toggleBtn.textContent = cfg.i18n.openPanel;
    }

    return { wrap: wrap, launcher: launcher, panel: panel, setOpen: setOpen };
  }

  function createPreviewMessage(role, text) {
    const row = document.createElement("div");
    row.className = "cb-msg-row cb-msg-row-" + role;

    if (role === "assistant") {
      const avatar = document.createElement("span");
      avatar.className = "cb-msg-avatar";
      avatar.setAttribute("aria-hidden", "true");
      avatar.innerHTML =
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' +
        '<path d="M12 8V4H8"/><path d="M16 12h2"/><path d="M6 12H4"/>' +
        '<rect width="16" height="12" x="4" y="8" rx="2"/><path d="M9 13v2"/><path d="M15 13v2"/>' +
        "</svg>";
      row.appendChild(avatar);
    }

    const bubble = document.createElement("div");
    bubble.className = "cb-msg cb-msg-" + role;
    bubble.textContent = text;
    row.appendChild(bubble);
    return row;
  }

  function renderMessages(messagesEl, settings) {
    messagesEl.innerHTML = "";
    messagesEl.appendChild(createPreviewMessage("assistant", settings.welcome));
    messagesEl.appendChild(createPreviewMessage("user", "¿Cuáles son vuestros horarios?"));
    messagesEl.appendChild(
      createPreviewMessage("assistant", "Atendemos de lunes a viernes, de 9:00 a 18:00.")
    );
  }

  function applyPreview(settings, refs) {
    const { wrap, launcher, panel } = refs;
    const side = launcherSide(settings.position);

    PRESETS.forEach(function (p) {
      wrap.classList.remove("cb-preset-" + p);
    });
    wrap.classList.add("cb-preset-" + settings.preset);

    POSITIONS.forEach(function (p) {
      panel.classList.remove("cb-position-" + p);
    });
    panel.classList.add("cb-position-" + settings.position);

    ["left", "right", "center"].forEach(function (s) {
      launcher.classList.remove("cb-launcher-" + s);
    });
    launcher.classList.add("cb-launcher-" + side);

    const labelEl = launcher.querySelector(".cb-launcher-text");
    if (labelEl) {
      labelEl.textContent = settings.launcherLabel ? settings.title : "";
      labelEl.hidden = !settings.launcherLabel;
    }

    panel.querySelector(".cb-header-title").textContent = settings.title;
    panel.querySelector(".cb-header-sub-text").textContent = settings.subtitle;

    applyStyleVars(wrap, settings);
    renderMessages(panel.querySelector(".cb-messages"), settings);

    updatePositionButtons(settings.position);
  }

  function syncPositionInput(position) {
    const el = field("style_position");
    if (el) el.value = position;
  }

  function bindEvents(refs) {
    const inputs = document.querySelectorAll(
      '[name^="' + optionKey + '[style_"]'
    );

    inputs.forEach(function (input) {
      if (
        input.type === "hidden" &&
        document.querySelector('[name="' + input.name + '"][type="checkbox"]')
      ) {
        return;
      }
      const evt = input.type === "checkbox" || input.tagName === "SELECT" ? "change" : "input";
      input.addEventListener(evt, function () {
        applyPreview(readSettings(), refs);
      });
    });

    document.querySelectorAll(".chatbot-position-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const position = btn.dataset.position;
        syncPositionInput(position);
        applyPreview(readSettings(), refs);
      });
    });
  }

  function initColorPickers(onChange) {
    if (typeof jQuery === "undefined" || !jQuery.fn.wpColorPicker) return;

    jQuery(".chatbot-color-picker").each(function () {
      const $input = jQuery(this);
      if ($input.hasClass("wp-color-picker")) return;

      $input.wpColorPicker({
        change: function () {
          setTimeout(onChange, 10);
        },
        clear: function () {
          setTimeout(onChange, 10);
        },
      });
    });
  }

  function boot() {
    const viewport = document.getElementById("chatbot-preview-viewport");
    if (!viewport) return;

    const refs = buildPreviewDOM(viewport);
    applyPreview(readSettings(), refs);
    bindEvents(refs);

    initColorPickers(function () {
      applyPreview(readSettings(), refs);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
