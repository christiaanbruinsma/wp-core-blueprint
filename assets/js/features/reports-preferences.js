/**
 * Core Blueprint - Preferences → Reports tab
 *
 * Wires:
 *   - Master switch: optimistic toggle → cb_core_set_reports_enabled,
 *     reload on success so the admin menu re-renders (Reports menu
 *     visibility is gated server-side on State::is_enabled())
 *   - Logo picker (WP Media Library via wp.media)
 *   - Logo remove button
 *   - Live preview that updates as the user types / picks colours
 *   - Save → cb_core_save_report_branding
 *   - Reset → cb_core_reset_report_branding
 *
 * Live preview keeps users from needing to generate-and-download a PDF to
 * see what their changes look like. Updates are local-only until Save -
 * cancelling navigation away discards everything.
 *
 * @since   1.0.0
 */

import { qs, qsa, apiPost } from '../core/dom.js';

const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/reports-preferences' );
const data   = dataEl ? JSON.parse( dataEl.textContent ) : {};
const i18n   = data.i18n || {};

const FORM = qs( '#cb-core-branding-form' );
if ( FORM ) {
	const nonce      = FORM.dataset.nonce;
	const logoIdEl   = qs( '#cb-core-logo-id', FORM );
	const logoPreview = qs( '#cb-core-logo-preview', FORM );
	const logoPick   = qs( '#cb-core-logo-pick', FORM );
	const logoRemove = qs( '#cb-core-logo-remove', FORM );
	const providerNameEl    = qs( '#cb-core-provider-name', FORM );
	const providerContactEl = qs( '#cb-core-provider-contact', FORM );
	const colorEl    = qs( '#cb-core-accent-color', FORM );
	const colorHexEl = qs( '#cb-core-accent-hex', FORM );
	const saveBtn    = qs( '#cb-core-save-branding', FORM );
	const resetBtn   = qs( '#cb-core-reset-branding', FORM );
	const statusEl   = qs( '[data-target="branding"]', FORM );

	// ─── Logo + colour sync ──────────────────────────────────────────────
	//
	// The live-preview column was removed in 1.3.34-dev. The remaining wiring
	// here is the colour-picker / hex-input two-way sync (so editing either
	// keeps the other current) and the logo picker / removal flow (so the
	// inline preview thumb in the form reflects the current selection).

	const updateLogoPreview = ( url ) => {
		if ( ! logoPreview ) return;
		if ( url ) {
			logoPreview.innerHTML = `<img src="${ encodeURI( url ) }" alt="">`;
			logoPreview.dataset.hasLogo = 'yes';
			if ( logoRemove ) logoRemove.hidden = false;
			if ( logoPick )   logoPick.textContent = ( i18n.brandingChangeLogo || 'Change logo' );
		} else {
			logoPreview.innerHTML = `<span class="cb-core-logo-placeholder">${
				i18n.brandingNoLogo || 'No logo set'
			}</span>`;
			logoPreview.dataset.hasLogo = 'no';
			if ( logoRemove ) logoRemove.hidden = true;
			if ( logoPick )   logoPick.textContent = ( i18n.brandingSelectLogo || 'Select logo' );
		}
	};

	// Colour-picker / hex-input two-way sync.
	colorEl?.addEventListener( 'input', ( ev ) => {
		const hex = ev.target.value;
		if ( colorHexEl ) colorHexEl.value = hex;
	} );
	colorHexEl?.addEventListener( 'input', ( ev ) => {
		const hex = ev.target.value;
		if ( /^#[0-9a-fA-F]{6}$/.test( hex ) ) {
			if ( colorEl ) colorEl.value = hex;
		}
	} );

	// ─── Logo picker (WP Media Library) ──────────────────────────────────

	let mediaFrame = null;

	logoPick?.addEventListener( 'click', ( ev ) => {
		ev.preventDefault();
		if ( ! window.wp || ! window.wp.media ) {
			setStatus( statusEl, ( i18n.brandingMediaUnavailable || 'Media Library not available - reload the page.' ), 'error' );
			return;
		}

		// Reuse the frame so opening the picker twice doesn't duplicate state.
		if ( ! mediaFrame ) {
			mediaFrame = window.wp.media( {
				title:    ( i18n.brandingPickerTitle || 'Select logo' ),
				button:   { text: ( i18n.brandingPickerButton || 'Use this image' ) },
				multiple: false,
				library:  { type: 'image' },
			} );

			mediaFrame.on( 'select', () => {
				const attachment = mediaFrame.state().get( 'selection' ).first().toJSON();
				if ( ! attachment || ! attachment.id ) return;

				if ( logoIdEl ) logoIdEl.value = String( attachment.id );

				// Prefer the medium size where available, fall back to full.
				const url = attachment.sizes?.medium?.url || attachment.sizes?.full?.url || attachment.url;
				updateLogoPreview( url );
} );
		}
		mediaFrame.open();
	} );

	logoRemove?.addEventListener( 'click', ( ev ) => {
		ev.preventDefault();
		if ( logoIdEl ) logoIdEl.value = '0';
		updateLogoPreview( '' );
	} );

	// ─── Save ────────────────────────────────────────────────────────────

	saveBtn?.addEventListener( 'click', async () => {
		if ( ! nonce ) {
			setStatus( statusEl, ( i18n.reportsNonceMissing || 'Nonce missing - reload the page.' ), 'error' );
			return;
		}

		// Final hex validation - if the user typed something invalid in
		// the hex input we let the server fall back, but we surface a
		// hint here so they know the value isn't what they expected.
		const hex = colorHexEl?.value || colorEl?.value || '';
		if ( ! /^#[0-9a-fA-F]{6}$/.test( hex ) ) {
			setStatus( statusEl, ( i18n.brandingInvalidHex || 'Hex colour must be in #RRGGBB form.' ), 'error' );
			return;
		}

		setStatus( statusEl, ( i18n.saving || 'Saving…' ), 'pending' );
		saveBtn.disabled = true;

		try {
			const response = await apiPost( 'cb_core_save_report_branding', nonce, {
				logo_attachment_id: logoIdEl?.value || 0,
				provider_name:       providerNameEl?.value || '',
				provider_contact:    providerContactEl?.value || '',
				accent_color:       hex,
			} );

			if ( response?.success ) {
				setStatus( statusEl, ( i18n.saved || 'Saved.' ), 'success' );

				// Reflect any server-side normalisation (sanitised hex,
				// resolved logo URL) back into the form + preview.
				if ( response.data?.logo_url !== undefined ) {
					updateLogoPreview( response.data.logo_url );
}
				if ( response.data?.accent_color ) {
					if ( colorEl )    colorEl.value    = response.data.accent_color;
					if ( colorHexEl ) colorHexEl.value = response.data.accent_color;
}
			} else {
				setStatus(
					statusEl,
					response?.data?.message || ( i18n.saveFailedShort || 'Save failed.' ),
					'error'
				);
			}
		} catch {
			setStatus( statusEl, ( i18n.networkError || 'Network error - try again.' ), 'error' );
		} finally {
			saveBtn.disabled = false;
		}
	} );

	// ─── Reset to defaults ───────────────────────────────────────────────

	resetBtn?.addEventListener( 'click', async () => {
		if ( ! nonce ) {
			setStatus( statusEl, ( i18n.reportsNonceMissing || 'Nonce missing - reload the page.' ), 'error' );
			return;
		}

		const modal = window.cbCore?.modal;

		const ok = modal
			? await modal.show( {
				title:        i18n.brandingConfirmResetTitle  || 'Reset report settings?',
				body:         i18n.brandingConfirmReset
					|| 'Logo, report provider details, and accent colour will be cleared and reset to defaults.',
				confirmLabel: i18n.brandingConfirmResetConfirm || 'Reset to defaults',
				confirmVariant: 'danger',
			} )
			: true;

		if ( ! ok ) return;

		setStatus( statusEl, ( i18n.brandingResetting || 'Resetting…' ), 'pending' );
		resetBtn.disabled = true;
		saveBtn.disabled  = true;

		try {
			const response = await apiPost( 'cb_core_reset_report_branding', nonce, {} );

			if ( response?.success ) {
				// Apply the returned defaults to the form + preview in
				// place, no page reload.
				const d = response.data || {};
				if ( logoIdEl )   logoIdEl.value   = String( d.logo_attachment_id ?? 0 );
				if ( providerNameEl )    providerNameEl.value    = d.provider_name ?? '';
				if ( providerContactEl ) providerContactEl.value = d.provider_contact ?? '';
				if ( colorEl )    colorEl.value    = d.accent_color ?? '#0064c8';
				if ( colorHexEl ) colorHexEl.value = d.accent_color ?? '#0064c8';
				updateLogoPreview( d.logo_url || '' );
				setStatus( statusEl, ( i18n.brandingResetDone || 'Reset to defaults.' ), 'success' );
			} else {
				setStatus(
					statusEl,
					response?.data?.message || ( i18n.saveFailedShort || 'Save failed.' ),
					'error'
				);
			}
		} catch {
			setStatus( statusEl, ( i18n.networkError || 'Network error - try again.' ), 'error' );
		} finally {
			resetBtn.disabled = false;
			saveBtn.disabled  = false;
		}
	} );

}

function setStatus( el, text, kind = 'pending' ) {
	if ( ! el ) return;
	el.textContent = text;
	el.dataset.kind = kind;
}
