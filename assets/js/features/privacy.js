/**
 * Core Blueprint - Privacy & Logging page
 *
 * Wires up the Privacy & Logging configuration form (Preferences >
 * Privacy). Two actions:
 *
 *   - Save settings   - collects ip_mode + verbosity[*] + retention[*]
 *                       and POSTs as a single envelope to
 *                       cb_core_save_privacy. On success the page
 *                       reloads so the form re-renders against the
 *                       new server state.
 *   - Apply preset    - destructive (overwrites all current settings)
 *                       so guarded by window.confirm. Reloads on
 *                       success.
 *
 * The verbosity/retention groups are dynamic - they're rendered server-
 * side based on the registered event categories. We collect them by
 * matching the input `name` attribute (`verbosity[<key>]` /
 * `retention[<key>]`) rather than maintaining a hardcoded key list, so
 * adding a new category requires zero JS changes.
 *
 * @since   1.0.0
 */

import { qs, qsa, apiPost } from '../core/dom.js';

const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/privacy' );
const data   = dataEl ? JSON.parse( dataEl.textContent ) : {};
const nonce  = data.nonce || '';
const i18n   = data.i18n  || {};

if ( nonce ) {
	const modal = window.cbCore?.modal;
	const toast = window.cbCore?.toast;

	// ─── Save settings ──────────────────────────────────────────────────────
	document.addEventListener( 'click', async ( event ) => {
		const btn = event.target.closest( '.cb-core-privacy-save' );
		if ( ! btn ) return;
		event.preventDefault();

		const form = qs( '#cb-core-privacy-form' );
		if ( ! form ) return;

		const status = qs( '.cb-core-form-status', form );

		// Collect form values. apiPost expects a flat URL-encoded body, so
		// nested verbosity[*] / retention[*] keys are sent as
		// "verbosity[audit]=detailed" etc. - same wire format jQuery
		// produced from a nested object.
		const ipMode = qs( 'input[name="ip_mode"]:checked', form )?.value || 'anonymized';
		const data = { ip_mode: ipMode };

		for ( const select of qsa( 'select[name^="verbosity["]', form ) ) {
			const name = select.getAttribute( 'name' );
			const match = name?.match( /verbosity\[([^\]]+)\]/ );
			if ( match ) data[ `verbosity[${ match[ 1 ] }]` ] = select.value;
		}
		for ( const select of qsa( 'select[name^="retention["]', form ) ) {
			const name = select.getAttribute( 'name' );
			const match = name?.match( /retention\[([^\]]+)\]/ );
			if ( match ) data[ `retention[${ match[ 1 ] }]` ] = select.value;
		}

		const setStatus = ( text, kind = 'pending' ) => {
			if ( ! status ) return;
			status.textContent = text || '';
			status.dataset.kind = kind;
		};

		btn.disabled = true;
		setStatus( i18n.saving || 'Saving…', 'pending' );

		try {
			const response = await apiPost( 'cb_core_save_privacy', nonce, data );
			if ( response?.success ) {
				setStatus( i18n.saved || 'Saved', 'success' );
				window.setTimeout( () => window.location.reload(), 600 );
			} else {
				const msg = response?.data?.message || i18n.saveFailed || 'Save failed';
				setStatus( msg, 'error' );
			}
		} catch ( error ) {
			setStatus( error?.message || i18n.saveFailed || 'Save failed', 'error' );
		} finally {
			btn.disabled = false;
		}
	} );

	// ─── Apply preset ───────────────────────────────────────────────────────
	document.addEventListener( 'click', async ( event ) => {
		const btn = event.target.closest( '.cb-core-privacy-apply-preset' );
		if ( ! btn ) return;
		event.preventDefault();

		const preset = qs( 'input[name="preset"]:checked' )?.value;
		if ( ! preset ) return;

		const ok = modal
			? await modal.show( {
				title:        i18n.confirmPresetTitle  || 'Apply this preset?',
				body:         i18n.confirmPreset       || 'All current settings will be overwritten.',
				confirmLabel: i18n.confirmPresetConfirm || 'Apply preset',
			} )
			: window.confirm( i18n.confirmPreset || 'Apply this preset? All current settings will be overwritten.' );

		if ( ! ok ) return;

		btn.disabled = true;
		try {
			const response = await apiPost( 'cb_core_apply_preset', nonce, { preset } );
			if ( response?.success ) {
				window.location.reload();
			} else {
				toast?.error( response?.data?.message || i18n.privacyPresetFailed || 'Preset apply failed' );
				btn.disabled = false;
			}
		} catch ( error ) {
			toast?.error( error?.message || i18n.privacyPresetFailed || 'Preset apply failed' );
			btn.disabled = false;
		}
	} );
}
