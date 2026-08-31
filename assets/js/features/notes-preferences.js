/**
 * Core Blueprint - Notes master-switch handler.
 *
 * Wires the MasterSwitch component on the Preferences > Notes tab to the
 * REST endpoint that flips the subsystem on or off. Optimistic UI: flip
 * visually on click, revert on server rejection. Reload on success so
 * the admin menu re-renders (the Notes top-level menu item is gated on
 * the master state via the conditional registration in
 * src/Notes/Bootstrap.php).
 *
 * Module no-ops on every page that doesn't render the master switch -
 * the tag-name lookup short-circuits.
 *
 * REST contract: POST /notes/enable with { enabled: bool }, X-WP-Nonce
 * header. Same nonce as the rest of the @cb-core/notes feature module.
 *
 * @since   1.0.0
 */

import { qs, qsa } from '../core/dom.js';

const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/notes-preferences' );
const config = dataEl ? JSON.parse( dataEl.textContent ) : {};

const restRoot = config.restRoot || '';
const nonce    = config.nonce    || '';
const i18n     = config.i18n     || {};

const masterSwitch = qs( '[data-cb-core-master-switch="notes"]' );

if ( masterSwitch && restRoot && nonce ) {
	masterSwitch.addEventListener( 'click', async ( event ) => {
		const trigger = event.target.closest( '[data-cb-core-master-switch-toggle], [data-cb-core-master-switch-state]' );
		if ( ! trigger ) return;
		event.preventDefault();

		const toggle      = qs( '[data-cb-core-master-switch-toggle]', masterSwitch );
		const currentlyOn = toggle?.getAttribute( 'aria-pressed' ) === 'true';

		let target;
		if ( trigger.matches( '[data-cb-core-master-switch-state]' ) ) {
			target = trigger.dataset.cbCoreMasterSwitchState === 'on';
			if ( target === currentlyOn ) return; // no-op
		} else {
			target = ! currentlyOn;
		}

		const repaint = ( on ) => {
			if ( toggle ) toggle.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			for ( const opt of qsa( '[data-cb-core-master-switch-state]', masterSwitch ) ) {
				opt.classList.remove( 'is-active' );
			}
			const wanted = qs( `[data-cb-core-master-switch-state="${ on ? 'on' : 'off' }"]`, masterSwitch );
			if ( wanted ) wanted.classList.add( 'is-active' );
		};
		repaint( target );

		try {
			const response = await fetch( `${ restRoot }enable`, {
				method:  'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce':   nonce,
				},
				body: JSON.stringify( { enabled: target } ),
			} );
			const payload = await response.json().catch( () => ( {} ) );

			if ( response.ok && payload?.success ) {
				// Reload - the Notes top-level menu item visibility is
				// rendered server-side so we need a fresh page to see it.
				window.location.reload();
				return;
			}

			repaint( currentlyOn );
			if ( window.cbCore?.toast?.error ) {
				const message = payload?.message
					|| i18n.masterToggleFailed
					|| 'Could not update Notes - try again.';
				window.cbCore.toast.error( message );
			}
		} catch ( error ) {
			repaint( currentlyOn );
			if ( window.cbCore?.toast?.error ) {
				const message = error?.message
					|| i18n.masterToggleFailed
					|| 'Could not update Notes - try again.';
				window.cbCore.toast.error( message );
			}
		}
	} );
}
