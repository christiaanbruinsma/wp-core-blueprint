/**
 * Core Blueprint - Appearance page
 *
 * Theme picker interactions on Preferences > Appearance:
 *   - Scope toggle ('user' vs 'site default')
 *   - Theme card click → live preview + persist via setTheme()
 *   - Reset link → clear user preference, reapply resolved theme
 *
 * @since   1.0.0
 */

import { qs, qsa } from '../core/dom.js';
import { setTheme } from '../core/public-api.js';

const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/appearance' );
const data   = dataEl ? JSON.parse( dataEl.textContent ) : {};
const i18n   = data.i18n || {};

const grid = qs( '.cb-core-theme-grid' );

if ( grid ) {
	const toolbar     = qs( '.cb-core-appearance-toolbar' );
	const statusEl    = toolbar ? qs( '.cb-core-appearance-status', toolbar ) : null;
	const cards       = qsa( '.cb-core-theme-card', grid );
	const resetLinks  = qsa( '.cb-core-theme-reset' );

	const state = {
		scope:       'user',
		userPref:    grid.getAttribute( 'data-user-pref' )    || '',
		siteDefault: grid.getAttribute( 'data-site-default' ) || '',
	};

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

	const refreshCardStates = () => {
		for ( const card of cards ) {
			const slug = card.getAttribute( 'data-theme' );
			const isSelectedUser = ( state.userPref === slug )
				|| ( state.userPref === '' && state.siteDefault === slug );
			card.classList.toggle( 'is-selected-user', isSelectedUser );
			card.classList.toggle( 'is-selected-site', state.siteDefault === slug );
			card.setAttribute( 'aria-pressed', state.userPref === slug ? 'true' : 'false' );
		}
	};

	const applyThemeToBody = ( slug ) => {
		// Defensive: remove any legacy pre-paint stylenode from earlier versions.
		const prePaint = qs( '#cb-core-prepaint' );
		if ( prePaint ) prePaint.remove();

		// Set on <html> and <body> both - chrome rules are scoped on html,
		// in-content rules on body. Keeping both in sync.
		const setOn = ( el, themeSlug, mode ) => {
			el.setAttribute( 'data-cb-theme', themeSlug );
			if ( mode === 'dark' || mode === 'light' ) {
				el.setAttribute( 'data-cb-mode', mode );
			} else {
				el.removeAttribute( 'data-cb-mode' );
			}
		};

		if ( slug === 'auto' ) {
			const isLight = window.matchMedia?.( '(prefers-color-scheme: light)' ).matches;
			const resolved = isLight ? 'core_blueprint_light' : 'core_blueprint_dark';
			const mode     = isLight ? 'light' : 'dark';
			setOn( document.documentElement, resolved, mode );
			setOn( document.body,             resolved, mode );
			return;
		}

		let mode = 'custom';
		if ( slug === 'core_blueprint_dark' )       mode = 'dark';
		else if ( slug === 'core_blueprint_light' ) mode = 'light';
		setOn( document.documentElement, slug, mode );
		setOn( document.body,             slug, mode );
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
			setStatus( '' );
		} );
	}

	// ─── Theme card click ────────────────────────────────────────────────────
	for ( const card of cards ) {
		card.addEventListener( 'click', async () => {
			const slug = card.getAttribute( 'data-theme' );
			if ( ! slug ) return;

			// Live preview - instant.
			applyThemeToBody( slug );

			// Persist via AJAX.
			setStatus( i18n.saved ? '...' : 'Saving…' );

			try {
				const response = await setTheme( slug, state.scope );
				if ( ! response?.success ) {
					setStatus( response?.data?.message || i18n.saveFailed || 'Save failed', 'error' );
					return;
				}
				if ( state.scope === 'user' ) state.userPref    = slug;
				else                          state.siteDefault = slug;
				refreshCardStates();
				setStatus( i18n.saved || 'Saved', 'success' );
			} catch ( error ) {
				setStatus( error?.message || i18n.saveFailed || 'Save failed', 'error' );
			}
		} );
	}

	// ─── Reset link (clear user preference) ──────────────────────────────────
	for ( const reset of resetLinks ) {
		reset.addEventListener( 'click', async ( event ) => {
			event.preventDefault();
			setStatus( 'Clearing…' );

			try {
				const response = await setTheme( '', 'user' );
				if ( ! response?.success ) {
					setStatus( response?.data?.message || 'Reset failed', 'error' );
					return;
				}
				state.userPref = '';
				refreshCardStates();
				// Re-apply to body based on resolved theme from server response.
				if ( response.data?.current ) {
					applyThemeToBody( response.data.current );
				}
				setStatus( i18n.saved || 'Saved', 'success' );
			} catch {
				setStatus( i18n.saveFailed || 'Reset failed', 'error' );
			}
		} );
	}
}
