(function () {
  "use strict";

  const cfg = window.chatbotStylePreview || {};
  const optionKey = cfg.optionKey || "chatbot_plugin_settings";
  const PRESETS = Array.isArray(cfg.presets) && cfg.presets.length
    ? cfg.presets
    : ["default", "dark-glass", "obsidian", "minimal", "ocean", "sunset", "forest", "lavender", "plum"];
  const PRESET_META = cfg.presetMeta || {};
  const EXPORT_KEYS = Array.isArray(cfg.exportKeys) ? cfg.exportKeys : [];
  const POSITIONS = [
    "bottom-right",
    "center-right",
    "bottom-left",
    "center-left",
    "bottom-center",
  ];

  const OVERRIDE_RESET_KEYS = [
    "style_primary",
    "style_accent",
    "style_bg",
    "style_fg",
    "style_radius",
    "style_panel_width",
    "style_panel_max_height",
  ];

  function previewI18n(key, fallback) {
    return cfg.i18n && cfg.i18n[key] ? cfg.i18n[key] : fallback;
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

  function buildComposerHtml() {
    const placeholder = previewI18n("placeholder", "Type your message…");
    const sendLabel = previewI18n("send", "Send");
    return (
      '<div class="maicb-composer-inner">' +
      '<textarea class="maicb-input" rows="1" placeholder="' +
      placeholder +
      '" maxlength="700" readonly aria-label="' +
      placeholder +
      '"></textarea>' +
      '<button type="submit" class="maicb-send" aria-label="' +
      sendLabel +
      '">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>' +
      "</svg></button></div>"
    );
  }

  function buildHeaderHtml() {
    const minimizeLabel = previewI18n("minimize", "Minimize");
    const resetLabel = previewI18n("reset", "Reset");
    const closeLabel = previewI18n("close", "Close");
    return (
      '<div class="maicb-header-brand">' +
      '<span class="maicb-header-avatar" aria-hidden="true">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M12 8V4H8"/><path d="M16 12h2"/><path d="M6 12H4"/>' +
      '<rect width="16" height="12" x="4" y="8" rx="2"/><path d="M9 13v2"/><path d="M15 13v2"/>' +
      "</svg></span>" +
      '<div class="maicb-header-info">' +
      '<h3 class="maicb-header-title"></h3>' +
      '<p class="maicb-header-sub"><span class="maicb-header-status" aria-hidden="true"></span>' +
      '<span class="maicb-header-sub-text"></span></p>' +
      "</div></div>" +
      '<div class="maicb-header-actions">' +
      '<button type="button" class="maicb-icon-btn maicb-minimize" title="' +
      minimizeLabel +
      '" aria-label="' +
      minimizeLabel +
      '">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
      '<path d="M5 12h14"/>' +
      "</svg></button>" +
      '<button type="button" class="maicb-icon-btn maicb-reset" title="' +
      resetLabel +
      '" aria-label="' +
      resetLabel +
      '">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>' +
      "</svg></button>" +
      '<button type="button" class="maicb-icon-btn maicb-close" title="' +
      closeLabel +
      '" aria-label="' +
      closeLabel +
      '">' +
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

  function readGeneralFields() {
    const title = field("widget_title");
    const subtitle = field("widget_subtitle");
    const welcome = field("welcome_message");
    return {
      title: title ? title.value.trim() : cfg.widgetTitle || "AI Agent",
      subtitle: subtitle ? subtitle.value.trim() : cfg.widgetSubtitle || "System online",
      welcome: welcome ? welcome.value.trim() : cfg.welcomeMessage || "Hello! How can I help you?",
    };
  }

  function readSettings() {
    const presetEl = field("style_preset");
    const positionEl = field("style_position");
    const primaryEl = field("style_primary");
    const accentEl = field("style_accent");
    const bgEl = field("style_bg");
    const fgEl = field("style_fg");
    const fontEl = field("style_font_family");
    const radiusEl = field("style_radius");
    const offsetEl = field("style_offset");
    const widthEl = field("style_panel_width");
    const maxHeightEl = field("style_panel_max_height");
    const launcherLabelEl = checkboxField("style_launcher_label");
    const reduceMotionEl = checkboxField("style_reduce_motion");
    const presetAutoEl = checkboxField("style_preset_auto");
    const presetAutoDarkEl = field("style_preset_auto_dark");
    const general = readGeneralFields();

    return {
      preset: presetEl ? presetEl.value : "default",
      position: positionEl ? positionEl.value : "bottom-right",
      primary: primaryEl ? primaryEl.value.trim() : "",
      accent: accentEl ? accentEl.value.trim() : "",
      bg: bgEl ? bgEl.value.trim() : "",
      fg: fgEl ? fgEl.value.trim() : "",
      fontFamily: fontEl ? fontEl.value : "system",
      radius: radiusEl ? radiusEl.value.trim() : "",
      offset: offsetEl ? offsetEl.value.trim() : "1rem",
      panelWidth: widthEl ? widthEl.value.trim() : "",
      panelMaxHeight: maxHeightEl ? maxHeightEl.value.trim() : "",
      launcherLabel: launcherLabelEl ? launcherLabelEl.checked : true,
      reduceMotion: reduceMotionEl ? reduceMotionEl.checked : false,
      presetAuto: presetAutoEl ? presetAutoEl.checked : false,
      presetAutoDark: presetAutoDarkEl ? presetAutoDarkEl.value : "dark-glass",
      title: general.title,
      subtitle: general.subtitle,
      welcome: general.welcome,
    };
  }

  function launcherSide(position) {
    if (position.indexOf("left") !== -1) return "left";
    if (position === "bottom-center") return "center";
    return "right";
  }

  function fontCssValue(fontKey) {
    const map = {
      system: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
      inherit: "inherit",
      serif: 'Georgia, "Times New Roman", serif',
      mono: "ui-monospace, SFMono-Regular, Menlo, Consolas, monospace",
    };
    return map[fontKey] || map.system;
  }

  function applyPresetClasses(wrap, settings) {
    PRESETS.forEach(function (p) {
      wrap.classList.remove("maicb-preset-" + p);
    });
    const light = settings.preset || "default";
    const dark = settings.presetAutoDark || "dark-glass";
    if (!settings.presetAuto) {
      wrap.classList.add("maicb-preset-" + light);
      wrap.dataset.preset = light;
      return;
    }
    const mq = window.matchMedia("(prefers-color-scheme: dark)");
    const apply = function () {
      PRESETS.forEach(function (p) {
        wrap.classList.remove("maicb-preset-" + p);
      });
      const active = mq.matches ? dark : light;
      wrap.classList.add("maicb-preset-" + active);
      wrap.dataset.preset = active;
      updateContrastWarning(wrap, settings);
    };
    apply();
    if (typeof mq.addEventListener === "function") {
      mq.addEventListener("change", apply);
    }
  }

  function applyStyleVars(wrap, settings) {
    const keys = ["primary", "accent", "radius", "bg", "fg"];
    const cssMap = {
      primary: "--maicb-primary",
      accent: "--maicb-accent",
      radius: "--maicb-radius",
      bg: "--maicb-bg",
      fg: "--maicb-fg",
    };
    keys.forEach(function (k) {
      wrap.style.removeProperty(cssMap[k]);
    });
    wrap.style.removeProperty("--maicb-offset");
    wrap.style.removeProperty("--maicb-panel-width");
    wrap.style.removeProperty("--maicb-panel-max-height");

    if (settings.primary) wrap.style.setProperty("--maicb-primary", settings.primary);
    if (settings.accent) wrap.style.setProperty("--maicb-accent", settings.accent);
    if (settings.bg) wrap.style.setProperty("--maicb-bg", settings.bg);
    if (settings.fg) wrap.style.setProperty("--maicb-fg", settings.fg);
    if (settings.radius) wrap.style.setProperty("--maicb-radius", settings.radius);
    if (settings.offset) wrap.style.setProperty("--maicb-offset", settings.offset);
    if (settings.panelWidth) wrap.style.setProperty("--maicb-panel-width", settings.panelWidth);
    if (settings.panelMaxHeight) {
      wrap.style.setProperty("--maicb-panel-max-height", settings.panelMaxHeight);
    }
    wrap.style.fontFamily = fontCssValue(settings.fontFamily);
    wrap.classList.toggle("maicb-reduce-motion", !!settings.reduceMotion);
  }

  function parseHexColor(hex) {
    const m = String(hex).trim().match(/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i);
    if (!m) return null;
    let h = m[1];
    if (h.length === 3) {
      h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
    }
    return {
      r: parseInt(h.slice(0, 2), 16),
      g: parseInt(h.slice(2, 4), 16),
      b: parseInt(h.slice(4, 6), 16),
    };
  }

  function relativeLuminance(rgb) {
    const chan = [rgb.r, rgb.g, rgb.b].map(function (c) {
      const s = c / 255;
      return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * chan[0] + 0.7152 * chan[1] + 0.0722 * chan[2];
  }

  function contrastRatio(l1, l2) {
    const lighter = Math.max(l1, l2);
    const darker = Math.min(l1, l2);
    return (lighter + 0.05) / (darker + 0.05);
  }

  function updateContrastWarning(wrap, settings) {
    const el = document.getElementById("chatbot-preview-contrast");
    if (!el) return;
    const primary = settings.primary || getComputedStyle(wrap).getPropertyValue("--maicb-primary").trim();
    const bg = settings.bg || getComputedStyle(wrap).getPropertyValue("--maicb-bg").trim();
    const pRgb = parseHexColor(primary);
    const bRgb = parseHexColor(bg);
    if (!pRgb || !bRgb) {
      el.hidden = true;
      return;
    }
    const ratio = contrastRatio(relativeLuminance(pRgb), relativeLuminance(bRgb));
    if (ratio < 4.5) {
      el.textContent = previewI18n("contrastWarning", "Low contrast between primary color and background.");
      el.hidden = false;
    } else {
      el.hidden = true;
    }
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

  function updatePresetDesc(presetId) {
    const desc = document.getElementById("chatbot-style-preset-desc");
    if (!desc) return;
    const meta = PRESET_META[presetId];
    desc.textContent = meta && meta.desc ? meta.desc : "";
  }

  function syncPresetCards(presetId) {
    document.querySelectorAll(".chatbot-theme-card").forEach(function (card) {
      const active = card.dataset.preset === presetId;
      card.classList.toggle("is-active", active);
      card.setAttribute("aria-checked", active ? "true" : "false");
    });
    const hidden = document.getElementById("chatbot-style-preset");
    if (hidden) hidden.value = presetId;
    updatePresetDesc(presetId);
  }

  function buildPreviewDOM(viewport) {
    const widgetHost = viewport.querySelector(".maicb-preview-widget-host");
    const mount = widgetHost || viewport;
    if (widgetHost) {
      widgetHost.innerHTML = "";
    } else {
      viewport.innerHTML = "";
    }

    const wrap = document.createElement("div");
    wrap.className = "maicb-widget maicb-wrap maicb-preview-widget";
    wrap.id = "chatbot-style-preview";

    const settings = readSettings();
    applyPresetClasses(wrap, settings);

    const launcher = document.createElement("button");
    launcher.type = "button";
    launcher.className =
      "maicb-launcher maicb-launcher-" +
      launcherSide(settings.position) +
      (settings.launcherLabel ? "" : " maicb-launcher--icon-only");
    launcher.setAttribute("aria-label", previewI18n("openChat", "Open chat"));
    launcher.innerHTML = launcherMarkup(settings.launcherLabel, settings.title);

    const panel = document.createElement("section");
    panel.className = "maicb-panel maicb-position-" + settings.position;
    panel.setAttribute("aria-label", settings.title || "MultiAI ChatBot");

    panel.innerHTML =
      '<header class="maicb-header">' +
      buildHeaderHtml() +
      "</header>" +
      '<div class="maicb-messages" role="log"></div>' +
      '<div class="maicb-error" hidden></div>' +
      '<form class="maicb-composer">' +
      buildComposerHtml() +
      "</form>";

    wrap.appendChild(launcher);
    wrap.appendChild(panel);
    mount.appendChild(wrap);

    let isOpen = false;

    function setOpen(open) {
      isOpen = open;
      panel.hidden = !open;
      launcher.hidden = open;
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
    panel.querySelector(".maicb-composer").addEventListener("submit", function (e) {
      e.preventDefault();
    });

    const toggleBtn = document.getElementById("chatbot-preview-toggle");
    if (toggleBtn) {
      toggleBtn.addEventListener("click", function () {
        setOpen(!isOpen);
        toggleBtn.setAttribute("aria-pressed", isOpen ? "true" : "false");
        toggleBtn.textContent = isOpen ? previewI18n("closePanel", "Close panel") : previewI18n("openPanel", "Open panel");
      });
    }

    panel.querySelector(".maicb-header-title").textContent = settings.title;
    panel.querySelector(".maicb-header-sub-text").textContent = settings.subtitle;
    applyStyleVars(wrap, settings);
    renderMessages(panel.querySelector(".maicb-messages"), settings);
    updateContrastWarning(wrap, settings);

    setOpen(true);
    if (toggleBtn) {
      toggleBtn.setAttribute("aria-pressed", "true");
      toggleBtn.textContent = previewI18n("closePanel", "Close panel");
    }

    return { wrap: wrap, launcher: launcher, panel: panel, setOpen: setOpen };
  }

  function createPreviewMessage(role, text) {
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
    bubble.textContent = text;
    row.appendChild(bubble);
    return row;
  }

  function renderMessages(messagesEl, settings) {
    messagesEl.innerHTML = "";
    const welcomeText = settings.welcome || "Hello! How can I help you?";
    messagesEl.appendChild(createPreviewMessage("assistant", welcomeText));
    messagesEl.appendChild(createPreviewMessage("user", "What are your opening hours?"));
    messagesEl.appendChild(
      createPreviewMessage("assistant", "We are open Monday through Friday, 9:00 AM to 6:00 PM.")
    );
  }

  function applyPreview(settings, refs) {
    const { wrap, launcher, panel } = refs;
    const side = launcherSide(settings.position);

    applyPresetClasses(wrap, settings);

    POSITIONS.forEach(function (p) {
      panel.classList.remove("maicb-position-" + p);
    });
    panel.classList.add("maicb-position-" + settings.position);

    ["left", "right", "center"].forEach(function (s) {
      launcher.classList.remove("maicb-launcher-" + s);
    });
    launcher.classList.add("maicb-launcher-" + side);
    launcher.classList.toggle("maicb-launcher--icon-only", !settings.launcherLabel);
    launcher.innerHTML = launcherMarkup(settings.launcherLabel, settings.title);

    panel.querySelector(".maicb-header-title").textContent = settings.title;
    panel.querySelector(".maicb-header-sub-text").textContent = settings.subtitle;

    applyStyleVars(wrap, settings);
    renderMessages(panel.querySelector(".maicb-messages"), settings);
    updateContrastWarning(wrap, settings);
    updatePositionButtons(settings.position);
    syncPresetCards(settings.preset);
  }

  function syncPositionInput(position) {
    const el = field("style_position");
    if (el) el.value = position;
  }

  function clearColorPicker(name) {
    const input = field(name);
    if (!input) return;
    input.value = "";
    if (typeof jQuery !== "undefined" && jQuery(input).hasClass("wp-color-picker")) {
      jQuery(input).wpColorPicker("color", "");
    }
  }

  function resetOverrides(refs) {
    const colorKeys = ["style_primary", "style_accent", "style_bg", "style_fg"];
    OVERRIDE_RESET_KEYS.forEach(function (key) {
      if (colorKeys.indexOf(key) !== -1) {
        clearColorPicker(key);
        return;
      }
      const input = field(key);
      if (input) input.value = "";
    });
    applyPreview(readSettings(), refs);
  }

  function exportTheme() {
    const data = { version: 1 };
    const keys = EXPORT_KEYS.length ? EXPORT_KEYS : OVERRIDE_RESET_KEYS.concat(["style_preset", "style_position", "style_offset", "style_launcher_label"]);
    keys.forEach(function (key) {
      const input = field(key);
      if (!input) return;
      if (input.type === "checkbox") {
        data[key] = input.checked;
      } else {
        data[key] = input.value;
      }
    });
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "maicb-theme.json";
    a.click();
    URL.revokeObjectURL(url);
  }

  function importTheme(file, refs) {
    const reader = new FileReader();
    reader.onload = function () {
      try {
        const data = JSON.parse(String(reader.result || "{}"));
        Object.keys(data).forEach(function (key) {
          if (key === "version") return;
          const input = field(key);
          if (!input) return;
          if (input.type === "checkbox") {
            input.checked = !!data[key];
          } else {
            input.value = data[key];
            if (typeof jQuery !== "undefined" && jQuery(input).hasClass("wp-color-picker")) {
              jQuery(input).wpColorPicker("color", data[key] || "");
            }
          }
        });
        if (data.style_preset) {
          syncPresetCards(data.style_preset);
        }
        applyPreview(readSettings(), refs);
        window.alert(previewI18n("importSuccess", "Theme imported. Save to apply on the site."));
      } catch (e) {
        window.alert(previewI18n("importError", "Invalid theme JSON."));
      }
    };
    reader.readAsText(file);
  }

  function bindEvents(refs) {
    const inputs = document.querySelectorAll('[name^="' + optionKey + '[style_"]');

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

    (cfg.generalFieldNames || ["widget_title", "widget_subtitle", "welcome_message"]).forEach(
      function (name) {
        const input = field(name);
        if (!input) return;
        input.addEventListener("input", function () {
          applyPreview(readSettings(), refs);
        });
      }
    );

    document.querySelectorAll(".chatbot-theme-card").forEach(function (card) {
      card.addEventListener("click", function () {
        const presetId = card.dataset.preset;
        syncPresetCards(presetId);
        applyPreview(readSettings(), refs);
      });
    });

    document.querySelectorAll(".chatbot-position-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        syncPositionInput(btn.dataset.position);
        applyPreview(readSettings(), refs);
      });
    });

    const resetBtn = document.getElementById("chatbot-style-reset-overrides");
    if (resetBtn) {
      resetBtn.addEventListener("click", function () {
        resetOverrides(refs);
      });
    }

    const exportBtn = document.getElementById("chatbot-style-export");
    if (exportBtn) {
      exportBtn.addEventListener("click", exportTheme);
    }

    const importBtn = document.getElementById("chatbot-style-import");
    const importFile = document.getElementById("chatbot-style-import-file");
    if (importBtn && importFile) {
      importBtn.addEventListener("click", function () {
        importFile.click();
      });
      importFile.addEventListener("change", function () {
        if (importFile.files && importFile.files[0]) {
          importTheme(importFile.files[0], refs);
          importFile.value = "";
        }
      });
    }
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
