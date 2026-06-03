(function () {
  "use strict";

  var LAUNCHER_MARKUP =
    '<span class="maicb-launcher-icon-wrap" aria-hidden="true">' +
    '<span class="maicb-launcher-pulse"></span>' +
    '<span class="maicb-launcher-icon">' +
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' +
    '<path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/>' +
    '<path d="M8 10h.01"/><path d="M12 10h.01"/><path d="M16 10h.01"/>' +
    "</svg></span></span>" +
    '<span class="maicb-launcher-text">MultiAI ChatBot</span>';

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
      '<div class="maicb-widget maicb-wrap maicb-preset-default" id="multch-style-preview" role="img" aria-label="Botón flotante del widget MultiAI ChatBot">' +
      '<span class="maicb-launcher maicb-launcher-bottom-right">' +
      LAUNCHER_MARKUP +
      "</span></div>";
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
