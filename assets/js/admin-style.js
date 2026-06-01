(function () {
  "use strict";

  var cfg = window.multchStylePreview || {};
  var api = window.MultchAdminPreview
    ? window.MultchAdminPreview.create(Object.assign({}, cfg, { mode: "style" }))
    : null;

  if (!api) {
    return;
  }

  var optionKey = api.optionKey;
  var OVERRIDE_RESET_KEYS = [
    "style_primary",
    "style_accent",
    "style_bg",
    "style_fg",
    "style_radius",
    "style_panel_width",
    "style_panel_max_height",
  ];
  var EXPORT_KEYS = Array.isArray(cfg.exportKeys) ? cfg.exportKeys : [];

  function field(name) {
    return api.field(name);
  }

  function syncPositionInput(position) {
    var el = field("style_position");
    if (el) el.value = position;
  }

  function syncPreviewOpenState(refs, open) {
    if (!refs || typeof refs.setOpen !== "function") return;
    refs.setOpen(open);
    var toggleBtn = document.getElementById("multch-preview-toggle");
    if (toggleBtn) {
      toggleBtn.setAttribute("aria-pressed", open ? "true" : "false");
      toggleBtn.textContent = open
        ? api.previewI18n("closePanel", "Close panel")
        : api.previewI18n("openPanel", "Open panel");
    }
  }

  function clearColorPicker(name) {
    var input = field(name);
    if (!input) return;
    input.value = "";
    if (typeof jQuery !== "undefined" && jQuery(input).hasClass("wp-color-picker")) {
      jQuery(input).wpColorPicker("color", "");
    }
  }

  function resetOverrides(refs) {
    var colorKeys = ["style_primary", "style_accent", "style_bg", "style_fg"];
    OVERRIDE_RESET_KEYS.forEach(function (key) {
      if (colorKeys.indexOf(key) !== -1) {
        clearColorPicker(key);
        return;
      }
      var input = field(key);
      if (input) input.value = "";
    });
    api.applyPreview(api.readSettings(), refs);
  }

  function exportTheme() {
    var data = { version: 1 };
    var keys = EXPORT_KEYS.length
      ? EXPORT_KEYS
      : OVERRIDE_RESET_KEYS.concat([
          "style_preset",
          "style_position",
          "style_offset",
          "style_launcher_label",
        ]);
    keys.forEach(function (key) {
      var input = field(key);
      if (!input) return;
      if (input.type === "checkbox") {
        data[key] = input.checked;
      } else {
        data[key] = input.value;
      }
    });
    var blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" });
    var url = URL.createObjectURL(blob);
    var a = document.createElement("a");
    a.href = url;
    a.download = "maicb-theme.json";
    a.click();
    URL.revokeObjectURL(url);
  }

  function importTheme(file, refs) {
    var reader = new FileReader();
    reader.onload = function () {
      try {
        var data = JSON.parse(String(reader.result || "{}"));
        Object.keys(data).forEach(function (key) {
          if (key === "version") return;
          var input = field(key);
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
          api.syncPresetCards(data.style_preset);
        }
        api.applyPreview(api.readSettings(), refs);
        window.alert(
          api.previewI18n(
            "importSuccess",
            "Theme imported. Save to apply on the site."
          )
        );
      } catch (e) {
        window.alert(api.previewI18n("importError", "Invalid theme JSON."));
      }
    };
    reader.readAsText(file);
  }

  function bindEvents(refs) {
    var inputs = document.querySelectorAll('[name^="' + optionKey + '[style_"]');

    inputs.forEach(function (input) {
      if (
        input.type === "hidden" &&
        document.querySelector('[name="' + input.name + '"][type="checkbox"]')
      ) {
        return;
      }
      var evt = input.type === "checkbox" || input.tagName === "SELECT" ? "change" : "input";
      input.addEventListener(evt, function () {
        api.applyPreview(api.readSettings(), refs);
      });
    });

    (cfg.generalFieldNames || ["widget_title", "widget_subtitle", "welcome_message"]).forEach(
      function (name) {
        var input = field(name);
        if (!input) return;
        input.addEventListener("input", function () {
          api.applyPreview(api.readSettings(), refs);
        });
      }
    );

    document.querySelectorAll(".multch-theme-card").forEach(function (card) {
      card.addEventListener("click", function () {
        var presetId = card.dataset.preset;
        api.syncPresetCards(presetId);
        api.applyPreview(api.readSettings(), refs);
      });
    });

    document.querySelectorAll(".multch-position-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        syncPositionInput(btn.dataset.position);
        syncPreviewOpenState(refs, false);
        api.applyPreview(api.readSettings(), refs);
      });
    });

    var resetBtn = document.getElementById("multch-style-reset-overrides");
    if (resetBtn) {
      resetBtn.addEventListener("click", function () {
        resetOverrides(refs);
      });
    }

    var exportBtn = document.getElementById("multch-style-export");
    if (exportBtn) {
      exportBtn.addEventListener("click", exportTheme);
    }

    var importBtn = document.getElementById("multch-style-import");
    var importFile = document.getElementById("multch-style-import-file");
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

    jQuery(".multch-color-picker").each(function () {
      var $input = jQuery(this);
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
    var refs = api.boot();
    if (!refs) return;

    bindEvents(refs);

    initColorPickers(function () {
      api.applyPreview(api.readSettings(), refs);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
