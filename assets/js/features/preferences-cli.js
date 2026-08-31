/**
 * Core Blueprint - Preferences › CLI tab
 *
 * Enhances command copy buttons through the shared Clipboard Foundation.
 * The CLI page supplies the exact command text; Base owns clipboard access,
 * fallback behavior, icon feedback, busy state, and Toast notifications.
 *
 * @since   1.0.0
 */

const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/preferences-cli' );
let data = {};
try {
	data = dataEl ? JSON.parse( dataEl.textContent ) : {};
} catch {
	data = {};
}

const i18n = data.i18n || {};

const textForButton = ( button ) => {
	const example = button.closest( '.cb-core-cli__example' );
	const code = example?.querySelector( 'code' );
	return code ? code.textContent.trim() : '';
};

for ( const button of document.querySelectorAll( '.cb-core-cli__copy' ) ) {
	window.cbCore?.clipboard?.enhance( button, {
		text: () => textForButton( button ),
		label: i18n.copyCommand || 'Copy command',
	} );
}
