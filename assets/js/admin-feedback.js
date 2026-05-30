(function () {
	'use strict';

	var DISMISS_MS = 6000;
	var FADE_MS = 350;

	function dismissNotice(notice) {
		if (!notice || notice.classList.contains('is-dismissed')) {
			return;
		}

		notice.classList.add('is-dismissed');

		window.setTimeout(function () {
			if (notice.parentNode) {
				notice.parentNode.removeChild(notice);
			}
		}, FADE_MS);
	}

	function initNotice(notice) {
		var dismissBtn = notice.querySelector('.chatbot-admin-notice__dismiss');

		if (dismissBtn) {
			dismissBtn.addEventListener('click', function () {
				dismissNotice(notice);
			});
		}

		if ('true' === notice.getAttribute('data-auto-dismiss')) {
			window.setTimeout(function () {
				dismissNotice(notice);
			}, DISMISS_MS);
		}
	}

	document.querySelectorAll('.chatbot-admin-notice').forEach(initNotice);
})();
