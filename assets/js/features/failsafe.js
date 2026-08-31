/**
 * Core Blueprint - Failsafe page
 *
 * Wires up the Failsafe (emergency bypass) page actions:
 *   - Rotate bypass token         - destructive, requires WP password re-auth
 *   - Panic activate              - destructive, requires WP password re-auth + reason
 *   - Panic deactivate            - re-enables enforcement, no password needed
 *   - Close active bypass window  - closes the bypass URL early
 *   - Copy bypass token to clipboard (click on `.cb-core-token-display`)
 *
 * All confirmation flows go through cbCore.modal.show(); all error
 * feedback goes through cbCore.toast. No window.confirm/alert/prompt.
 *
 * Uses event delegation on `document` for elements whose containing tab
 * may be loaded async. The Failsafe page itself is a synchronous PHP
 * render today, so the delegation is mostly defensive - it costs
 * nothing and keeps behaviour parity.
 *
 * @since   1.0.0
 */

import { apiPost, copyToClipboard } from '../core/dom.js';

const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/failsafe' );
const data   = dataEl ? JSON.parse( dataEl.textContent ) : {};
const nonce  = data.nonce || '';
const i18n   = data.i18n  || {};

if ( nonce ) {
	const modal = window.cbCore?.modal;
	const toast = window.cbCore?.toast;
	const busy  = window.cbCore?.busy;

	// ─── Rotate token ───────────────────────────────────────────────────────
	document.addEventListener( 'click', async ( event ) => {
		const btn = event.target.closest( '.cb-core-rotate-token' );
		if ( ! btn ) return;
		event.preventDefault();

		// Single combined modal: confirms intent AND captures password.
		// The body explains what will happen; the password input gates the
		// confirm button. Promise resolves to the password string on
		// confirm, null on cancel.
		const password = await modal.show( {
			title:        i18n.failsafeRotateTitle  || 'Rotate bypass token',
			body:         i18n.failsafeRotateBody   || 'This invalidates the current bypass URL immediately. The new URL will be shown only once on the redirected page.\n\nRe-enter your WordPress password to continue.',
			confirmLabel: i18n.failsafeRotateConfirm || 'Rotate token',
			confirmVariant: 'remediation',
			input: {
				type:        'password',
				placeholder: i18n.failsafePasswordPlaceholder || 'WordPress password',
			},
		} );

		if ( password === null ) return; // user cancelled

		busy?.button( btn, true );

		try {
			const response = await apiPost( 'cb_core_rotate_token', nonce, { password } );
			if ( response?.success && response.data?.url ) {
				window.location.href = response.data.url;
			} else {
				toast.error( response?.data?.message || i18n.networkError || 'Request failed' );
				busy?.button( btn, false );
			}
		} catch ( error ) {
			toast.error( error?.message || i18n.networkError || 'Request failed' );
			busy?.button( btn, false );
		}
	} );

	// ─── Panic activate ─────────────────────────────────────────────────────
	// Two-step flow: (1) password confirm - destructive, gated on auth;
	// (2) reason - optional, captured separately so the operator isn't
	// asked for both in one cramped form. Cancelling either step aborts.
	document.addEventListener( 'click', async ( event ) => {
		const btn = event.target.closest( '.cb-core-panic-activate' );
		if ( ! btn ) return;
		event.preventDefault();

		const password = await modal.show( {
			title:        i18n.failsafePanicTitle  || 'Activate emergency bypass',
			body:         i18n.failsafePanicBody   || 'All restrictive security features will be deactivated until you explicitly resume enforcement.\n\nRe-enter your WordPress password to confirm.',
			confirmLabel: i18n.failsafePanicConfirm || 'Activate bypass',
			confirmVariant: 'primary',
			input: {
				type:        'password',
				placeholder: i18n.failsafePasswordPlaceholder || 'WordPress password',
			},
		} );

		if ( password === null ) return;

		const reason = await modal.show( {
			title:        i18n.failsafeReasonTitle  || 'Reason for activating',
			body:         i18n.failsafeReasonBody   || 'Optionally, log why you activated the emergency bypass. This will be visible in the audit log.',
			confirmLabel: i18n.failsafeReasonConfirm || 'Activate',
			cancelLabel:  i18n.cancel || 'Cancel',
			input: {
				type:        'text',
				placeholder: i18n.failsafeReasonPlaceholder || 'Reason (optional)',
				required:    false,
			},
		} );

		if ( reason === null ) return; // cancelled at reason step

		busy?.button( btn, true );

		try {
			const response = await apiPost( 'cb_core_panic_activate', nonce, { password, reason } );
			if ( response?.success ) {
				window.location.reload();
			} else {
				toast.error( response?.data?.message || i18n.networkError || 'Request failed' );
				busy?.button( btn, false );
			}
		} catch ( error ) {
			toast.error( error?.message || i18n.networkError || 'Request failed' );
			busy?.button( btn, false );
		}
	} );

	// ─── Panic deactivate ───────────────────────────────────────────────────
	// Re-enabling enforcement is a non-destructive operation - no password
	// needed, just a confirm to prevent accidental re-locking of an
	// emergency bypass that's still being used.
	document.addEventListener( 'click', async ( event ) => {
		const btn = event.target.closest( '.cb-core-panic-deactivate' );
		if ( ! btn ) return;
		event.preventDefault();

		const ok = await modal.show( {
			title:        i18n.failsafeResumeTitle   || 'Resume enforcement?',
			body:         i18n.failsafeResumeBody    || 'All restrictive security features will be re-enabled. Anyone currently using the bypass URL will lose access immediately.',
			confirmLabel: i18n.failsafeResumeConfirm || 'Resume enforcement',
		} );

		if ( ! ok ) return;

		busy?.button( btn, true );
		try {
			const response = await apiPost( 'cb_core_panic_deactivate', nonce );
			if ( response?.success ) {
				window.location.reload();
			} else {
				toast.error( response?.data?.message || i18n.networkError || 'Request failed' );
				busy?.button( btn, false );
			}
		} catch ( error ) {
			toast.error( error?.message || i18n.networkError || 'Request failed' );
			busy?.button( btn, false );
		}
	} );

	// ─── Close active bypass window ─────────────────────────────────────────
	document.addEventListener( 'click', async ( event ) => {
		const btn = event.target.closest( '.cb-core-close-window' );
		if ( ! btn ) return;
		event.preventDefault();

		busy?.button( btn, true );
		try {
			const response = await apiPost( 'cb_core_close_window', nonce );
			if ( response?.success ) {
				window.location.reload();
			} else {
				toast.error( response?.data?.message || i18n.networkError || 'Request failed' );
				busy?.button( btn, false );
			}
		} catch ( error ) {
			toast.error( error?.message || i18n.networkError || 'Request failed' );
			busy?.button( btn, false );
		}
	} );

	// ─── Token display → copy to clipboard ──────────────────────────────────
	const copyToken = ( target ) => {
		const text = ( target?.textContent || '' ).trim();
		if ( text ) {
			copyToClipboard( text, target );
		}
	};

	document.addEventListener( 'click', ( event ) => {
		const target = event.target.closest( '.cb-core-token-display' );
		if ( target ) copyToken( target );
	} );

	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key !== 'Enter' && event.key !== ' ' ) return;
		const target = event.target.closest( '.cb-core-token-display' );
		if ( ! target ) return;
		event.preventDefault();
		copyToken( target );
	} );
}
