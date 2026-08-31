/**
 * Core Blueprint - Notifications toggles (severity + event-type)
 *
 * Wires the alert checkboxes on Preferences > Notifications to the
 * cb_core_toggle_alert AJAX action. Two toggle styles share this handler:
 *
 *   1. **Severity toggles** (legacy, audit group only) - checkbox carries
 *      `data-severity="critical|warning|notice|info"`.
 *   2. **Event-type toggles** (v1.1+, permissions + reports groups) -
 *      checkbox carries `data-cb-core-alert-group="<group>"` plus
 *      `data-alert-key="role_change|operator_guard_triggered|…"`.
 *
 * Both flow through one fetch - the server's hybrid handler decides which
 * subtree to mutate based on the (group, alert_key) pair.
 *
 * Sibling module to features/alert-recipients.js for the recipient inputs.
 *
 * @since   1.0.0
 */

import { apiPost } from '../core/dom.js';

const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/notifications' );
const data   = dataEl ? JSON.parse( dataEl.textContent ) : {};
const nonce  = data.nonce || '';

if ( nonce ) {
	document.addEventListener( 'change', async ( event ) => {
		const toggle = event.target.closest( '.cb-core-alert-toggle' );
		if ( ! toggle ) return;

		// Resolve group + alert_key from data attributes. Group can come
		// from the toggle itself or be inherited from the enclosing
		// notification-group section. Falls back to 'audit' for legacy
		// severity toggles that have neither.
		const group =
			toggle.dataset.cbCoreAlertGroup
			|| toggle.closest( '[data-cb-core-notification-group]' )?.dataset.cbCoreNotificationGroup
			|| 'audit';
		const alertKey = toggle.dataset.alertKey || toggle.dataset.severity;
		if ( ! alertKey ) return;

		const enabled = toggle.checked;
		toggle.disabled = true;

		try {
			const response = await apiPost( 'cb_core_toggle_alert', nonce, {
				group,
				alert_key: alertKey,
				enabled:   enabled ? 1 : 0,
			} );
			if ( ! response?.success ) {
				toggle.checked = ! enabled;
			}
		} catch {
			toggle.checked = ! enabled;
		} finally {
			toggle.disabled = false;
		}
	} );
}
