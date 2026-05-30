(function (global) {
  "use strict";

  var DEFAULT_PRESETS = [
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

  var POSITIONS = [
    "bottom-right",
    "center-right",
    "bottom-left",
    "center-left",
    "bottom-center",
  ];

  /**
   * @param {Record<string, unknown>} userCfg
   */
  function create(userCfg) {
    var cfg = userCfg || {};
    var optionKey = cfg.optionKey || "chatbot_plugin_settings";
    var mode = cfg.mode === "general" ? "general" : "style";
    var PRESETS =
      Array.isArray(cfg.presets) && cfg.presets.length ? cfg.presets : DEFAULT_PRESETS;
    var PRESET_META = cfg.presetMeta || {};
    var savedStyle = cfg.savedStyle || {};
    var credit = cfg.credit || {};

    function previewI18n(key, fallback) {
      return cfg.i18n && cfg.i18n[key] ? cfg.i18n[key] : fallback;
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
      var title = field("widget_title");
      var subtitle = field("widget_subtitle");
      var welcome = field("welcome_message");
      var displayDefaults = cfg.defaults || {};
      return {
        title: title
          ? title.value.trim()
          : cfg.widgetTitle ||
            displayDefaults.widget_title ||
            previewI18n("fallbackTitle", "AI Agent"),
        subtitle: subtitle
          ? subtitle.value.trim()
          : cfg.widgetSubtitle ||
            displayDefaults.widget_subtitle ||
            previewI18n("fallbackSubtitle", "System online"),
        welcome: welcome
          ? welcome.value.trim()
          : cfg.welcomeMessage ||
            displayDefaults.welcome_message ||
            previewI18n("fallbackWelcome", "Hello! How can I help you?"),
      };
    }

    function readStyleFromForm() {
      var presetEl = field("style_preset");
      var positionEl = field("style_position");
      var primaryEl = field("style_primary");
      var accentEl = field("style_accent");
      var bgEl = field("style_bg");
      var fgEl = field("style_fg");
      var fontEl = field("style_font_family");
      var radiusEl = field("style_radius");
      var offsetEl = field("style_offset");
      var widthEl = field("style_panel_width");
      var maxHeightEl = field("style_panel_max_height");
      var launcherLabelEl = checkboxField("style_launcher_label");
      var showCreditEl = checkboxField("style_show_credit");
      var reduceMotionEl = checkboxField("style_reduce_motion");
      var presetAutoEl = checkboxField("style_preset_auto");
      var presetAutoDarkEl = field("style_preset_auto_dark");

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
        showCredit: showCreditEl ? showCreditEl.checked : false,
        reduceMotion: reduceMotionEl ? reduceMotionEl.checked : false,
        presetAuto: presetAutoEl ? presetAutoEl.checked : false,
        presetAutoDark: presetAutoDarkEl ? presetAutoDarkEl.value : "dark-glass",
      };
    }

    function readStyleFromSaved() {
      return {
        preset: savedStyle.preset || "default",
        position: savedStyle.position || "bottom-right",
        primary: savedStyle.primary || "",
        accent: savedStyle.accent || "",
        bg: savedStyle.bg || "",
        fg: savedStyle.fg || "",
        fontFamily: savedStyle.fontFamily || "system",
        radius: savedStyle.radius || "",
        offset: savedStyle.offset || "1rem",
        panelWidth: savedStyle.panelWidth || "",
        panelMaxHeight: savedStyle.panelMaxHeight || "",
        launcherLabel: savedStyle.launcherLabel !== false,
        showCredit: !!savedStyle.showCredit,
        reduceMotion: !!savedStyle.reduceMotion,
        presetAuto: !!savedStyle.presetAuto,
        presetAutoDark: savedStyle.presetAutoDark || "dark-glass",
      };
    }

    function readSettings() {
      var style = mode === "general" ? readStyleFromSaved() : readStyleFromForm();
      var general = readGeneralFields();
      return Object.assign({}, style, general);
    }

    function isWidgetEnabled() {
      var el = checkboxField("widget_enabled");
      return el ? el.checked : true;
    }

    function launcherClassForPosition(position) {
      var allowed = {
        "bottom-right": "bottom-right",
        "center-right": "center-right",
        "bottom-left": "bottom-left",
        "center-left": "center-left",
        "bottom-center": "center",
      };
      return allowed[position] || "bottom-right";
    }

    function fontCssValue(fontKey) {
      var map = {
        system: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
        inherit: "inherit",
        serif: 'Georgia, "Times New Roman", serif',
        mono: "ui-monospace, SFMono-Regular, Menlo, Consolas, monospace",
      };
      return map[fontKey] || map.system;
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

    function escapeHtml(text) {
      return String(text)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
    }

    function buildCreditHtml() {
      if (!credit.productUrl) {
        return "";
      }
      var productName = escapeHtml(credit.productName || "MultiAI Chatbot");
      var authorName = escapeHtml(credit.authorName || "Jsravelo");
      var productUrl = escapeHtml(credit.productUrl);
      var authorUrl = escapeHtml(credit.authorUrl || credit.productUrl);
      return (
        '<p class="maicb-credit" data-maicb="credit" role="contentinfo">' +
        '<a href="' +
        productUrl +
        '" target="_blank" rel="noopener noreferrer">' +
        productName +
        "</a> \u00b7 " +
        '<a href="' +
        authorUrl +
        '" target="_blank" rel="noopener noreferrer">' +
        authorName +
        "</a></p>"
      );
    }

    function buildComposerHtml(settings) {
      var placeholder = previewI18n("placeholder", "Type your message…");
      var sendLabel = previewI18n("send", "Send");
      var html =
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
        "</svg></button></div>";
      if (settings && settings.showCredit) {
        html += buildCreditHtml();
      }
      return html;
    }

    function syncComposerCredit(composer, settings) {
      if (!composer) return;
      var existing = composer.querySelector(".maicb-credit");
      if (settings.showCredit) {
        if (!existing) {
          composer.insertAdjacentHTML("beforeend", buildCreditHtml());
        }
      } else if (existing) {
        existing.remove();
      }
    }

    function buildHeaderHtml() {
      var minimizeLabel = previewI18n("minimize", "Minimize");
      var resetLabel = previewI18n("reset", "Reset");
      var closeLabel = previewI18n("close", "Close");
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

    function applyPresetClasses(wrap, settings) {
      PRESETS.forEach(function (p) {
        wrap.classList.remove("maicb-preset-" + p);
      });
      var light = settings.preset || "default";
      var dark = settings.presetAutoDark || "dark-glass";
      if (!settings.presetAuto) {
        wrap.classList.add("maicb-preset-" + light);
        wrap.dataset.preset = light;
        return;
      }
      var mq = global.matchMedia("(prefers-color-scheme: dark)");
      var apply = function () {
        PRESETS.forEach(function (p) {
          wrap.classList.remove("maicb-preset-" + p);
        });
        var active = mq.matches ? dark : light;
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
      var keys = ["primary", "accent", "radius", "bg", "fg"];
      var cssMap = {
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
      var m = String(hex).trim().match(/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i);
      if (!m) return null;
      var h = m[1];
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
      var chan = [rgb.r, rgb.g, rgb.b].map(function (c) {
        var s = c / 255;
        return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
      });
      return 0.2126 * chan[0] + 0.7152 * chan[1] + 0.0722 * chan[2];
    }

    function contrastRatio(l1, l2) {
      var lighter = Math.max(l1, l2);
      var darker = Math.min(l1, l2);
      return (lighter + 0.05) / (darker + 0.05);
    }

    function updateContrastWarning(wrap, settings) {
      var el = document.getElementById("chatbot-preview-contrast");
      if (!el) return;
      var primary =
        settings.primary || getComputedStyle(wrap).getPropertyValue("--maicb-primary").trim();
      var bg = settings.bg || getComputedStyle(wrap).getPropertyValue("--maicb-bg").trim();
      var pRgb = parseHexColor(primary);
      var bRgb = parseHexColor(bg);
      if (!pRgb || !bRgb) {
        el.hidden = true;
        return;
      }
      var ratio = contrastRatio(relativeLuminance(pRgb), relativeLuminance(bRgb));
      if (ratio < 4.5) {
        el.textContent = previewI18n(
          "contrastWarning",
          "Low contrast between primary color and background."
        );
        el.hidden = false;
      } else {
        el.hidden = true;
      }
    }

    function updatePositionButtons(position) {
      document.querySelectorAll(".chatbot-position-btn").forEach(function (btn) {
        btn.classList.toggle("is-active", btn.dataset.position === position);
      });
      var label = document.getElementById("chatbot-position-label");
      if (label && cfg.positionLabels && cfg.positionLabels[position]) {
        label.textContent = cfg.positionLabels[position];
      }
      var viewport = document.getElementById("chatbot-preview-viewport");
      if (viewport) {
        viewport.setAttribute("data-preview-position", position);
      }
    }

    function updateWidgetDisabledOverlay() {
      var overlay = document.getElementById("chatbot-preview-disabled-overlay");
      var textEl = document.getElementById("chatbot-preview-disabled-text");
      if (!overlay) return;
      var disabled = mode === "general" && !isWidgetEnabled();
      overlay.hidden = !disabled;
      if (textEl && disabled) {
        textEl.textContent = previewI18n(
          "widgetDisabled",
          "Global widget is disabled. The preview shows how copy would look if enabled."
        );
      }
      var viewport = document.getElementById("chatbot-preview-viewport");
      if (viewport) {
        viewport.classList.toggle("chatbot-admin-preview__viewport--widget-off", disabled);
      }
    }

    function createPreviewMessage(role, text) {
      var row = document.createElement("div");
      row.className = "maicb-msg-row maicb-msg-row-" + role;

      if (role === "assistant") {
        var avatar = document.createElement("span");
        avatar.className = "maicb-msg-avatar";
        avatar.setAttribute("aria-hidden", "true");
        avatar.innerHTML =
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' +
          '<path d="M12 8V4H8"/><path d="M16 12h2"/><path d="M6 12H4"/>' +
          '<rect width="16" height="12" x="4" y="8" rx="2"/><path d="M9 13v2"/><path d="M15 13v2"/>' +
          "</svg>";
        row.appendChild(avatar);
      }

      var bubble = document.createElement("div");
      bubble.className = "maicb-msg maicb-msg-" + role;
      bubble.textContent = text;
      row.appendChild(bubble);
      return row;
    }

    function renderMessages(messagesEl, settings) {
      messagesEl.innerHTML = "";
      var welcomeText =
        settings.welcome || previewI18n("fallbackWelcome", "Hello! How can I help you?");
      messagesEl.appendChild(createPreviewMessage("assistant", welcomeText));
      messagesEl.appendChild(
        createPreviewMessage(
          "user",
          previewI18n("previewSampleUser", "What are your opening hours?")
        )
      );
      messagesEl.appendChild(
        createPreviewMessage(
          "assistant",
          previewI18n(
            "previewSampleAssistant",
            "We are open Monday through Friday, 9:00 AM to 6:00 PM."
          )
        )
      );
    }

    function applyPreview(settings, refs) {
      var wrap = refs.wrap;
      var launcher = refs.launcher;
      var panel = refs.panel;

      applyPresetClasses(wrap, settings);

      POSITIONS.forEach(function (p) {
        panel.classList.remove("maicb-position-" + p);
      });
      panel.classList.add("maicb-position-" + settings.position);

      ["bottom-right", "center-right", "bottom-left", "center-left", "center"].forEach(
        function (s) {
          launcher.classList.remove("maicb-launcher-" + s);
        }
      );
      launcher.classList.add("maicb-launcher-" + launcherClassForPosition(settings.position));
      launcher.classList.toggle("maicb-launcher--icon-only", !settings.launcherLabel);
      launcher.innerHTML = launcherMarkup(settings.launcherLabel, settings.title);

      panel.querySelector(".maicb-header-title").textContent = settings.title;
      panel.querySelector(".maicb-header-sub-text").textContent = settings.subtitle;

      applyStyleVars(wrap, settings);
      renderMessages(panel.querySelector(".maicb-messages"), settings);
      syncComposerCredit(panel.querySelector(".maicb-composer"), settings);
      updateContrastWarning(wrap, settings);
      if (mode === "style") {
        updatePositionButtons(settings.position);
        syncPresetCards(settings.preset);
      }
      updateWidgetDisabledOverlay();
    }

    function updatePresetDesc(presetId) {
      var desc = document.getElementById("chatbot-style-preset-desc");
      if (!desc) return;
      var meta = PRESET_META[presetId];
      desc.textContent = meta && meta.desc ? meta.desc : "";
    }

    function syncPresetCards(presetId) {
      document.querySelectorAll(".chatbot-theme-card").forEach(function (card) {
        var active = card.dataset.preset === presetId;
        card.classList.toggle("is-active", active);
        card.setAttribute("aria-checked", active ? "true" : "false");
      });
      var hidden = document.getElementById("chatbot-style-preset");
      if (hidden) hidden.value = presetId;
      updatePresetDesc(presetId);
    }

    function buildPreviewDOM(viewport) {
      var widgetHost = viewport.querySelector(".maicb-preview-widget-host");
      var mount = widgetHost || viewport;
      if (widgetHost) {
        widgetHost.innerHTML = "";
      } else {
        viewport.innerHTML = "";
      }

      var wrap = document.createElement("div");
      wrap.className = "maicb-widget maicb-wrap maicb-preview-widget";
      wrap.id = "chatbot-style-preview";

      var settings = readSettings();
      applyPresetClasses(wrap, settings);

      var launcher = document.createElement("button");
      launcher.type = "button";
      launcher.className =
        "maicb-launcher maicb-launcher-" +
        launcherClassForPosition(settings.position) +
        (settings.launcherLabel ? "" : " maicb-launcher--icon-only");
      launcher.setAttribute("aria-label", previewI18n("openChat", "Open chat"));
      launcher.innerHTML = launcherMarkup(settings.launcherLabel, settings.title);

      var panel = document.createElement("section");
      panel.className = "maicb-panel maicb-position-" + settings.position;
      panel.setAttribute("aria-label", settings.title || "MultiAI ChatBot");

      panel.innerHTML =
        '<header class="maicb-header">' +
        buildHeaderHtml() +
        "</header>" +
        '<div class="maicb-messages" role="log"></div>' +
        '<div class="maicb-error" hidden></div>' +
        '<form class="maicb-composer">' +
        buildComposerHtml(settings) +
        "</form>";

      wrap.appendChild(launcher);
      wrap.appendChild(panel);
      mount.appendChild(wrap);

      var isOpen = false;

      function setOpen(open) {
        isOpen = open;
        panel.hidden = !open;
        launcher.hidden = open;
        var vp = document.getElementById("chatbot-preview-viewport");
        if (vp) {
          vp.setAttribute("data-preview-panel-open", open ? "true" : "false");
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
      panel.querySelector(".maicb-composer").addEventListener("submit", function (e) {
        e.preventDefault();
      });

      var toggleBtn = document.getElementById("chatbot-preview-toggle");
      if (toggleBtn) {
        toggleBtn.addEventListener("click", function () {
          setOpen(!isOpen);
          toggleBtn.setAttribute("aria-pressed", isOpen ? "true" : "false");
          toggleBtn.textContent = isOpen
            ? previewI18n("closePanel", "Close panel")
            : previewI18n("openPanel", "Open panel");
        });
      }

      panel.querySelector(".maicb-header-title").textContent = settings.title;
      panel.querySelector(".maicb-header-sub-text").textContent = settings.subtitle;
      applyStyleVars(wrap, settings);
      renderMessages(panel.querySelector(".maicb-messages"), settings);
      syncComposerCredit(panel.querySelector(".maicb-composer"), settings);
      updateContrastWarning(wrap, settings);
      if (mode === "style") {
        updatePositionButtons(settings.position);
      }
      updateWidgetDisabledOverlay();

      setOpen(true);
      if (toggleBtn) {
        toggleBtn.setAttribute("aria-pressed", "true");
        toggleBtn.textContent = previewI18n("closePanel", "Close panel");
      }

      return { wrap: wrap, launcher: launcher, panel: panel, setOpen: setOpen };
    }

    function boot() {
      var viewport = document.getElementById("chatbot-preview-viewport");
      if (!viewport) return null;

      var refs = buildPreviewDOM(viewport);
      applyPreview(readSettings(), refs);
      return refs;
    }

    return {
      mode: mode,
      optionKey: optionKey,
      cfg: cfg,
      previewI18n: previewI18n,
      field: field,
      checkboxField: checkboxField,
      readSettings: readSettings,
      applyPreview: applyPreview,
      boot: boot,
      syncPresetCards: syncPresetCards,
      updatePositionButtons: updatePositionButtons,
      updateWidgetDisabledOverlay: updateWidgetDisabledOverlay,
      isWidgetEnabled: isWidgetEnabled,
    };
  }

  global.ChatbotAdminPreview = {
    create: create,
    DEFAULT_PRESETS: DEFAULT_PRESETS,
  };
})(window);
