import { create as createIcon } from './icon.js';

const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/icon-picker' );
const data = dataEl ? JSON.parse( dataEl.textContent ) : {};
const i18n = data.i18n || {};
const DASHICONS = Array.isArray( data.dashicons ) ? data.dashicons : [];
const LUCIDE = Array.isArray( data.lucide ) ? data.lucide : [];

const normalizeFamily = ( value ) => String( value || '' ).startsWith( 'lucide:' ) ? 'lucide' : 'dashicons';
const normalizeStoredValue = ( value ) => {
	const stringValue = String( value || '' ).trim();
	if ( stringValue.startsWith( 'lucide:' ) ) return stringValue;
	if ( stringValue.startsWith( 'dashicons-' ) ) return stringValue;
	if ( stringValue.startsWith( 'dashicons:' ) ) return `dashicons-${ stringValue.slice( 10 ) }`;
	return 'dashicons-admin-generic';
};
const familyLabel = ( family ) => family === 'lucide' ? ( i18n.lucide || 'Lucide' ) : ( i18n.dashicons || 'Dashicons' );

const createDashiconNode = ( value ) => {
	const span = document.createElement( 'span' );
	span.className = `dashicons ${ value }`;
	span.setAttribute( 'aria-hidden', 'true' );
	return span;
};

const renderIconNode = ( container, value ) => {
	container.textContent = '';
	if ( normalizeFamily( value ) === 'lucide' ) {
		const icon = createIcon( String( value ).slice( 7 ), { size: 'default', label: '' } );
		if ( icon ) container.appendChild( icon );
		return;
	}
	container.appendChild( createDashiconNode( value ) );
};

const buildOption = ( option ) => {
	const button = document.createElement( 'button' );
	button.type = 'button';
	button.className = 'button cb-core-picker__option';
	button.dataset.value = option.value;

	const iconWrap = document.createElement( 'span' );
	iconWrap.className = 'cb-core-picker__option-icon';
	renderIconNode( iconWrap, option.value );

	const textWrap = document.createElement( 'span' );
	textWrap.className = 'cb-core-picker__option-text';
	const label = document.createElement( 'span' );
	label.className = 'cb-core-picker__option-label';
	label.textContent = option.label || option.value;
	const value = document.createElement( 'span' );
	value.className = 'cb-core-picker__option-value';
	value.textContent = option.value;
	textWrap.append( label, value );
	button.append( iconWrap, textWrap );
	return button;
};

const initPicker = ( root ) => {
	if ( root.dataset.cbCoreIconPickerReady === '1' ) return;
	const input = root.querySelector( '[data-cb-core-icon-picker-input]' );
	const enhanced = root.querySelector( '[data-cb-core-icon-picker-enhanced]' );
	const toggle = root.querySelector( '[data-cb-core-icon-picker-toggle]' );
	const preview = root.querySelector( '[data-cb-core-icon-picker-preview]' );
	const labelEl = root.querySelector( '[data-cb-core-icon-picker-label]' );
	const metaEl = root.querySelector( '[data-cb-core-icon-picker-meta]' );
	const panel = root.querySelector( '[data-cb-core-icon-picker-panel]' );
	const search = root.querySelector( '[data-cb-core-icon-picker-search]' );
	const familyButtons = Array.from( root.querySelectorAll( '[data-cb-core-icon-picker-family]' ) );
	const results = root.querySelector( '[data-cb-core-icon-picker-results]' );
	if ( ! input || ! enhanced || ! toggle || ! preview || ! labelEl || ! metaEl || ! panel || ! search || ! results ) return;

	root.dataset.cbCoreIconPickerReady = '1';
	root.classList.add( 'is-enhanced' );
	enhanced.hidden = false;

	let family = normalizeFamily( input.value );
	const allOptions = { dashicons: DASHICONS, lucide: LUCIDE };
	const setExpanded = ( expanded ) => {
		panel.hidden = ! expanded;
		toggle.setAttribute( 'aria-expanded', expanded ? 'true' : 'false' );
		if ( expanded ) search.focus();
	};

	const renderSelected = () => {
		const value = normalizeStoredValue( input.value );
		input.value = value;
		const options = allOptions[ normalizeFamily( value ) ] || [];
		const selected = options.find( ( option ) => option.value === value ) || { value, label: value };
		renderIconNode( preview, value );
		labelEl.textContent = selected.label || selected.value;
		metaEl.textContent = `${ familyLabel( normalizeFamily( value ) ) } · ${ selected.value }`;
	};

	const renderOptions = () => {
		results.textContent = '';
		const term = String( search.value || '' ).trim().toLowerCase();
		familyButtons.forEach( ( button ) => {
			button.classList.toggle( 'is-active', button.dataset.cbCoreIconPickerFamily === family );
			button.setAttribute( 'aria-pressed', button.dataset.cbCoreIconPickerFamily === family ? 'true' : 'false' );
		} );
		const matches = ( allOptions[ family ] || [] ).filter( ( option ) => ! term || `${ option.label || '' } ${ option.value || '' }`.toLowerCase().includes( term ) );
		if ( ! matches.length ) {
			const empty = document.createElement( 'div' );
			empty.className = 'cb-core-picker__empty';
			empty.textContent = i18n.noResults || 'No matching icons found.';
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
	familyButtons.forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			family = button.dataset.cbCoreIconPickerFamily || 'dashicons';
			renderOptions();
		} );
	} );
	input.addEventListener( 'change', () => {
		family = normalizeFamily( input.value );
		renderSelected();
		renderOptions();
	} );
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
	scope.querySelectorAll( '[data-cb-core-icon-picker]' ).forEach( initPicker );
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => init() );
} else {
	init();
}

window.cbCore = window.cbCore || {};
window.cbCore.iconPicker = Object.freeze( { init } );
