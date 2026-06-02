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
		var dismissBtn = notice.querySelector('.multch-admin-notice__dismiss');

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

	document.querySelectorAll('.multch-admin-notice').forEach(initNotice);

	function copyShareUrl(button, url) {
		function flashCopied() {
			var label = button.querySelector('.multch-donation-footer__chip-label');
			var original = label ? label.textContent : '';
			var copiedLabel = button.getAttribute('data-copied-label') || button.textContent || '';

			button.classList.add('is-copied');
			if (label) {
				label.textContent = copiedLabel;
			}

			window.setTimeout(function () {
				button.classList.remove('is-copied');
				if (label) {
					label.textContent = original;
				}
			}, 1800);
		}

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(url).then(flashCopied).catch(function () {
				window.prompt('', url);
			});
			return;
		}

		var textarea = document.createElement('textarea');
		textarea.value = url;
		textarea.setAttribute('readonly', '');
		textarea.style.position = 'absolute';
		textarea.style.left = '-9999px';
		document.body.appendChild(textarea);
		textarea.select();

		try {
			if (document.execCommand('copy')) {
				flashCopied();
			} else {
				window.prompt('', url);
			}
		} catch (error) {
			window.prompt('', url);
		}

		document.body.removeChild(textarea);
	}

	function initDonationFooterShare(button) {
		button.addEventListener('click', function () {
			var url = button.getAttribute('data-copy-url') || '';
			if (!url) {
				return;
			}
			copyShareUrl(button, url);
		});
	}

	document.querySelectorAll('.multch-donation-footer__chip--share').forEach(initDonationFooterShare);
})();
