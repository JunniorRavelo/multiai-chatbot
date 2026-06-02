(function () {
  "use strict";

  const cfg = window.multchHistoryAdmin || {};
  const list = document.getElementById("multch-history-list");

  if (!list) {
    return;
  }

  function t(key, fallback) {
    return cfg.i18n && cfg.i18n[key] ? cfg.i18n[key] : fallback;
  }

  function updateUrl(conversationId) {
    if (!window.history || !window.history.replaceState) {
      return;
    }

    const url = new URL(window.location.href);

    if (conversationId) {
      url.searchParams.set("conversation", String(conversationId));
    } else {
      url.searchParams.delete("conversation");
    }

    window.history.replaceState({}, "", url.toString());
  }

  function closeCard(card) {
    const toggle = card.querySelector(".multch-admin-history-card__toggle");
    const panel = card.querySelector(".multch-admin-history-card__panel");

    card.classList.remove("is-open");
    if (toggle) {
      toggle.setAttribute("aria-expanded", "false");
    }
    if (panel) {
      panel.hidden = true;
    }
  }

  function renderError(panel, conversationId, onRetry) {
    panel.innerHTML =
      '<div class="multch-admin-history-card__error">' +
      t("error", "Could not load the conversation.") +
      ' <button type="button" class="button button-small multch-admin-history-retry" data-id="' +
      String(conversationId) +
      '">' +
      t("retry", "Retry") +
      "</button></div>";

    const retryBtn = panel.querySelector(".multch-admin-history-retry");
    if (retryBtn && typeof onRetry === "function") {
      retryBtn.addEventListener("click", onRetry);
    }
  }

  function loadDetail(card, options) {
    const opts = options || {};
    const panel = card.querySelector(".multch-admin-history-card__panel");
    const conversationId = card.getAttribute("data-conversation-id");

    if (!panel || !conversationId) {
      return Promise.resolve();
    }

    panel.innerHTML =
      '<div class="multch-admin-history-card__loading">' +
      t("loading", "Loading messages…") +
      "</div>";

    const requestUrl = new URL(cfg.ajaxUrl || "/wp-admin/admin-ajax.php", window.location.origin);
    requestUrl.searchParams.set("action", "multch_history_detail");
    requestUrl.searchParams.set("nonce", cfg.nonce || "");
    requestUrl.searchParams.set("id", conversationId);

    return fetch(requestUrl.toString(), {
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
      },
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data || !payload.data.html) {
          throw new Error("invalid_response");
        }

        panel.innerHTML = payload.data.html;
        card.setAttribute("data-loaded", "1");

        if (opts.scroll) {
          card.scrollIntoView({ behavior: "smooth", block: "nearest" });
        }
      })
      .catch(function () {
        renderError(panel, conversationId, function () {
          card.setAttribute("data-loaded", "0");
          loadDetail(card, opts);
        });
      });
  }

  function openCard(card, options) {
    const opts = options || {};
    const toggle = card.querySelector(".multch-admin-history-card__toggle");
    const panel = card.querySelector(".multch-admin-history-card__panel");
    const conversationId = card.getAttribute("data-conversation-id");

    if (!toggle || !panel || !conversationId) {
      return;
    }

    list.querySelectorAll(".multch-admin-history-card.is-open").forEach(function (other) {
      if (other !== card) {
        closeCard(other);
      }
    });

    card.classList.add("is-open");
    toggle.setAttribute("aria-expanded", "true");
    panel.hidden = false;

    if (!opts.skipUrl) {
      updateUrl(conversationId);
    }

    const needsLoad =
      card.getAttribute("data-loaded") !== "1" ||
      !panel.querySelector(".multch-admin-history-card__body");

    if (!needsLoad) {
      if (opts.scroll) {
        card.scrollIntoView({ behavior: "smooth", block: "nearest" });
      }
      return;
    }

    loadDetail(card, opts);
  }

  function copyText(text, button, restoreLabel) {
    if (!text) {
      return;
    }

    function flashSuccess() {
      if (!button) {
        return;
      }
      const original =
        typeof restoreLabel === "string" ? restoreLabel : button.textContent;
      button.textContent = t("copied", "Copied");
      window.setTimeout(function () {
        button.textContent = original;
      }, 1500);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(flashSuccess).catch(function () {
        window.prompt(t("copyFailed", "Could not copy."), text);
      });
      return;
    }

    const area = document.createElement("textarea");
    area.value = text;
    area.setAttribute("readonly", "");
    area.style.position = "absolute";
    area.style.left = "-9999px";
    document.body.appendChild(area);
    area.select();
    try {
      document.execCommand("copy");
      flashSuccess();
    } catch (err) {
      window.prompt(t("copyFailed", "Could not copy."), text);
    }
    document.body.removeChild(area);
  }

  function copyConversationJson(button) {
    const conversationId = button.getAttribute("data-id");
    if (!conversationId) {
      return;
    }

    const original = button.textContent;
    button.disabled = true;
    button.textContent = t("copyJsonLoading", "Preparing JSON…");

    const requestUrl = new URL(cfg.ajaxUrl || "/wp-admin/admin-ajax.php", window.location.origin);
    requestUrl.searchParams.set("action", "multch_history_export_json");
    requestUrl.searchParams.set("nonce", cfg.nonce || "");
    requestUrl.searchParams.set("id", conversationId);

    fetch(requestUrl.toString(), {
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
      },
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data || !payload.data.export) {
          throw new Error("invalid_response");
        }

        const text = JSON.stringify(payload.data.export, null, 2);
        copyText(text, button, original);
      })
      .catch(function () {
        window.alert(t("copyJsonFailed", "Could not load conversation JSON."));
      })
      .finally(function () {
        button.disabled = false;
        if (button.textContent === t("copyJsonLoading", "Preparing JSON…")) {
          button.textContent = original;
        }
      });
  }

  function deleteConversation(card, conversationId) {
    if (!window.confirm(t("deleteConfirm", "Delete this conversation and all its messages?"))) {
      return;
    }

    const body = new URLSearchParams();
    body.set("action", "multch_delete_conversation");
    body.set("nonce", cfg.deleteNonce || "");
    body.set("id", String(conversationId));

    fetch(cfg.ajaxUrl || "/wp-admin/admin-ajax.php", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
      },
      body: body.toString(),
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        if (!payload || !payload.success) {
          throw new Error("delete_failed");
        }
        card.remove();
        updateUrl(null);
      })
      .catch(function () {
        window.alert(t("deleteFailed", "Could not delete the conversation."));
      });
  }

  list.addEventListener("click", function (event) {
    const copyBtn = event.target.closest(".multch-admin-history-copy");
    if (copyBtn && list.contains(copyBtn)) {
      event.preventDefault();
      event.stopPropagation();
      copyText(copyBtn.getAttribute("data-copy") || "", copyBtn);
      return;
    }

    const copyJsonBtn = event.target.closest(".multch-admin-history-copy-json");
    if (copyJsonBtn && list.contains(copyJsonBtn)) {
      event.preventDefault();
      event.stopPropagation();
      copyConversationJson(copyJsonBtn);
      return;
    }

    const deleteBtn = event.target.closest(".multch-admin-history-delete");
    if (deleteBtn && list.contains(deleteBtn)) {
      event.preventDefault();
      event.stopPropagation();
      const card = deleteBtn.closest(".multch-admin-history-card");
      if (card) {
        deleteConversation(card, deleteBtn.getAttribute("data-id"));
      }
      return;
    }

    const toggle = event.target.closest(".multch-admin-history-card__toggle");
    if (!toggle || !list.contains(toggle)) {
      return;
    }

    const card = toggle.closest(".multch-admin-history-card");
    if (!card) {
      return;
    }

    if (card.classList.contains("is-open")) {
      closeCard(card);
      updateUrl(null);
      return;
    }

    openCard(card, { scroll: true });
  });

  document.querySelectorAll(".multch-admin-history-purge").forEach(function (link) {
    link.addEventListener("click", function (event) {
      const message = link.getAttribute("data-confirm");
      if (message && !window.confirm(message)) {
        event.preventDefault();
      }
    });
  });

  const initial = list.querySelector(".multch-admin-history-card.is-open");
  if (initial) {
    const conversationId = initial.getAttribute("data-conversation-id");
    const panel = initial.querySelector(".multch-admin-history-card__panel");

    if (panel && !panel.querySelector(".multch-admin-history-card__body")) {
      openCard(initial, { skipUrl: true, scroll: false });
    }

    if (conversationId && window.location.search.indexOf("conversation=" + conversationId) !== -1) {
      initial.scrollIntoView({ behavior: "auto", block: "start" });
    }
  }
})();
