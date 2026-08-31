/**
 * Core Blueprint Mail Log - destructive clear confirmation.
 *
 * Presentation and focus management are owned by Modal Foundation.
 * The form keeps its normal POST action and nonce; this module only guards
 * the destructive submit with the shared Core Admin confirmation flow.
 */

const form = document.querySelector( '[data-cb-core-mail-clear-log]' );
const modal = window.cbCore?.modal;

if ( form && modal ) {
	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();

		const confirmed = await modal.show( {
			title: form.dataset.confirmTitle || 'Clear Mail Log',
			body: form.dataset.confirmBody || '',
			confirmLabel: form.dataset.confirmLabel || 'Clear Mail Log',
			confirmVariant: 'danger',
		} );

		if ( confirmed ) {
			form.submit();
		}
	} );
}
