/**
 * Mail Test Email - server success feedback through Toast Foundation.
 */

const successPayload = document.querySelector( '[data-cb-core-mail-test-success]' );

if ( successPayload ) {
	const message = successPayload.dataset.cbCoreMailTestSuccess || '';
	if ( message && window.cbCore?.toast?.success ) {
		window.cbCore.toast.success( message );
	}
	successPayload.remove();
}
