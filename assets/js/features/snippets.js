/**
 * Core Blueprint Snippets admin glue.
 * Native DOM + WordPress Code Editor only; no framework, no telemetry.
 */
( () => {
	'use strict';

	const data = window.cbCoreSnippetsData || {};
	const locations = data.locations || {};
	const i18n = data.i18n || {};

	const typeSelect = document.getElementById( 'cb-snippet-type' );
	const locationSelect = document.getElementById( 'cb-snippet-location' );
	const shortcodeField = document.querySelector( '.cb-snippets-shortcode-field' );
	const codeTextarea = document.getElementById( 'cb-snippet-code' );
	let editor = null;

	const modeForType = ( type ) => {
		switch ( type ) {
			case 'css': return 'text/css';
			case 'js': return 'text/javascript';
			case 'html': return 'text/html';
			default: return 'text/x-php';
		}
	};

	const updateShortcodeVisibility = () => {
		if ( ! shortcodeField || ! locationSelect ) return;
		shortcodeField.hidden = locationSelect.value !== 'shortcode';
	};

	const rebuildLocations = () => {
		if ( ! typeSelect || ! locationSelect ) return;
		const type = typeSelect.value;
		const options = locations[ type ] || {};
		const previous = locationSelect.value;
		locationSelect.replaceChildren();

		Object.entries( options ).forEach( ( [ value, label ] ) => {
			const option = document.createElement( 'option' );
			option.value = value;
			option.textContent = label;
			locationSelect.appendChild( option );
		} );

		if ( Object.prototype.hasOwnProperty.call( options, previous ) ) {
			locationSelect.value = previous;
		}

		if ( editor?.codemirror ) {
			editor.codemirror.setOption( 'mode', modeForType( type ) );
		}
		updateShortcodeVisibility();
	};

	if ( codeTextarea && window.wp?.codeEditor?.initialize ) {
		const settings = data.editor && typeof data.editor === 'object'
			? JSON.parse( JSON.stringify( data.editor ) )
			: {};
		settings.codemirror = settings.codemirror || {};
		settings.codemirror.mode = modeForType( typeSelect?.value || 'php' );
		settings.codemirror.lineNumbers = true;
		settings.codemirror.indentUnit = 4;
		settings.codemirror.tabSize = 4;
		settings.codemirror.indentWithTabs = true;
		settings.codemirror.lineWrapping = false;
		editor = window.wp.codeEditor.initialize( codeTextarea, settings );
	}

	typeSelect?.addEventListener( 'change', rebuildLocations );
	locationSelect?.addEventListener( 'change', updateShortcodeVisibility );
	updateShortcodeVisibility();


	// Destructive row action uses the suite modal when available. The browser
	// confirm fallback exists only for partial/failed asset loads.
	document.addEventListener( 'submit', async ( event ) => {
		const form = event.target.closest( 'form[data-cb-snippet-delete="1"]' );
		if ( ! form || form.dataset.cbConfirmed === '1' ) return;
		event.preventDefault();

		const title = form.dataset.snippetTitle || '';
		let confirmed = false;
		if ( window.cbCore?.modal?.show ) {
			confirmed = await window.cbCore.modal.show( {
				title: i18n.deleteTitle || 'Delete snippet?',
				body: title ? `${ title }\n\n${ i18n.deleteBody || '' }` : ( i18n.deleteBody || '' ),
				confirmLabel: i18n.delete || 'Delete snippet',
				cancelLabel: i18n.cancel || 'Cancel',
				confirmVariant: 'danger',
			} );
		} else {
			confirmed = window.confirm( i18n.deleteBody || 'Delete this snippet?' );
		}

		if ( confirmed ) {
			form.dataset.cbConfirmed = '1';
			form.submit();
		}
	} );
} )();
