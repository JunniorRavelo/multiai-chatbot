(function () {
  "use strict";

  var ROBOT_SVG =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' +
    '<path d="M12 8V4H8"/><path d="M16 12h2"/><path d="M6 12H4"/>' +
    '<rect width="16" height="12" x="4" y="8" rx="2"/><path d="M9 13v2"/><path d="M15 13v2"/>' +
    "</svg>";

  function buildHeaderHtml() {
    return (
      '<header class="maicb-header">' +
      '<div class="maicb-header-brand">' +
      '<span class="maicb-header-avatar" aria-hidden="true">' +
      ROBOT_SVG +
      "</span>" +
      '<div class="maicb-header-info">' +
      '<h3 class="maicb-header-title">Agente IA</h3>' +
      '<p class="maicb-header-sub">' +
      '<span class="maicb-header-status" aria-hidden="true"></span>' +
      '<span class="maicb-header-sub-text">Sistema en línea</span>' +
      "</p></div></div>" +
      '<div class="maicb-header-actions">' +
      '<button type="button" class="maicb-icon-btn maicb-minimize" tabindex="-1" aria-hidden="true">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
      '<path d="M5 12h14"/></svg></button>' +
      '<button type="button" class="maicb-icon-btn maicb-reset" tabindex="-1" aria-hidden="true">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
      '<path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></button>' +
      '<button type="button" class="maicb-icon-btn maicb-close" tabindex="-1" aria-hidden="true">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">' +
      '<path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg></button>' +
      "</div></header>"
    );
  }

  function buildMessageRow(role, text, meta) {
    var avatar =
      role === "assistant"
        ? '<span class="maicb-msg-avatar" aria-hidden="true">' + ROBOT_SVG + "</span>"
        : "";
    var metaHtml = meta
      ? '<span class="maicb-msg-meta">' + meta + "</span>"
      : "";
    return (
      '<div class="maicb-msg-row maicb-msg-row-' +
      role +
      '">' +
      avatar +
      '<div class="maicb-msg maicb-msg-' +
      role +
      '">' +
      text +
      metaHtml +
      "</div></div>"
    );
  }

  function buildPanelHtml() {
    return (
      '<section class="maicb-panel maicb-position-bottom-right" aria-label="Agente IA">' +
      buildHeaderHtml() +
      '<div class="maicb-messages" role="log">' +
      buildMessageRow(
        "assistant",
        "Hola. Soy un agente de IA. Puedo cometer errores; verifique la información importante antes de tomar decisiones. ¿En qué puedo ayudarle?",
        "Mensaje de bienvenida"
      ) +
      buildMessageRow("user", "¿Cuál es su horario de atención?", "") +
      buildMessageRow(
        "assistant",
        "Estamos abiertos de lunes a viernes, de 9:00 a 18:00.",
        "gemini-3.5-flash (la API usó este; respaldo configurado: gemini-3.1-flash-lite)"
      ) +
      "</div>" +
      '<form class="maicb-composer">' +
      '<div class="maicb-composer-inner">' +
      '<textarea class="maicb-input" rows="1" placeholder="Escribe tu mensaje…" readonly tabindex="-1" aria-hidden="true"></textarea>' +
      '<button type="button" class="maicb-send" tabindex="-1" aria-hidden="true">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg></button>' +
      "</div></form></section>"
    );
  }

  function mountStaticPreview(host) {
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
      '<div class="maicb-widget maicb-wrap maicb-preset-default maicb-hero-preview-open" id="multch-style-preview" role="img" aria-label="Vista previa del chat MultiAI ChatBot abierto">' +
      buildPanelHtml() +
      "</div>";
  }

  function boot() {
    var host = document.getElementById("hero-widget-host");
    if (host) mountStaticPreview(host);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
