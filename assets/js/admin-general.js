(function () {
  "use strict";

  var cfg = window.chatbotGeneralPreview || {};
  var api = window.ChatbotAdminPreview
    ? window.ChatbotAdminPreview.create(Object.assign({}, cfg, { mode: "general" }))
    : null;

  if (!api) {
    return;
  }

  function i18n(key, fallback) {
    return api.previewI18n(key, fallback);
  }

  function charCountLabel(current, max) {
    var template = i18n("charCount", "%1$d / %2$d characters");
    return template.replace("%1$d", String(current)).replace("%2$d", String(max));
  }

  function updateCharCount(input) {
    var max = parseInt(input.getAttribute("data-char-max") || input.maxLength || "0", 10);
    if (!max) return;

    var counter = document.querySelector('[data-char-for="' + input.id + '"]');
    if (!counter) return;

    var len = input.value.length;
    counter.textContent = charCountLabel(len, max);
    counter.classList.remove(
      "chatbot-admin-char-count--warn",
      "chatbot-admin-char-count--over"
    );
    if (len >= max) {
      counter.classList.add("chatbot-admin-char-count--over");
    } else if (len >= max * 0.9) {
      counter.classList.add("chatbot-admin-char-count--warn");
    }
  }

  function initCharCounters() {
    document.querySelectorAll(".chatbot-admin-char-field").forEach(function (input) {
      updateCharCount(input);
      input.addEventListener("input", function () {
        updateCharCount(input);
      });
    });
  }

  function copyShortcode() {
    var text = cfg.shortcode || "[chatbot_widget]";
    var input = document.getElementById("chatbot-shortcode-display");
    if (input) {
      input.value = text;
      input.select();
    }

    function onSuccess() {
      var btn = document.getElementById("chatbot-copy-shortcode");
      if (!btn) return;
      var original = btn.textContent;
      btn.textContent = i18n("copied", "Copied");
      window.setTimeout(function () {
        btn.textContent = original;
      }, 2000);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(onSuccess).catch(function () {
        window.alert(i18n("copyFailed", "Could not copy."));
      });
      return;
    }

    try {
      if (document.execCommand("copy")) {
        onSuccess();
      } else {
        window.alert(i18n("copyFailed", "Could not copy."));
      }
    } catch (e) {
      window.alert(i18n("copyFailed", "Could not copy."));
    }
  }

  function restoreField(fieldId, confirmKey) {
    var btn = document.getElementById(fieldId);
    if (!btn) return;

    btn.addEventListener("click", function () {
      var message = i18n(confirmKey, "Restore default value?");
      if (!window.confirm(message)) {
        return;
      }
      var target = api.field(
        fieldId === "chatbot-restore-welcome" ? "welcome_message" : "system_prompt"
      );
      if (target) {
        target.value = btn.getAttribute("data-default") || "";
        target.dispatchEvent(new Event("input", { bubbles: true }));
        updateCharCount(target);
      }
    });
  }

  function bindGeneralEvents(refs) {
    (cfg.generalFieldNames || ["widget_title", "widget_subtitle", "welcome_message"]).forEach(
      function (name) {
        var input = api.field(name);
        if (!input) return;
        input.addEventListener("input", function () {
          api.applyPreview(api.readSettings(), refs);
        });
      }
    );

    var widgetEl = api.checkboxField("widget_enabled");
    if (widgetEl) {
      widgetEl.addEventListener("change", function () {
        api.updateWidgetDisabledOverlay();
        api.applyPreview(api.readSettings(), refs);
      });
    }

    var copyBtn = document.getElementById("chatbot-copy-shortcode");
    if (copyBtn) {
      copyBtn.addEventListener("click", copyShortcode);
    }

    restoreField("chatbot-restore-welcome", "restoreWelcome");
    restoreField("chatbot-restore-system-prompt", "restoreSystemPrompt");
  }

  function boot() {
    var refs = api.boot();
    if (!refs) return;

    initCharCounters();
    bindGeneralEvents(refs);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
