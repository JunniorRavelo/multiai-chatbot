(function () {
  "use strict";

  document.querySelectorAll(".multch-admin-stats-purge").forEach(function (link) {
    link.addEventListener("click", function (event) {
      var message = link.getAttribute("data-confirm");
      if (message && !window.confirm(message)) {
        event.preventDefault();
      }
    });
  });
})();
