const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/capability-picker' );
const data = dataEl ? JSON.parse( dataEl.textContent ) : {};
const i18n = data.i18n || {};
const CAPABILITIES = Array.isArray( data.capabilities ) ? data.capabilities : [];

const buildOption = ( option ) => {
	const button = document.createElement( 'button' );
	button.type = 'button';
	button.className = 'button cb-core-picker__option';
	button.dataset.value = option.value;

	const textWrap = document.createElement( 'span' );
	textWrap.className = 'cb-core-picker__option-text';
	const label = document.createElement( 'span' );
	label.className = 'cb-core-picker__option-label';
	label.textContent = option.value;
	const value = document.createElement( 'span' );
	value.className = 'cb-core-picker__option-value';
	value.textContent = option.label || option.value;
	textWrap.append( label, value );
	button.append( textWrap );
	return button;
};

const initPicker = ( root ) => {
	if ( root.dataset.cbCoreCapabilityPickerReady === '1' ) return;
	const input = root.querySelector( '[data-cb-core-capability-picker-input]' );
	const enhanced = root.querySelector( '[data-cb-core-capability-picker-enhanced]' );
	const toggle = root.querySelector( '[data-cb-core-capability-picker-toggle]' );
	const labelEl = root.querySelector( '[data-cb-core-capability-picker-label]' );
	const panel = root.querySelector( '[data-cb-core-capability-picker-panel]' );
	const search = root.querySelector( '[data-cb-core-capability-picker-search]' );
	const results = root.querySelector( '[data-cb-core-capability-picker-results]' );
	if ( ! input || ! enhanced || ! toggle || ! labelEl || ! panel || ! search || ! results ) return;

	root.dataset.cbCoreCapabilityPickerReady = '1';
	root.classList.add( 'is-enhanced' );
	enhanced.hidden = false;

	const setExpanded = ( expanded ) => {
		panel.hidden = ! expanded;
		toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		if ( expanded ) search.focus();
	};

	const renderSelected = () => {
		labelEl.textContent = String( input.value || '' ).trim() || 'manage_options';
	};

	const renderOptions = () => {
		results.textContent = '';
		const term = String( search.value || '' ).trim().toLowerCase();
		const matches = CAPABILITIES.filter( ( option ) => ! term || `${ option.value || '' } ${ option.label || '' } ${ option.keywords || '' }`.toLowerCase().includes( term ) );
		if ( ! matches.length ) {
			const empty = document.createElement( 'div' );
			empty.className = 'cb-core-picker__empty';
			empty.textContent = i18n.noResults || 'No matching capabilities found.';
			results.appendChild( empty );
			return;
		}
		matches.forEach( ( option ) => {
			const button = buildOption( option );
			button.addEventListener( 'click', () => {
				input.value = option.value;
				renderSelected();
				setExpanded( false );
			} );
			results.appendChild( button );
		} );
	};

	toggle.addEventListener( 'click', () => {
		const open = toggle.getAttribute( 'aria-expanded' ) === 'true';
		setExpanded( ! open );
		if ( ! open ) renderOptions();
	} );
	search.addEventListener( 'input', renderOptions );
	input.addEventListener( 'change', renderSelected );
	document.addEventListener( 'click', ( event ) => {
		if ( ! root.contains( event.target ) ) setExpanded( false );
	} );
	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' ) setExpanded( false );
	} );

	renderSelected();
	renderOptions();
};

const init = ( scope = document ) => {
	scope.querySelectorAll( '[data-cb-core-capability-picker]' ).forEach( initPicker );
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => init() );
} else {
	init();
}

window.cbCore = window.cbCore || {};
window.cbCore.capabilityPicker = Object.freeze( { init } );
