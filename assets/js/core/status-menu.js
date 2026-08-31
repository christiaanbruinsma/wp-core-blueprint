/**
 * Core Blueprint Status Menu Foundation.
 *
 * Owns only popover interaction. Domain actions remain regular links/buttons
 * and are handled by their owning feature modules.
 */

const roots = () => Array.from( document.querySelectorAll( '[data-cb-core-status-menu]' ) );

const triggerFor = ( root ) => root?.querySelector( '[data-cb-core-status-menu-trigger]' );
const panelFor   = ( root ) => root?.querySelector( '[data-cb-core-status-menu-panel]' );
const itemsFor   = ( root ) => Array.from( root?.querySelectorAll( '[role="menuitem"]' ) || [] )
	.filter( ( item ) => ! item.disabled && item.getAttribute( 'aria-disabled' ) !== 'true' );

const setOpen = ( root, open, focusFirst = false ) => {
	const trigger = triggerFor( root );
	const panel = panelFor( root );
	if ( ! trigger || ! panel ) return;

	trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	panel.hidden = ! open;
	root.classList.toggle( 'is-open', open );
	root.closest( '.cb-core-tile' )?.classList.toggle( 'is-menu-open', open );

	if ( open && focusFirst ) {
		itemsFor( root )[ 0 ]?.focus();
	}
};

const closeOthers = ( except = null ) => {
	for ( const root of roots() ) {
		if ( root !== except ) setOpen( root, false );
	}
};

document.addEventListener( 'click', ( event ) => {
	const trigger = event.target.closest( '[data-cb-core-status-menu-trigger]' );
	if ( trigger ) {
		const root = trigger.closest( '[data-cb-core-status-menu]' );
		if ( ! root ) return;
		event.preventDefault();
		event.stopPropagation();
		const open = trigger.getAttribute( 'aria-expanded' ) === 'true';
		closeOthers( root );
		setOpen( root, ! open );
		return;
	}

	const menuItem = event.target.closest( '[data-cb-core-status-menu] [role="menuitem"]' );
	if ( menuItem ) {
		const root = menuItem.closest( '[data-cb-core-status-menu]' );
		window.setTimeout( () => setOpen( root, false ), 0 );
		return;
	}

	if ( ! event.target.closest( '[data-cb-core-status-menu]' ) ) {
		closeOthers();
	}
} );

document.addEventListener( 'keydown', ( event ) => {
	const root = event.target.closest?.( '[data-cb-core-status-menu]' );
	if ( ! root ) {
		if ( event.key === 'Escape' ) closeOthers();
		return;
	}

	const trigger = triggerFor( root );
	const items = itemsFor( root );
	const index = items.indexOf( document.activeElement );

	if ( event.key === 'Escape' ) {
		event.preventDefault();
		setOpen( root, false );
		trigger?.focus();
		return;
	}

	if ( document.activeElement === trigger && ( event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ' ) ) {
		event.preventDefault();
		closeOthers( root );
		setOpen( root, true, true );
		return;
	}

	if ( index < 0 || ! items.length ) return;

	if ( event.key === 'ArrowDown' ) {
		event.preventDefault();
		items[ ( index + 1 ) % items.length ].focus();
	} else if ( event.key === 'ArrowUp' ) {
		event.preventDefault();
		items[ ( index - 1 + items.length ) % items.length ].focus();
	} else if ( event.key === 'Home' ) {
		event.preventDefault();
		items[ 0 ].focus();
	} else if ( event.key === 'End' ) {
		event.preventDefault();
		items[ items.length - 1 ].focus();
	}
} );
