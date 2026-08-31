/**
 * Core Blueprint - Safeguards → Permissions tab
 *
 * Wires three independent save actions:
 *   - #cb-core-save-operators     → cb_core_save_permission_operators
 *   - #cb-core-save-hide          → cb_core_save_permission_hide
 *   - #cb-core-save-admin-caps    → cb_core_save_permission_admin_caps
 *
 * Each save reads its own scoped form fields, posts via apiPost, and
 * updates the inline status span matching its data-target. Errors from
 * the server (lockout-preventie messages, capability rejections) surface
 * directly in the status text rather than dialogs - the messages are
 * actionable enough to read inline.
 *
 * @since   1.0.0
 */

import { qs, qsa, apiPost } from '../core/dom.js';

const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/permissions' );
const data   = dataEl ? JSON.parse( dataEl.textContent ) : {};
const i18n   = data.i18n || {};

const FORM_SELECTOR = '#cb-core-permissions-form';

const form = qs( FORM_SELECTOR );
if ( form ) {
	const nonce = form.dataset.nonce;

	// ─── ① Operators save ────────────────────────────────────────────────
	const saveOperatorsBtn = qs( '#cb-core-save-operators', form );
	if ( saveOperatorsBtn && nonce ) {
		saveOperatorsBtn.addEventListener( 'click', async () => {
			const status = qs( '[data-target="operators"]', form );
			const checked = qsa( '.cb-core-operator-toggle:checked', form );
			const operatorIds = checked.map( ( c ) => c.value );

			setStatus( status, ( i18n.saving || 'Saving…' ), 'pending' );
			saveOperatorsBtn.disabled = true;

			try {
				const response = await apiPost(
					'cb_core_save_permission_operators',
					nonce,
					{ 'operator_ids[]': operatorIds }
				);
				if ( response?.success ) {
					setStatus( status, ( i18n.saved || 'Saved.' ), 'success' );
				} else {
					setStatus(
						status,
						response?.data?.message || ( i18n.saveFailedShort || 'Save failed.' ),
						'error'
					);
				}
			} catch {
				setStatus( status, ( i18n.networkError || 'Network error - try again.' ), 'error' );
			} finally {
				saveOperatorsBtn.disabled = false;
			}
		} );
	}

	// ─── ② Hide-toggle save ──────────────────────────────────────────────
	const saveHideBtn = qs( '#cb-core-save-hide', form );
	if ( saveHideBtn && nonce ) {
		saveHideBtn.addEventListener( 'click', async () => {
			const status = qs( '[data-target="hide"]', form );
			const toggle = qs( '#cb-core-hide-toggle', form );
			const enabled = toggle?.checked ? 1 : 0;

			setStatus( status, ( i18n.saving || 'Saving…' ), 'pending' );
			saveHideBtn.disabled = true;

			try {
				const response = await apiPost(
					'cb_core_save_permission_hide',
					nonce,
					{ enabled }
				);
				if ( response?.success ) {
					setStatus( status, ( i18n.saved || 'Saved.' ), 'success' );
				} else {
					// Server rejected (likely lockout-preventie). Revert
					// the checkbox to match the actual state - flipping
					// it visually only is misleading.
					if ( toggle ) toggle.checked = ! toggle.checked;
					setStatus(
						status,
						response?.data?.message || ( i18n.saveFailedShort || 'Save failed.' ),
						'error'
					);
				}
			} catch {
				setStatus( status, ( i18n.networkError || 'Network error - try again.' ), 'error' );
			} finally {
				saveHideBtn.disabled = false;
			}
		} );
	}

	// ─── ③ Admin capability toggles save ─────────────────────────────────
	const saveAdminCapsBtn = qs( '#cb-core-save-admin-caps', form );
	if ( saveAdminCapsBtn && nonce ) {
		saveAdminCapsBtn.addEventListener( 'click', async () => {
			const status = qs( '[data-target="admin-caps"]', form );
			const reportsToggle   = qs( '#cb-core-admin-can-generate-maintenance', form );
			const integrityToggle = qs( '#cb-core-admin-can-run-integrity', form );
			const reportsEnabled   = reportsToggle?.checked   ? 1 : 0;
			const integrityEnabled = integrityToggle?.checked ? 1 : 0;

			setStatus( status, ( i18n.saving || 'Saving…' ), 'pending' );
			saveAdminCapsBtn.disabled = true;

			try {
				const response = await apiPost(
					'cb_core_save_permission_admin_caps',
					nonce,
					{
						admin_can_generate_maintenance: reportsEnabled,
						admin_can_run_integrity:        integrityEnabled,
					}
				);
				if ( response?.success ) {
					setStatus( status, ( i18n.saved || 'Saved.' ), 'success' );
				} else {
					// Revert both toggles on failure - server is the source of
					// truth, and the admin-caps save is a single transaction.
					if ( reportsToggle )   reportsToggle.checked   = ! reportsToggle.checked;
					if ( integrityToggle ) integrityToggle.checked = ! integrityToggle.checked;
					setStatus(
						status,
						response?.data?.message || ( i18n.saveFailedShort || 'Save failed.' ),
						'error'
					);
				}
			} catch {
				setStatus( status, ( i18n.networkError || 'Network error - try again.' ), 'error' );
			} finally {
				saveAdminCapsBtn.disabled = false;
			}
		} );
	}
}

function setStatus( el, text, kind = 'pending' ) {
	if ( ! el ) return;
	el.textContent = text;
	el.dataset.kind = kind;
}
