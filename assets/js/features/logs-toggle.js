/**
 * Core Blueprint - Logs reload-on-mode-change
 *
 * Logs renders different columns server-side per mode (the Event
 * column shows a plain sentence vs raw event_type + technical label;
 * the Context column changes shape). A DOM-flip would mean rendering
 * the whole table client-side, so reload is the simpler choice.
 *
 * Click handling is in core/mode-switcher.js (suite-wide). This
 * module just listens for the broadcast event and takes over with
 * preventDefault + reload when we're on a logs page.
 *
 * @since   1.0.0
 */

document.addEventListener( 'cb-core-mode-changed', ( event ) => {
	if ( ! document.querySelector( '.cb-core-logs-page' ) ) return;

	// Tell the default soft-swap not to run - we're handling this.
	event.preventDefault();

	// The persistence write already succeeded (the event fires after
	// a successful AJAX response). Reload so the server re-renders
	// the table in the new mode.
	window.location.reload();
} );
