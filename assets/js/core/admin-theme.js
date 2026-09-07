/**
 * Core Blueprint Admin Theme browser API.
 *
 * CSS tokens remain the primary integration contract. This small runtime exists
 * for interfaces that must redraw non-CSS surfaces (charts, canvases, editors)
 * when the HUD changes theme without a page reload.
 *
 * WordPress Core presentation adapters are Dark-only. Light intentionally falls
 * back to native WordPress presentation while Core Blueprint components keep
 * using the shared semantic token contract in both modes.
 *
 * Public API:
 *   window.cbAdminTheme.theme()
 *   window.cbAdminTheme.mode()
 *   window.cbAdminTheme.state()
 *
 * Events:
 *   cb:admin-theme-ready
 *   cb:admin-theme-change
 *
 * @package CB\Core
 * @since   1.0.0
 */

(function () {
	'use strict';

	const root = document.documentElement;
	const adapterLinkSelector = [
		'link#cb-core-css-admin-theme-css',
		'link[id^="cb-core-css-admin-theme-core-"]',
		'link[id^="cb-core-css-admin-theme-integration-"]',
	].join(',');

	let lastState = readState();
	let pending = false;

	function readState() {
		return {
			theme: root.getAttribute('data-cb-theme') || '',
			mode: root.getAttribute('data-cb-mode') || '',
		};
	}

	function state() {
		const current = readState();
		return { theme: current.theme, mode: current.mode };
	}

	function syncAdapterMedia(mode) {
		const targetMedia = mode === 'dark' ? 'all' : 'not all';
		const links = document.querySelectorAll(adapterLinkSelector);

		for (const link of links) {
			if (link instanceof HTMLLinkElement) {
				link.media = targetMedia;
			}
		}
	}

	window.cbAdminTheme = Object.freeze({
		theme: function () { return readState().theme; },
		mode: function () { return readState().mode; },
		state: state,
	});

	function dispatch(name, detail) {
		document.dispatchEvent(new CustomEvent(name, { detail: detail }));
	}

	function flushChange() {
		pending = false;
		const nextState = readState();
		if (nextState.theme === lastState.theme && nextState.mode === lastState.mode) {
			return;
		}

		const previous = lastState;
		lastState = nextState;
		syncAdapterMedia(nextState.mode);
		dispatch('cb:admin-theme-change', {
			theme: nextState.theme,
			mode: nextState.mode,
			previousTheme: previous.theme,
			previousMode: previous.mode,
		});
	}

	const observer = new MutationObserver(function (mutations) {
		for (const mutation of mutations) {
			if (mutation.type === 'attributes' && (mutation.attributeName === 'data-cb-theme' || mutation.attributeName === 'data-cb-mode')) {
				if (!pending) {
					pending = true;
					queueMicrotask(flushChange);
				}
				break;
			}
		}
	});

	observer.observe(root, {
		attributes: true,
		attributeFilter: ['data-cb-theme', 'data-cb-mode'],
	});

	// Align server-rendered media attributes with the browser-resolved mode.
	// This is especially important for Auto, where PHP intentionally emits a
	// prefers-color-scheme media query before the browser resolves final state.
	syncAdapterMedia(lastState.mode);
	dispatch('cb:admin-theme-ready', state());
})();
