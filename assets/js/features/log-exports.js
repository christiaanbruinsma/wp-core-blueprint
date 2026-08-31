/**
 * Core Blueprint - Log exports
 *
 * Single dispatcher for all three log-export buttons (Audit / System /
 * Maintenance). The button's CSS class maps to a config entry - AJAX
 * action name + which URL filter params should be forwarded. The export
 * format is read from the `<select class="cb-core-export-format">`
 * dropdown adjacent to the button.
 *
 * The export itself is a GET-driven download, not a JSON POST: we build
 * a URL with the action + nonce + format + filter params and navigate
 * to it. WordPress emits a Content-Disposition response and the browser
 * triggers a file download. This is why the module doesn't use
 * `apiPost` - there's no JSON envelope to inspect.
 *
 * Adding a new format (e.g. PDF via CB Report) requires zero JS changes -
 * the format dropdown carries the value, the server handles the rest.
 *
 * @since   1.0.0
 */


import { qs } from '../core/dom.js';
const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/log-exports' );
const data   = dataEl ? JSON.parse( dataEl.textContent ) : {};
const nonce  = data.nonce   || '';
const ajaxUrl = data.ajaxUrl || '';

if ( nonce ) {
	const EXPORT_MAP = {
		'cb-core-export-audit': {
			action:  'cb_core_export_audit',
			forward: [ 'event', 'severity', 'period' ],
		},
		'cb-core-export-system-log': {
			action:  'cb_core_export_system_log',
			forward: [ 'event', 'severity', 'period' ],
		},
		'cb-core-export-maintenance-report': {
			action:  'cb_core_export_maintenance_report',
			forward: [ 'actor', 'category', 'source', 'period' ],
		},
	};

	document.addEventListener( 'click', ( event ) => {
		const btn = event.target.closest( '.cb-core-log-filters__export' );
		if ( ! btn ) return;
		event.preventDefault();

		// Match the button against the config map by CSS class.
		let config = null;
		for ( const cls of Object.keys( EXPORT_MAP ) ) {
			if ( btn.classList.contains( cls ) ) {
				config = EXPORT_MAP[ cls ];
				break;
			}
		}
		if ( ! config ) return;

		const params = new URLSearchParams( window.location.search );
		const args = {
			action:   config.action,
			_wpnonce: nonce,
			nonce:    nonce,
		};

		// Format from the adjacent dropdown; default 'csv' for legacy markup.
		const group  = btn.closest( '.cb-core-export-group' );
		const format = qs( '.cb-core-export-format', group );
		args.format  = format?.value || 'csv';

		// Forward the log-type's relevant URL params as-is.
		for ( const key of config.forward ) {
			const value = params.get( key );
			if ( value ) args[ key ] = value;
		}

		const query = new URLSearchParams( args ).toString();
		window.location.href = `${ ajaxUrl }?${ query }`;
	} );
}
