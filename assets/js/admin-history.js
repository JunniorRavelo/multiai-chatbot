(function () {
  "use strict";

  const cfg = window.multchHistoryAdmin || {};
  const SEARCH_DEBOUNCE_MS = 450;

  const filterForm = document.getElementById("multch-history-filters");
  const listPanel = document.getElementById("multch-history-list-panel");
  const listContent = document.getElementById("multch-history-list-content");
  const countLabelEl = document.getElementById("multch-history-count-label");
  const pageMetaEl = document.getElementById("multch-history-page-meta");

  let listFetchController = null;
  let searchDebounceTimer = null;

  function t(key, fallback) {
    return cfg.i18n && cfg.i18n[key] ? cfg.i18n[key] : fallback;
  }

  function getHistoryList() {
    return listContent ? listContent.querySelector("#multch-history-list") : null;
  }

  function collectFilterParams(extra) {
    const params = new URLSearchParams();
    const opts = extra || {};

    if (filterForm) {
      const formData = new FormData(filterForm);
      formData.forEach(function (value, key) {
        if ("page" === key || "tab" === key) {
          return;
        }
        const normalized = String(value).trim();
        if ("" !== normalized) {
          params.set(key, normalized);
        }
      });
    }

    params.set("paged", String(opts.paged || 1));

    if (opts.conversation) {
      params.set("conversation", String(opts.conversation));
    }

    return params;
  }

  function syncBrowserUrl(params) {
    if (!window.history || !window.history.replaceState) {
      return;
    }

    const url = new URL(window.location.href);
    const keep = ["page", "tab", "days", "s", "search_in", "page_path", "provider", "status", "paged", "conversation"];

    keep.forEach(function (key) {
      url.searchParams.delete(key);
    });

    params.forEach(function (value, key) {
      if ("action" === key || "nonce" === key) {
        return;
      }
      url.searchParams.set(key, value);
    });

    window.history.replaceState({}, "", url.toString());
  }

  function updateFilterActions(hasFilters, clearUrl) {
    if (!filterForm) {
      return;
    }

    let actions = document.getElementById("multch-history-filter-actions");

    if (hasFilters) {
      if (!actions) {
        actions = document.createElement("div");
        actions.id = "multch-history-filter-actions";
        actions.className = "multch-admin-history-filters__actions";
        filterForm.appendChild(actions);
      }
      actions.innerHTML =
        '<a class="button multch-admin-history-clear-filters" href="' +
        String(clearUrl).replace(/"/g, "&quot;") +
        '">' +
        t("clearFilters", "Clear filters") +
        "</a>";
    } else if (actions) {
      actions.remove();
    }
  }

  function updateListHead(countLabel, pageMeta) {
    if (countLabelEl && countLabel) {
      countLabelEl.textContent = countLabel;
    }
    if (!pageMetaEl) {
      return;
    }
    if (pageMeta) {
      pageMetaEl.textContent = pageMeta;
      pageMetaEl.hidden = false;
      return;
    }
    pageMetaEl.textContent = "";
    pageMetaEl.hidden = true;
  }

  function applyHistoryFilters(extra) {
    if (!filterForm || !listContent || !listPanel) {
      return Promise.resolve();
    }

    const params = collectFilterParams(extra || {});
    const requestParams = new URLSearchParams(params);
    requestParams.set("action", "multch_history_list");
    requestParams.set("nonce", cfg.nonce || "");

    if (listFetchController) {
      listFetchController.abort();
    }
    listFetchController = new AbortController();

    listPanel.classList.add("is-loading");
    listPanel.setAttribute("aria-busy", "true");

    const requestUrl = new URL(cfg.ajaxUrl || "/wp-admin/admin-ajax.php", window.location.origin);
    requestUrl.search = requestParams.toString();

    return fetch(requestUrl.toString(), {
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
      },
      signal: listFetchController.signal,
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (payload) {
        if (!payload || !payload.success || !payload.data) {
          throw new Error("invalid_response");
        }

        listContent.innerHTML = payload.data.contentHtml || "";
        updateListHead(payload.data.countLabel, payload.data.pageMeta);
        updateFilterActions(!!payload.data.hasFilters, payload.data.clearFiltersUrl || "");
        syncBrowserUrl(params);
        initExpandedFromUrl();
      })
      .catch(function (error) {
        if (error && error.name === "AbortError") {
          return;
        }
        window.alert(t("listFailed", "Could not update the conversation list."));
      })
      .finally(function () {
        listPanel.classList.remove("is-loading");
        listPanel.removeAttribute("aria-busy");
      });
  }

  function scheduleSearchFilter() {
    window.clearTimeout(searchDebounceTimer);
    searchDebounceTimer = window.setTimeout(function () {
      applyHistoryFilters({ paged: 1 });
    }, SEARCH_DEBOUNCE_MS);
  }

  function resetFilterFields() {
    if (!filterForm) {
      return;
    }

    const searchInput = filterForm.querySelector("#multch-history-search");
    if (searchInput) {
      searchInput.value = "";
    }

    filterForm.querySelectorAll("select").forEach(function (select) {
      const defaultOption = select.querySelector('option[value="all"]');
      if (defaultOption) {
        select.value = "all";
        return;
      }
      select.selectedIndex = 0;
    });
  }

  if (filterForm) {
    const searchInput = filterForm.querySelector("#multch-history-search");
    if (searchInput) {
      searchInput.addEventListener("input", scheduleSearchFilter);
      searchInput.addEventListener("keydown", function (event) {
        if (event.key !== "Enter") {
          return;
        }
        event.preventDefault();
        window.clearTimeout(searchDebounceTimer);
        applyHistoryFilters({ paged: 1 });
      });
    }

    filterForm.querySelectorAll("select").forEach(function (select) {
      select.addEventListener("change", function () {
        applyHistoryFilters({ paged: 1 });
      });
    });

    filterForm.addEventListener("click", function (event) {
      const clearLink = event.target.closest(".multch-admin-history-clear-filters");
      if (!clearLink || !filterForm.contains(clearLink)) {
        return;
      }
      event.preventDefault();
      resetFilterFields();
      applyHistoryFilters({ paged: 1 });
    });

    filterForm.addEventListener("submit", function (event) {
      event.preventDefault();
      window.clearTimeout(searchDebounceTimer);
      applyHistoryFilters({ paged: 1 });
    });
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
    const list = getHistoryList();

    if (!toggle || !panel || !conversationId) {
      return;
    }

    if (list) {
      list.querySelectorAll(".multch-admin-history-card.is-open").forEach(function (other) {
        if (other !== card) {
          closeCard(other);
        }
      });
    }

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

  function initExpandedFromUrl() {
    const list = getHistoryList();
    if (!list) {
      return;
    }

    const initial = list.querySelector(".multch-admin-history-card.is-open");
    if (!initial) {
      return;
    }

    const conversationId = initial.getAttribute("data-conversation-id");
    const panel = initial.querySelector(".multch-admin-history-card__panel");

    if (panel && !panel.querySelector(".multch-admin-history-card__body")) {
      openCard(initial, { skipUrl: true, scroll: false });
    }

    if (
      conversationId &&
      window.location.search.indexOf("conversation=" + conversationId) !== -1
    ) {
      initial.scrollIntoView({ behavior: "auto", block: "start" });
    }
  }

  if (listContent) {
    listContent.addEventListener("click", function (event) {
      const clearLink = event.target.closest(".multch-admin-history-clear-filters");
      if (clearLink && listContent.contains(clearLink)) {
        event.preventDefault();
        resetFilterFields();
        applyHistoryFilters({ paged: 1 });
        return;
      }

      const pageLink = event.target.closest(".multch-admin-tablenav--history a");
      if (pageLink && listContent.contains(pageLink)) {
        event.preventDefault();
        const linkUrl = new URL(pageLink.href, window.location.origin);
        const paged = parseInt(linkUrl.searchParams.get("paged") || "1", 10);
        const conversationId = linkUrl.searchParams.get("conversation");
        applyHistoryFilters({
          paged: paged,
          conversation: conversationId || undefined,
        });
        return;
      }

      const copyBtn = event.target.closest(".multch-admin-history-copy");
      if (copyBtn && listContent.contains(copyBtn)) {
        event.preventDefault();
        event.stopPropagation();
        copyText(copyBtn.getAttribute("data-copy") || "", copyBtn);
        return;
      }

      const copyJsonBtn = event.target.closest(".multch-admin-history-copy-json");
      if (copyJsonBtn && listContent.contains(copyJsonBtn)) {
        event.preventDefault();
        event.stopPropagation();
        copyConversationJson(copyJsonBtn);
        return;
      }

      const deleteBtn = event.target.closest(".multch-admin-history-delete");
      if (deleteBtn && listContent.contains(deleteBtn)) {
        event.preventDefault();
        event.stopPropagation();
        const card = deleteBtn.closest(".multch-admin-history-card");
        if (card) {
          deleteConversation(card, deleteBtn.getAttribute("data-id"));
        }
        return;
      }

      const toggle = event.target.closest(".multch-admin-history-card__toggle");
      if (!toggle || !listContent.contains(toggle)) {
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
  }

  document.querySelectorAll(".multch-admin-history-purge").forEach(function (link) {
    link.addEventListener("click", function (event) {
      const message = link.getAttribute("data-confirm");
      if (message && !window.confirm(message)) {
        event.preventDefault();
      }
    });
  });

  initExpandedFromUrl();
})();
