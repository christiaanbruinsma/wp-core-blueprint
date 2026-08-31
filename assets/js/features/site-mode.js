/**
 * Core Blueprint - Access Mode editor
 *
 * Stages one of four public-access policies and commits it explicitly through
 * cb_core_set_access_mode. Tile selection never changes the live site until the
 * user submits the form and the server validates the supporting configuration.
 *
 * @since   1.0.0
 */

import { qs, qsa, apiPost } from '../core/dom.js';

const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/site-mode' );
let data = {};
try {
	data = dataEl ? JSON.parse( dataEl.textContent ) : {};
} catch {
	data = {};
}

const nonce = data.nonce || '';
const i18n  = data.i18n || {};
const page  = qs( '.cb-core-access-mode' );

if ( page && nonce ) {
	const form        = qs( '[data-cb-core-access-form]', page );
	const modeInputs  = qsa( '[data-cb-core-access-mode-input]', page );
	const options     = qsa( '[data-cb-core-access-option]', page );
	const configs     = qsa( '[data-cb-core-access-config]', page );
	const notices     = qsa( '[data-cb-core-access-notice]', page );
	const submit      = qs( '[data-cb-core-access-submit]', page );
	const saveStatus  = qs( '[data-cb-core-access-save-status]', page );
	const status      = qs( '[data-cb-core-access-status]', page );
	const statusDot   = qs( '[data-cb-core-access-status-dot]', page );
	const statusLabel = qs( '[data-cb-core-access-status-label]', page );
	const toast       = window.cbCore?.toast;

	const MODE_PUBLIC      = 'public';
	const MODE_COMING_SOON = 'coming_soon';
	const MODE_MAINTENANCE = 'maintenance';
	const MODE_ADMIN_ONLY  = 'admin_only';

	let currentMode = status?.dataset.currentMode || MODE_PUBLIC;

	const selectedMode = () => {
		const checked = modeInputs.find( ( input ) => input.checked );
		return checked?.value || currentMode;
	};

	const buttonLabelFor = ( mode ) => {
		if ( mode === currentMode ) return i18n.saveAccessMode || 'Save Access Mode';
		switch ( mode ) {
			case MODE_COMING_SOON:
				return i18n.activateComingSoon || 'Activate Coming Soon';
			case MODE_MAINTENANCE:
				return i18n.activateMaintenance || 'Activate Maintenance';
			case MODE_ADMIN_ONLY:
				return i18n.activateAdminOnly || 'Activate Admin-Only';
			default:
				return i18n.activatePublic || 'Activate Public Mode';
		}
	};

	const applyStagedMode = ( mode ) => {
		for ( const option of options ) {
			const active = option.dataset.cbCoreAccessMode === mode;
			option.classList.toggle( 'is-active', active );
		}
		for ( const config of configs ) {
			config.hidden = config.dataset.cbCoreAccessConfig !== mode;
		}
		for ( const notice of notices ) {
			notice.hidden = notice.dataset.cbCoreAccessNotice !== mode;
		}
		if ( submit ) submit.textContent = buttonLabelFor( mode );
		if ( saveStatus ) saveStatus.textContent = '';
	};

	const updateEffectiveStatus = ( mode, label = '' ) => {
		currentMode = mode;
		if ( status ) status.dataset.currentMode = mode;
		if ( statusLabel ) {
			statusLabel.textContent = label || ( {
				[ MODE_PUBLIC ]: i18n.accessPublic,
				[ MODE_COMING_SOON ]: i18n.accessComingSoon,
				[ MODE_MAINTENANCE ]: i18n.accessMaintenance,
				[ MODE_ADMIN_ONLY ]: i18n.accessAdminOnly,
			}[ mode ] || mode );
		}
		if ( statusDot ) {
			statusDot.classList.toggle( 'cb-core-status__dot--success', mode === MODE_PUBLIC );
			statusDot.classList.toggle( 'cb-core-status__dot--warning', mode !== MODE_PUBLIC );
		}
		if ( submit ) submit.textContent = buttonLabelFor( selectedMode() );
	};

	const fieldValue = ( name ) => {
		const field = form?.elements?.namedItem( name );
		if ( ! field ) return '';
		if ( typeof RadioNodeList !== 'undefined' && field instanceof RadioNodeList ) return field.value || '';
		return String( field.value || '' );
	};

	for ( const input of modeInputs ) {
		input.addEventListener( 'change', () => {
			if ( input.checked ) applyStagedMode( input.value );
		} );
	}

	form?.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		const mode = selectedMode();
		const payload = {
			mode,
			coming_soon_page_id: fieldValue( 'coming_soon_page_id' ),
			coming_soon_indexable: fieldValue( 'coming_soon_indexable' ) || '0',
			maintenance_page_id: fieldValue( 'maintenance_page_id' ),
			maintenance_until_date: fieldValue( 'maintenance_until_date' ),
			maintenance_until_time: fieldValue( 'maintenance_until_time' ),
		};

		if ( submit ) {
			submit.disabled = true;
			submit.dataset.loading = 'true';
		}
		if ( saveStatus ) saveStatus.textContent = i18n.saving || 'Saving…';

		try {
			const response = await apiPost( 'cb_core_set_access_mode', nonce, payload );
			if ( ! response?.success ) {
				const message = response?.data?.message || i18n.saveFailed || 'Save failed';
				if ( saveStatus ) saveStatus.textContent = message;
				toast?.error( message );
				return;
			}

			updateEffectiveStatus( response.data.mode, response.data.status );
			const message = response.data.message || i18n.saved || 'Saved.';
			if ( saveStatus ) saveStatus.textContent = message;
			toast?.success( message );
		} catch ( error ) {
			const message = error?.message || i18n.saveFailed || 'Save failed';
			if ( saveStatus ) saveStatus.textContent = message;
			toast?.error( message );
		} finally {
			if ( submit ) {
				submit.disabled = false;
				delete submit.dataset.loading;
			}
		}
	} );

	applyStagedMode( selectedMode() );
}

// Dashboard cockpit: Access Mode is a four-state policy, not a binary module.
// These actions reuse the same cb_core_set_access_mode endpoint as the full
// Access Mode editor. Supporting page/SEO/retry configuration is preserved
// server-side when only the mode is supplied here.
if ( nonce ) {
	document.addEventListener( 'click', async ( event ) => {
		const action = event.target.closest( '[data-cb-core-access-mode-action]' );
		if ( ! action ) return;

		event.preventDefault();
		event.stopPropagation();
		const mode = action.dataset.cbCoreAccessModeAction || '';
		if ( ! [ 'public', 'coming_soon', 'maintenance', 'admin_only' ].includes( mode ) ) return;

		action.disabled = true;
		action.setAttribute( 'aria-busy', 'true' );
		try {
			const response = await apiPost( 'cb_core_set_access_mode', nonce, { mode } );
			if ( response?.success ) {
				window.location.reload();
				return;
			}
			window.cbCore?.toast?.error?.( response?.data?.message || i18n.saveFailed || 'Could not update Access Mode.' );
		} catch ( error ) {
			window.cbCore?.toast?.error?.( error?.message || i18n.saveFailed || 'Could not update Access Mode.' );
		} finally {
			action.disabled = false;
			action.removeAttribute( 'aria-busy' );
		}
	} );
}
