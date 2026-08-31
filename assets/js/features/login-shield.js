/**
 * Core Blueprint - Login Shield form
 *
 * Wires up the Login Shield configuration form (Safeguards › Login Shield).
 * Handles:
 *   - Live slug sanitisation + URL preview as the user types
 *   - Conditional reveal of the "Custom URL" field when redirect-after-
 *     login is set to "custom"
 *   - Confirmation prompt when switching to Strict mode (with revert if
 *     the user backs out)
 *   - AJAX save (cb_core_login_shield_save) with inline status feedback
 *   - Server-side URL test (cb_core_login_shield_test) surfacing the HTTP
 *     status code back to the operator
 *
 * Gated on presence of the form element + the per-module nonce - the whole
 * module no-ops on any page that doesn't render the Login Shield tab.
 *
 *
 * @since   1.0.0
 */

import { qs, qsa, apiPost } from '../core/dom.js';

const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/login-shield' );
const data   = dataEl ? JSON.parse( dataEl.textContent ) : {};
const nonce  = data.nonce || '';
const i18n   = data.i18n  || {};

const form = qs( '[data-cb-core-ls-form]' );

if ( form && nonce ) {
	const slug         = qs( '[data-cb-core-ls-slug]', form );
	const preview      = qs( '[data-cb-core-ls-preview]', form );
	const previewUrl   = qs( '[data-cb-core-ls-preview-url]', form );
	const redirect     = qs( '[data-cb-core-ls-redirect]', form );
	const redirectWrap = qs( '[data-cb-core-ls-redirect-custom]', form );
	const modeInputs   = qsa( '[data-cb-core-ls-mode]', form );
	const saveBtn      = qs( '[data-cb-core-ls-save]', form );
	const testBtn      = qs( '[data-cb-core-ls-test]', form );
	const status       = qs( '[data-cb-core-ls-save-status]', form );


	const urlBase = preview?.dataset.urlBase || '';

	let lastCheckedMode = qs( '[data-cb-core-ls-mode]:checked', form )?.value || 'standard';

	// Mirror the server-side sanitize_slug() - lowercase, [a-z0-9-] only,
	// collapse repeated hyphens, trim leading/trailing hyphens. Keeping
	// this in sync with the PHP equivalent prevents surprises where the
	// live preview shows something the server refuses to save.
	const sanitizeSlug = ( raw ) =>
		String( raw || '' )
			.toLowerCase()
			.replace( /[^a-z0-9-]/g, '' )
			.replace( /-+/g, '-' )
			.replace( /^-+|-+$/g, '' );

	const updatePreview = () => {
		if ( ! slug || ! previewUrl || ! testBtn ) return;
		const cleaned = sanitizeSlug( slug.value );
		if ( cleaned ) {
			previewUrl.textContent = `${ urlBase }${ cleaned }/`;
			previewUrl.style.display = '';
			testBtn.disabled = false;
		} else {
			previewUrl.style.display = 'none';
			testBtn.disabled = true;
		}
	};

	const updateRedirectReveal = () => {
		if ( ! redirect || ! redirectWrap ) return;
		if ( redirect.value === 'custom' ) {
			redirectWrap.removeAttribute( 'hidden' );
		} else {
			redirectWrap.setAttribute( 'hidden', 'hidden' );
		}
	};

	const setStatus = ( text, kind = 'pending' ) => {
		if ( ! status ) return;
		status.textContent = text || '';
		status.dataset.kind = kind;
	};

	const serializeConfig = () => {
		const checkedMode = qs( '[data-cb-core-ls-mode]:checked', form )?.value || 'standard';
		const customUrl   = qs( '[name="redirect_custom_url"]', form )?.value || '';
		const respCode    = qs( '[name="block_response_code"]:checked', form )?.value || '404';
		// `enabled` deliberately omitted - Dashboard activation persists it
		// via ActivationRegistry. The save handler treats
		// absence as "preserve current state" so we don't accidentally
		// flip the master off on a routine settings save.
		return {
			slug:                 sanitizeSlug( slug?.value ),
			mode:                 checkedMode,
			redirect_after_login: redirect?.value || 'dashboard',
			redirect_custom_url:  customUrl,
			block_response_code:  respCode,
		};
	};

	// ─── Slug input - live preview ─────────────────────────────────────────
	slug?.addEventListener( 'input', () => {
		// Don't rewrite the user's raw input mid-keystroke - just update preview.
		updatePreview();
	} );

	slug?.addEventListener( 'blur', () => {
		// On blur, normalise the stored value so the next save+re-render
		// cycle matches what the preview showed.
		const cleaned = sanitizeSlug( slug.value );
		if ( cleaned !== slug.value ) {
			slug.value = cleaned;
			updatePreview();
		}
	} );

	// ─── Redirect dropdown ──────────────────────────────────────────────────
	redirect?.addEventListener( 'change', updateRedirectReveal );

	// ─── Strict-mode confirmation ───────────────────────────────────────────
	// If the user backs out of the confirm, revert to the previously-selected
	// mode so we don't silently leave Strict selected without their consent.
	for ( const input of modeInputs ) {
		input.addEventListener( 'change', async () => {
			const newMode = input.value;
			if ( newMode === 'strict' && lastCheckedMode !== 'strict' ) {
				const ok = window.cbCore?.modal
					? await window.cbCore.modal.show( {
						title:        i18n.lsConfirmStrictTitle  || 'Enable Strict mode?',
						body:         i18n.lsConfirmStrict
							|| 'Strict mode blocks /wp-admin for guests - only your custom login URL works. If you forget the URL, you can only get back in via the Failsafe bypass (see Failsafe tab).',
						confirmLabel: i18n.lsConfirmStrictConfirm || 'Enable Strict mode',
						confirmVariant: 'danger',
					} )
					: true;

				if ( ! ok ) {
					const previous = qs( `[data-cb-core-ls-mode][value="${ lastCheckedMode }"]`, form );
					if ( previous ) previous.checked = true;
					return;
				}
			}
			lastCheckedMode = newMode;
		} );
	}

	// ─── Save ───────────────────────────────────────────────────────────────
	saveBtn?.addEventListener( 'click', async () => {
		saveBtn.disabled = true;
		if ( testBtn ) testBtn.disabled = true;
		setStatus( i18n.lsSaving || 'Saving…' );

		// Local flag: when a reload is scheduled on the success path we
		// must NOT re-enable the buttons in the finally block (a second
		// click would trigger a second save while the page is already
		// on its way out).
		let reloadPending = false;

		try {
			const response = await apiPost( 'cb_core_login_shield_save', nonce, serializeConfig() );
			if ( response?.success ) {
				// Reload so URLs that were rendered server-side against the
				// previous configuration regenerate with the new slug - most
				// importantly the admin-bar logout link, but also any
				// rendered lostpassword links and similar. Sticking to a
				// reload (rather than DOM-patching individual URLs) is
				// deliberate: it's the only way to guarantee no stale
				// reference remains on the page. Login Shield save is
				// infrequent enough that the brief flash is acceptable.
				setStatus( i18n.lsSavedReloading || 'Saved - reloading…', 'success' );
				reloadPending = true;
				setTimeout( () => window.location.reload(), 800 );
				return;
			}

			const msg = response?.data?.message
				|| i18n.lsSaveFailed
				|| 'Could not save Login Shield settings - try again.';
			setStatus( msg, 'error' );
		} catch ( error ) {
			const msg = error?.message
				|| i18n.lsSaveFailed
				|| 'Could not save Login Shield settings - try again.';
			setStatus( msg, 'error' );
		} finally {
			if ( ! reloadPending ) {
				saveBtn.disabled = false;
				updatePreview(); // re-evaluates whether Test button should be enabled
			}
		}
	} );

	// ─── Test ───────────────────────────────────────────────────────────────
	testBtn?.addEventListener( 'click', async () => {
		testBtn.disabled = true;
		setStatus( i18n.lsTesting || 'Testing…' );

		try {
			const response = await apiPost( 'cb_core_login_shield_test', nonce );
			if ( response?.success ) {
				setStatus( response.data?.message || '', response.data?.ok ? 'success' : 'error' );
			} else {
				const msg = response?.data?.message || i18n.lsTestFailed || 'Test request failed.';
				setStatus( msg, 'error' );
			}
		} catch ( error ) {
			const msg = error?.message || i18n.lsTestFailed || 'Test request failed.';
			setStatus( msg, 'error' );
		} finally {
			testBtn.disabled = ! sanitizeSlug( slug?.value );
		}
	} );


	// ─── Initial paint - ensure preview + reveal match DOM state ───────────
	updatePreview();
	updateRedirectReveal();
}
