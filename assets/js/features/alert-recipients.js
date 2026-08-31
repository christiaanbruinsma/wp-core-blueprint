/**
 * Core Blueprint - Alert recipient editor
 *
 * Wires the email-alerts recipient input(s) on Preferences > Notifications
 * to the cb_core_set_alert_recipient AJAX action. Saves on click or Enter,
 * with an inline status message per group.
 *
 * Multi-group aware: iterates over every section marked with
 * `data-cb-core-notification-group` and wires its own input/button/status
 * triplet. Audit, permissions, and reports each get their own independent
 * save flow without sharing element references.
 *
 * Client-side never duplicates server-side is_email() validation - the
 * server is source of truth. Partial validity (one valid + one typo)
 * silently drops the typo on save and reflects the cleaned value back into
 * the input so the user sees what actually landed.
 *
 * @since   1.0.0
 */

import { qsa, qs, apiPost } from '../core/dom.js';

const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/alert-recipients' );
const data   = dataEl ? JSON.parse( dataEl.textContent ) : {};
const nonce  = data.nonce || '';
const i18n   = data.i18n  || {};

if ( nonce ) {
	const sections = qsa( '[data-cb-core-notification-group]' );

	sections.forEach( ( section ) => {
		const group  = section.dataset.cbCoreNotificationGroup || 'audit';
		const input  = qs( '[data-cb-core-alert-recipient]', section );
		const button = qs( '[data-cb-core-alert-recipient-save]', section );
		const status = qs( '[data-cb-core-alert-recipient-status]', section );

		if ( ! input ) return;

		const setStatus = ( text, kind = 'pending' ) => {
			if ( ! status ) return;
			status.textContent = text || '';
			status.dataset.kind = kind;
		};

		const save = async () => {
			const value = input.value;

			input.disabled = true;
			if ( button ) button.disabled = true;
			setStatus( i18n.lsSaving || 'Saving…' );

			try {
				const response = await apiPost(
					'cb_core_set_alert_recipient',
					nonce,
					{ group, recipient: value }
				);

				if ( response?.success ) {
					// Server returns the sanitised value - reflect it back so users
					// can see that "a@b.com, not-email" became "a@b.com" on save.
					if ( typeof response.data?.recipient !== 'undefined' ) {
						input.value = response.data.recipient;
					}
					setStatus( response.data?.message || i18n.lsSaved || 'Saved', 'success');
				} else {
					const message = response?.data?.message
						|| i18n.recipientSaveFailed
						|| 'Could not save.';
					setStatus( message, 'error' );
				}
			} catch ( error ) {
				const message = error?.message
					|| i18n.recipientSaveFailed
					|| 'Could not save - network error.';
				setStatus( message, 'error' );
			} finally {
				input.disabled = false;
				if ( button ) button.disabled = false;
			}
		};

		if ( button ) {
			button.addEventListener( 'click', save );
		}

		input.addEventListener( 'keydown', ( event ) => {
			if ( event.key === 'Enter' ) {
				event.preventDefault();
				save();
			}
		} );
	} );
}
