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

	function initAdminSaveBars() {
		var form = document.querySelector('form.multch-admin-form');
		if (!form) {
			return;
		}

		var dock = form.querySelector('.multch-admin-footer--dock');
		var topStatus = form.querySelector('.multch-admin-save-bar--top .multch-admin-save-bar__status');

		function markDirty() {
			form.classList.add('is-dirty');
			if (topStatus) {
				topStatus.hidden = false;
			}
		}

		form.addEventListener('input', markDirty);
		form.addEventListener('change', markDirty);

		form.addEventListener('keydown', function (event) {
			if ((event.ctrlKey || event.metaKey) && 's' === event.key.toLowerCase()) {
				event.preventDefault();
				if (typeof form.requestSubmit === 'function') {
					form.requestSubmit();
				} else {
					form.submit();
				}
			}
		});

		if (!dock) {
			return;
		}

		var spacer = document.createElement('div');
		spacer.className = 'multch-admin-footer-spacer';
		spacer.setAttribute('aria-hidden', 'true');
		dock.parentNode.insertBefore(spacer, dock);

		function layoutDock() {
			var anchor = document.getElementById('wpbody-content') || form.closest('.wrap') || form;
			var rect = anchor.getBoundingClientRect();

			dock.style.left = rect.left + 'px';
			dock.style.width = rect.width + 'px';
			spacer.style.height = dock.offsetHeight + 'px';
		}

		dock.classList.add('is-docked');
		form.classList.add('is-dock-active');
		layoutDock();

		window.addEventListener('resize', layoutDock);
		window.addEventListener('scroll', layoutDock, { passive: true });

		if (typeof ResizeObserver !== 'undefined') {
			var resizeTarget = document.getElementById('wpcontent') || document.body;
			var observer = new ResizeObserver(layoutDock);
			observer.observe(resizeTarget);
		}
	}

	initAdminSaveBars();
})();
