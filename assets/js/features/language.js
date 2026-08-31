/**
 * Core Blueprint - Language page
 *
 * Locale + description-mode interactions on Preferences > Language:
 *   - Scope toggle ('user' vs 'site default') - re-syncs both controls
 *     to reflect the chosen scope's saved value
 *   - Locale dropdown change → setLocale() then page reload (locale takes
 *     effect on next PHP render, not in-place)
 *   - Reset locale link → clear user preference + reload
 *   - Description-mode radio cards → cb_core_set_description_mode AJAX
 *
 * @since   1.0.0
 */

import { qs, qsa, apiPost } from '../core/dom.js';
import { setLocale } from '../core/public-api.js';

const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/language' );
const data   = dataEl ? JSON.parse( dataEl.textContent ) : {};
const nonce  = data.nonce || '';
const i18n   = data.i18n  || {};

const page = qs( '.cb-core-language' );

if ( page && nonce ) {
	const toolbar  = qs( '.cb-core-appearance-toolbar', page );
	const statusEl = toolbar ? qs( '.cb-core-lang-status', toolbar ) : null;
	const select   = qs( '#cb-core-locale-select', page );
	const reset    = qs( '.cb-core-locale-reset', page );
	const radios   = qsa( '.cb-core-radio-card--checkable', page );

	const state = { scope: 'user' };

	let statusTimer = null;

	const setStatus = ( text, kind = 'pending' ) => {
		if ( ! statusEl ) return;
		statusEl.textContent = text || '';
		statusEl.dataset.kind = kind;
		if ( text ) {
			window.clearTimeout( statusTimer );
			statusTimer = window.setTimeout( () => {
				statusEl.textContent = '';
				delete statusEl.dataset.kind;
			}, 3500 );
		}
	};

	const reloadSoon = ( delay = 800 ) => {
		window.setTimeout( () => window.location.reload(), delay );
	};

	// ─── Scope toggle ────────────────────────────────────────────────────────
	if ( toolbar ) {
		toolbar.addEventListener( 'click', ( event ) => {
			const btn = event.target.closest( '.cb-core-scope-option' );
			if ( ! btn || ! toolbar.contains( btn ) ) return;

			const scope = btn.getAttribute( 'data-scope' );
			if ( ! scope || scope === state.scope ) return;

			state.scope = scope;
			for ( const opt of qsa( '.cb-core-scope-option', toolbar ) ) {
				opt.classList.remove( 'is-active' );
				opt.setAttribute( 'aria-selected', 'false' );
			}
			btn.classList.add( 'is-active' );
			btn.setAttribute( 'aria-selected', 'true' );

			// Re-sync dropdown + radios to reflect the chosen scope's saved value.
			const locSection  = qs( '.cb-core-pref-locale',   page );
			const descSection = qs( '.cb-core-pref-descmode', page );

			if ( locSection && select ) {
				const targetLocale = scope === 'site'
					? locSection.getAttribute( 'data-site-default' )
					: locSection.getAttribute( 'data-user-pref' );
				if ( targetLocale ) select.value = targetLocale;
			}

			if ( descSection ) {
				const siteDefault = descSection.getAttribute( 'data-site-default' );
				const userPref    = descSection.getAttribute( 'data-user-pref' );
				const targetMode  = scope === 'site'
					? ( siteDefault || 'plain' )
					: ( userPref === '' ? 'inherit' : userPref );

				for ( const radio of radios ) {
					const mode     = radio.getAttribute( 'data-mode' );
					const userOnly = radio.getAttribute( 'data-user-only' ) === '1';

					// "inherit" is only meaningful for user-scope; visually dim it
					// when site tab active.
					if ( scope === 'site' && userOnly ) {
						radio.style.opacity       = '0.4';
						radio.style.pointerEvents = 'none';
					} else {
						radio.style.opacity       = '';
						radio.style.pointerEvents = '';
					}

					radio.classList.toggle( 'is-selected-user', scope === 'user' && mode === targetMode );
					radio.classList.toggle( 'is-selected-site', scope === 'site' && mode === targetMode );

					const input = qs( 'input[type="radio"]', radio );
					if ( input ) input.checked = ( mode === targetMode );
				}
			}

			setStatus( '' );
		} );
	}

	// ─── Locale dropdown ─────────────────────────────────────────────────────
	if ( select ) {
		select.addEventListener( 'change', async () => {
			const value = select.value;
			if ( ! value ) return;

			setStatus( 'Saving…' );
			try {
				const response = await setLocale( value, state.scope );
				if ( ! response?.success ) {
					setStatus( response?.data?.message || i18n.saveFailed || 'Save failed', 'error' );
					return;
				}
				setStatus( `${ i18n.saved || 'Saved' } · reloading…`, 'success' );
				// Locale takes effect on next PHP render - reload after a short delay.
				reloadSoon();
			} catch ( error ) {
				setStatus( error?.message || i18n.saveFailed || 'Save failed', 'error' );
			}
		} );
	}

	// ─── Reset locale ────────────────────────────────────────────────────────
	if ( reset ) {
		reset.addEventListener( 'click', async ( event ) => {
			event.preventDefault();
			setStatus( 'Clearing…' );
			try {
				const response = await setLocale( '', 'user' );
				if ( ! response?.success ) {
					setStatus( response?.data?.message || 'Reset failed', 'error' );
					return;
				}
				setStatus( 'Cleared · reloading…', 'success' );
				reloadSoon();
			} catch {
				setStatus( i18n.saveFailed || 'Reset failed', 'error' );
			}
		} );
	}

	// ─── Description-mode radio cards ────────────────────────────────────────
	for ( const card of radios ) {
		card.addEventListener( 'click', async ( event ) => {
			const mode = card.getAttribute( 'data-mode' );
			if ( ! mode ) return;

			// Ignore disabled "inherit" option on site scope.
			if ( state.scope === 'site' && card.getAttribute( 'data-user-only' ) === '1' ) {
				event.preventDefault();
				return;
			}

			const selectedClass = state.scope === 'user' ? 'is-selected-user' : 'is-selected-site';
			for ( const r of radios ) r.classList.remove( selectedClass );
			card.classList.add( selectedClass );

			const input = qs( 'input[type="radio"]', card );
			if ( input ) input.checked = true;

			if ( ! nonce ) {
				setStatus( 'Admin nonce unavailable - refresh the page and try again.', 'error' );
				return;
			}

			setStatus( 'Saving…' );
			try {
				const response = await apiPost(
					'cb_core_set_description_mode',
					nonce,
					{ scope: state.scope, mode }
				);
				if ( ! response?.success ) {
					setStatus( response?.data?.message || i18n.saveFailed || 'Save failed', 'error' );
					return;
				}
				// Update stored state attribute so scope-toggle re-sync is accurate.
				const section = qs( '.cb-core-pref-descmode', page );
				if ( section ) {
					if ( state.scope === 'user' ) {
						section.setAttribute( 'data-user-pref', mode === 'inherit' ? '' : mode );
					} else {
						section.setAttribute( 'data-site-default', mode );
					}
				}
				setStatus( i18n.saved || 'Saved', 'success' );
			} catch ( error ) {
				setStatus( error?.message || i18n.saveFailed || 'Save failed', 'error' );
			}
		} );
	}
}
