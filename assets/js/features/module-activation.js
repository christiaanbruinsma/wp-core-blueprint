/**
 * Core Blueprint - shared optional-module master switch.
 *
 * Consumes Dashboard Status Menu activation actions and legacy UI\MasterSwitch
 * instances whose name is present in the server-side module allowlist. The
 * authoritative state is always written by Modules\ActivationRegistry and the
 * page reloads after a successful mutation so menus, HUD items and module-specific
 * notices reflect the same state immediately.
 *
 * @since   1.0.0
 */

import { apiPost, qs, qsa } from '../core/dom.js';

const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/module-activation' );
const data   = dataEl ? JSON.parse( dataEl.textContent ) : {};
const nonce  = data.nonce || '';
const modules = Array.isArray( data.modules ) ? new Set( data.modules ) : new Set();
const i18n   = data.i18n || {};

const repaint = ( root, on ) => {
	const toggle = qs( '[data-cb-core-master-switch-toggle]', root );
	if ( toggle ) toggle.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
	for ( const option of qsa( '[data-cb-core-master-switch-state]', root ) ) {
		option.classList.toggle( 'is-active', option.dataset.cbCoreMasterSwitchState === ( on ? 'on' : 'off' ) );
	}
};

if ( nonce && modules.size ) {
	document.addEventListener( 'click', async ( event ) => {

		const menuAction = event.target.closest( '[data-cb-core-module-action]' );
		if ( menuAction ) {
			const module = menuAction.dataset.cbCoreModuleAction || '';
			if ( ! modules.has( module ) ) return;

			event.preventDefault();
			event.stopPropagation();
			const target = menuAction.dataset.cbCoreModuleEnabled === '1';
			menuAction.disabled = true;
			menuAction.setAttribute( 'aria-busy', 'true' );

			try {
				const response = await apiPost( 'cb_core_set_module_enabled', nonce, {
					module,
					enabled: target ? 1 : 0,
				} );
				if ( response?.success ) {
					window.location.reload();
					return;
				}
				window.cbCore?.toast?.error?.( response?.data?.message || i18n.updateFailed || 'Could not update module.' );
			} catch ( error ) {
				window.cbCore?.toast?.error?.( error?.message || i18n.updateFailed || 'Could not update module.' );
			} finally {
				menuAction.disabled = false;
				menuAction.removeAttribute( 'aria-busy' );
			}
			return;
		}
		const trigger = event.target.closest( '[data-cb-core-master-switch-toggle], [data-cb-core-master-switch-state]' );
		if ( ! trigger ) return;

		const root = trigger.closest( '[data-cb-core-master-switch]' );
		if ( ! root ) return;
		const module = root.dataset.cbCoreMasterSwitch || '';
		if ( ! modules.has( module ) ) return;

		event.preventDefault();
		const toggle = qs( '[data-cb-core-master-switch-toggle]', root );
		const currentlyOn = toggle?.getAttribute( 'aria-pressed' ) === 'true';
		const target = trigger.matches( '[data-cb-core-master-switch-state]' )
			? trigger.dataset.cbCoreMasterSwitchState === 'on'
			: ! currentlyOn;
		if ( target === currentlyOn ) return;

		repaint( root, target );
		root.dataset.cbCoreModuleBusy = '1';

		try {
			const response = await apiPost( 'cb_core_set_module_enabled', nonce, {
				module,
				enabled: target ? 1 : 0,
			} );
			if ( response?.success ) {
				window.location.reload();
				return;
			}
			repaint( root, currentlyOn );
			window.cbCore?.toast?.error?.( response?.data?.message || i18n.updateFailed || 'Could not update module.' );
		} catch ( error ) {
			repaint( root, currentlyOn );
			window.cbCore?.toast?.error?.( error?.message || i18n.updateFailed || 'Could not update module.' );
		} finally {
			delete root.dataset.cbCoreModuleBusy;
		}
	} );
}
