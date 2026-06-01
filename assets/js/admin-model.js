(function () {
  "use strict";

  var cfg = window.multchModelAdmin || {};
  var descriptions = cfg.descriptions || {};
  var sel = document.getElementById("multch-provider");

  if (!sel) {
    return;
  }

  var modelDesc = document.getElementById("multch-model-desc");
  var candidatesDesc = document.getElementById("multch-model-candidates-desc");
  var wpModel = document.getElementById("multch-model");
  var wpFallbackModel = document.getElementById("multch-model-fallback");
  var ollamaModel = document.getElementById("multch-model-ollama");

  function setFieldEnabled(el, enabled) {
    if (!el) {
      return;
    }
    el.disabled = !enabled;
  }

  function toggle() {
    var v = sel.value;
    var isWp = v === "wordpress_ai";
    var isOllama = v === "ollama";

    document.querySelectorAll(".multch-field-wordpress-ai").forEach(function (el) {
      el.style.display = isWp ? "" : "none";
    });
    document.querySelectorAll(".multch-field-ollama").forEach(function (el) {
      el.style.display = isOllama ? "" : "none";
    });

    setFieldEnabled(wpModel, isWp);
    setFieldEnabled(wpFallbackModel, isWp);
    setFieldEnabled(ollamaModel, isOllama);

    if (modelDesc && descriptions[v]) {
      modelDesc.textContent = descriptions[v].model || "";
    }
    if (candidatesDesc && descriptions[v]) {
      candidatesDesc.textContent = descriptions[v].candidates || "";
    }
  }

  sel.addEventListener("change", toggle);
  toggle();
})();
