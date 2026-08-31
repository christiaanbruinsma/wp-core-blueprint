/**
 * Core Blueprint - User Roles editor.
 *
 * REST-backed, native JavaScript only. The server remains authoritative for
 * every safety decision; disabled UI controls are explanatory affordances,
 * not security boundaries.
 *
 * @since   1.0.0
 */

import modal from '../core/modal.js';
import toast from '../core/toast.js';
import { create as createIcon } from '../core/icon.js';

const dataEl   = document.getElementById( 'wp-script-module-data-@cb-core/user-roles' );
const data     = dataEl ? JSON.parse( dataEl.textContent ) : {};
const restRoot = data.restRoot || '';
const nonce    = data.nonce || '';
const i18n     = data.i18n || {};

const root = document.querySelector( '[data-cb-user-roles]' );

let snapshot = { roles: [], capabilities: {} };
let selectedRole = '';
let capabilitySearch = '';
let capabilityGroup = 'all';
let draftRole = '';
let capabilityDraft = null;
let fieldCounter = 0;

const listEl   = root?.querySelector( '[data-cb-role-list]' );
const detailEl = root?.querySelector( '[data-cb-role-detail]' );
const roleSearchEl = root?.querySelector( '[data-cb-role-search]' );

if ( root ) {
	boot();
}

async function boot() {
	root.querySelector( '[data-cb-role-create]' )?.addEventListener( 'click', openCreateDialog );
	roleSearchEl?.addEventListener( 'input', renderRoleList );
	await reload();
}

async function request( path = '', options = {} ) {
	const response = await fetch( restRoot + path, {
		method: options.method || 'GET',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
		body: options.body ? JSON.stringify( options.body ) : undefined,
	} );

	let payload = null;
	try {
		payload = await response.json();
	} catch {
		payload = null;
	}

	if ( ! response.ok || ! payload?.success ) {
		throw new Error( payload?.message || i18n.requestFailed || 'Request failed.' );
	}
	return payload;
}

async function reload( preferredRole = selectedRole ) {
	setBusy( true );
	try {
		const payload = await request();
		snapshot = payload.data || { roles: [], capabilities: {} };
		capabilityDraft = null;
		draftRole = '';

		if ( preferredRole && snapshot.roles.some( ( role ) => role.slug === preferredRole ) ) {
			selectedRole = preferredRole;
		} else if ( ! selectedRole || ! snapshot.roles.some( ( role ) => role.slug === selectedRole ) ) {
			selectedRole = snapshot.roles[ 0 ]?.slug || '';
		}

		renderRoleList();
		renderRoleDetail();
	} catch ( error ) {
		listEl.innerHTML = '';
		const p = document.createElement( 'p' );
		p.className = 'cb-user-roles-error';
		p.textContent = error.message;
		listEl.appendChild( p );
		toast.error( error.message );
	} finally {
		setBusy( false );
	}
}

function setBusy( busy ) {
	root?.classList.toggle( 'is-busy', busy );
}

function renderRoleList() {
	if ( ! listEl ) return;
	const query = String( roleSearchEl?.value || '' ).trim().toLowerCase();
	const roles = snapshot.roles.filter( ( role ) => {
		return ! query || role.name.toLowerCase().includes( query ) || role.slug.toLowerCase().includes( query );
	} );

	listEl.innerHTML = '';
	if ( roles.length === 0 ) {
		const empty = document.createElement( 'p' );
		empty.className = 'cb-user-roles-empty-list';
		empty.textContent = i18n.noRoles || 'No roles found.';
		listEl.appendChild( empty );
		return;
	}

	for ( const role of roles ) {
		const button = document.createElement( 'button' );
		button.type = 'button';
		button.className = 'cb-user-role-list-item';
		button.classList.toggle( 'is-active', role.slug === selectedRole );
		button.dataset.role = role.slug;
		button.addEventListener( 'click', () => {
			selectedRole = role.slug;
			capabilityDraft = null;
			draftRole = '';
			capabilitySearch = '';
			capabilityGroup = 'all';
			renderRoleList();
			renderRoleDetail();
		} );

		const top = document.createElement( 'span' );
		top.className = 'cb-user-role-list-item__top';
		const name = document.createElement( 'strong' );
		name.textContent = role.name;
		top.appendChild( name );

		if ( role.is_system ) top.appendChild( badge( i18n.system || 'System', 'identity' ) );
		else if ( role.is_builtin ) top.appendChild( badge( i18n.wordpress || 'WordPress', 'identity-muted' ) );
		if ( role.is_default ) top.appendChild( badge( i18n.defaultRole || 'Default', 'identity' ) );

		const meta = document.createElement( 'span' );
		meta.className = 'cb-user-role-list-item__meta';
		meta.textContent = `${ role.slug } · ${ formatUsers( role.user_count ) }`;

		button.append( top, meta );
		listEl.appendChild( button );
	}
}

function renderRoleDetail() {
	if ( ! detailEl ) return;
	const role = snapshot.roles.find( ( item ) => item.slug === selectedRole );
	if ( ! role ) {
		detailEl.innerHTML = `<div class="cb-user-role-empty"><h2>${ escapeHtml( i18n.selectRole || 'Select a role' ) }</h2></div>`;
		return;
	}

	detailEl.innerHTML = '';

	const header = document.createElement( 'div' );
	header.className = 'cb-user-role-detail__header';

	const identity = document.createElement( 'div' );
	identity.className = 'cb-user-role-identity';
	const titleRow = document.createElement( 'div' );
	titleRow.className = 'cb-user-role-identity__title-row';
	const title = document.createElement( 'h2' );
	title.textContent = role.name;
	titleRow.appendChild( title );
	if ( role.is_system ) titleRow.appendChild( badge( i18n.systemRole || 'System role', 'identity' ) );
	if ( role.is_default ) titleRow.appendChild( badge( i18n.defaultRole || 'Default', 'identity' ) );

	const slug = document.createElement( 'code' );
	slug.textContent = role.slug;
	identity.append( titleRow, slug );

	const headerActions = document.createElement( 'div' );
	headerActions.className = 'cb-user-role-detail__actions';
	const duplicate = buttonEl( i18n.duplicate || 'Duplicate', 'button cb-core-button cb-core-button--secondary cb-core-button--compact' );
	duplicate.addEventListener( 'click', () => openDuplicateDialog( role ) );
	headerActions.appendChild( duplicate );

	const del = buttonEl( i18n.deleteRole || 'Delete role', 'button cb-core-button cb-core-button--danger cb-core-button--compact' );
	del.disabled = ! role.can_delete;
	if ( ! role.can_delete ) {
		del.title = role.delete_reasons.join( ', ' );
	}
	del.addEventListener( 'click', () => deleteRole( role ) );
	headerActions.appendChild( del );

	header.append( identity, headerActions );
	detailEl.appendChild( header );

	const meta = document.createElement( 'div' );
	meta.className = 'cb-user-role-meta';
	const usersLink = document.createElement( 'a' );
	usersLink.href = role.users_url;
	usersLink.textContent = formatUsers( role.user_count );
	meta.appendChild( usersLink );
	if ( role.delete_reasons.length ) {
		const protection = document.createElement( 'span' );
		protection.textContent = `${ i18n.protectedBecause || 'Protected' }: ${ role.delete_reasons.join( ' · ' ) }`;
		meta.appendChild( protection );
	}
	detailEl.appendChild( meta );

	if ( role.can_rename_label ) {
		detailEl.appendChild( renderRenamePanel( role ) );
	}

	detailEl.appendChild( renderCapabilityPanel( role ) );
}

function renderRenamePanel( role ) {
	const panel = document.createElement( 'section' );
	panel.className = 'cb-user-role-section';
	const h = document.createElement( 'h3' );
	h.textContent = i18n.roleName || 'Role name';
	const row = document.createElement( 'div' );
	row.className = 'cb-user-role-inline-form';
	const input = document.createElement( 'input' );
	input.type = 'text';
	input.value = role.name;
	input.maxLength = 100;
	const save = buttonEl( i18n.saveName || 'Save name', 'button cb-core-button cb-core-button--primary cb-core-button--compact' );
	save.addEventListener( 'click', async () => {
		const name = input.value.trim();
		if ( ! name || name === role.name ) return;
		await mutate( { action: 'rename', role: role.slug, name }, role.slug );
	} );
	row.append( input, save );
	panel.append( h, row );
	return panel;
}

function renderCapabilityPanel( role ) {
	ensureCapabilityDraft( role );
	const panel = document.createElement( 'section' );
	panel.className = 'cb-user-role-section cb-user-role-capabilities';

	const heading = document.createElement( 'div' );
	heading.className = 'cb-user-role-capabilities__heading';
	const text = document.createElement( 'div' );
	const h = document.createElement( 'h3' );
	h.textContent = i18n.capabilities || 'Capabilities';
	const p = document.createElement( 'p' );
	p.textContent = i18n.capabilityHelp || 'Primitive WordPress permissions assigned to this role.';
	text.append( h, p );

	const save = buttonEl( i18n.saveCapabilities || 'Save capabilities', 'button cb-core-button cb-core-button--primary' );
	save.addEventListener( 'click', () => saveCapabilities( role ) );
	heading.append( text, save );
	panel.appendChild( heading );

	const toolbar = document.createElement( 'div' );
	toolbar.className = 'cb-user-role-capability-toolbar';
	const search = document.createElement( 'input' );
	search.type = 'search';
	search.value = capabilitySearch;
	search.placeholder = i18n.searchCapabilitiesPlaceholder || 'Search by name or capability…';
	search.addEventListener( 'input', () => {
		capabilitySearch = search.value;
		renderRoleDetail();
		const next = detailEl.querySelector( '.cb-user-role-capability-toolbar input[type="search"]' );
		next?.focus();
		if ( next ) next.setSelectionRange( next.value.length, next.value.length );
	} );

	const select = document.createElement( 'select' );
	const groups = [ ...new Set( Object.values( snapshot.capabilities ).map( ( meta ) => meta.group ) ) ].sort();
	select.appendChild( optionEl( 'all', i18n.allSources || 'All sources', capabilityGroup === 'all' ) );
	for ( const group of groups ) {
		select.appendChild( optionEl( group, group, capabilityGroup === group ) );
	}
	select.addEventListener( 'change', () => {
		capabilityGroup = select.value;
		renderRoleDetail();
	} );
	toolbar.append(
		fieldEl( i18n.searchCapabilities || 'Search capabilities', search ),
		fieldEl( i18n.source || 'Source', select )
	);
	panel.appendChild( toolbar );

	const grouped = filteredCapabilities( role );
	const groupsWrap = document.createElement( 'div' );
	groupsWrap.className = 'cb-user-role-capability-groups';

	if ( grouped.size === 0 ) {
		const empty = document.createElement( 'p' );
		empty.className = 'cb-user-role-capability-empty';
		empty.textContent = i18n.noCapabilities || 'No capabilities match the current filters.';
		groupsWrap.appendChild( empty );
	} else {
		for ( const [ group, entries ] of grouped.entries() ) {
			groupsWrap.appendChild( renderCapabilityGroup( group, entries, role ) );
		}
	}
	panel.appendChild( groupsWrap );
	return panel;
}

function filteredCapabilities( role ) {
	const query = capabilitySearch.trim().toLowerCase();
	const result = new Map();
	for ( const [ cap, meta ] of Object.entries( snapshot.capabilities ) ) {
		if ( capabilityGroup !== 'all' && meta.group !== capabilityGroup ) continue;
		if ( query ) {
			const haystack = `${ cap } ${ meta.label } ${ meta.description } ${ meta.source }`.toLowerCase();
			if ( ! haystack.includes( query ) ) continue;
		}
		const group = meta.group || meta.source || 'Other';
		if ( ! result.has( group ) ) result.set( group, [] );
		result.get( group ).push( [ cap, meta, role.capabilities[ cap ] || {} ] );
	}
	return new Map( [ ...result.entries() ].sort( ( a, b ) => a[ 0 ].localeCompare( b[ 0 ] ) ) );
}

function renderCapabilityGroup( group, entries, role ) {
	const section = document.createElement( 'details' );
	section.className = 'cb-core-disclosure cb-core-disclosure--compact cb-user-role-capability-group';
	section.open = true;

	const summary = document.createElement( 'summary' );
	summary.className = 'cb-core-disclosure__summary';
	const icon = createIcon( 'expand', { size: 'compact', className: 'cb-core-disclosure__icon' } );
	const title = document.createElement( 'span' );
	title.className = 'cb-core-disclosure__title';
	title.textContent = group;
	const count = entries.filter( ( [ , , state ] ) => state.granted ).length;
	const meta = document.createElement( 'span' );
	meta.className = 'cb-core-disclosure__meta';
	meta.textContent = `${ count }/${ entries.length }`;
	if ( icon ) summary.appendChild( icon );
	summary.append( title, meta );
	section.appendChild( summary );

	const list = document.createElement( 'div' );
	list.className = 'cb-core-disclosure__body cb-user-role-capability-list';
	for ( const [ cap, meta, state ] of entries ) {
		const row = document.createElement( 'label' );
		row.className = 'cb-user-role-capability';
		row.dataset.capability = cap;

		const checkbox = document.createElement( 'input' );
		checkbox.type = 'checkbox';
		checkbox.checked = capabilityDraft.has( cap ) || !! state.required;
		checkbox.disabled = !! state.required || ! state.actor_can_edit;
		checkbox.dataset.capability = cap;
		checkbox.addEventListener( 'change', () => {
			if ( checkbox.checked ) capabilityDraft.add( cap );
			else capabilityDraft.delete( cap );
		} );

		const body = document.createElement( 'span' );
		body.className = 'cb-user-role-capability__body';
		const top = document.createElement( 'span' );
		top.className = 'cb-user-role-capability__title';
		const label = document.createElement( 'strong' );
		label.textContent = meta.label || cap;
		const code = document.createElement( 'code' );
		code.textContent = cap;
		top.append( label, code );
		if ( state.required ) top.appendChild( badge( i18n.required || 'Required', 'standard' ) );
		if ( state.policy_grant && ! state.granted ) top.appendChild( badge( i18n.policyGrant || 'Granted by policy', 'standard' ) );
		if ( ! state.actor_can_edit && ! state.required ) top.appendChild( badge( i18n.outsideAuthority || 'Outside your authority', 'warning' ) );

		body.appendChild( top );
		if ( meta.description ) {
			const desc = document.createElement( 'span' );
			desc.className = 'cb-user-role-capability__description';
			desc.textContent = meta.description;
			body.appendChild( desc );
		}

		row.append( checkbox, body );
		list.appendChild( row );
	}
	section.appendChild( list );
	return section;
}

async function saveCapabilities( role ) {
	ensureCapabilityDraft( role );
	await mutate( { action: 'save_capabilities', role: role.slug, capabilities: [ ...capabilityDraft ] }, role.slug );
}

function ensureCapabilityDraft( role ) {
	if ( capabilityDraft && draftRole === role.slug ) return;
	draftRole = role.slug;
	capabilityDraft = new Set();
	for ( const [ cap, state ] of Object.entries( role.capabilities || {} ) ) {
		if ( state.granted || state.required ) capabilityDraft.add( cap );
	}
}

async function openCreateDialog() {
	const form = roleFormBody( null );
	const confirmed = await modal.show( {
		title: i18n.createRole || 'Add role',
		body: form.root,
		confirmLabel: i18n.create || 'Create role',
		cancelLabel: i18n.cancel || 'Cancel',
	} );
	if ( ! confirmed ) return;

	const name = form.name.value.trim();
	const slug = form.slug.value.trim();
	if ( ! name || ! slug ) {
		toast.error( i18n.nameSlugRequired || 'Role name and slug are required.' );
		return;
	}
	await mutate( { action: 'create', name, slug, source_role: form.source.value } );
}

async function openDuplicateDialog( role ) {
	const form = roleFormBody( role );
	const confirmed = await modal.show( {
		title: i18n.duplicateRole || 'Duplicate role',
		body: form.root,
		confirmLabel: i18n.duplicate || 'Duplicate',
		cancelLabel: i18n.cancel || 'Cancel',
	} );
	if ( ! confirmed ) return;
	const name = form.name.value.trim();
	const slug = form.slug.value.trim();
	if ( ! name || ! slug ) {
		toast.error( i18n.nameSlugRequired || 'Role name and slug are required.' );
		return;
	}
	await mutate( { action: 'duplicate', role: role.slug, name, slug } );
}

function roleFormBody( sourceRole ) {
	const wrap = document.createElement( 'div' );
	wrap.className = 'cb-user-role-modal-fields';
	const name = document.createElement( 'input' );
	name.type = 'text';
	name.maxLength = 100;
	name.value = sourceRole ? `${ sourceRole.name } Copy` : '';
	const slug = document.createElement( 'input' );
	slug.type = 'text';
	slug.maxLength = 64;
	slug.value = sourceRole ? `${ sourceRole.slug }_copy` : '';
	const source = document.createElement( 'select' );

	if ( sourceRole ) {
		source.appendChild( optionEl( sourceRole.slug, sourceRole.name, true ) );
		source.disabled = true;
	} else {
		source.appendChild( optionEl( '', i18n.startEmpty || 'Start with no capabilities', true ) );
		for ( const role of snapshot.roles ) {
			source.appendChild( optionEl( role.slug, `${ i18n.copyFrom || 'Copy from' } ${ role.name }`, false ) );
		}
	}

	wrap.append(
		fieldEl( i18n.roleName || 'Role name', name ),
		fieldEl( i18n.roleSlug || 'Role slug', slug, i18n.slugHelp || 'Permanent machine name. It cannot be changed later.' ),
		fieldEl( i18n.capabilityTemplate || 'Capability template', source )
	);

	let slugTouched = !! sourceRole;
	slug.addEventListener( 'input', () => { slugTouched = true; } );
	name.addEventListener( 'input', () => {
		if ( ! slugTouched ) slug.value = slugify( name.value );
	} );

	return { root: wrap, name, slug, source };
}

async function deleteRole( role ) {
	if ( ! role.can_delete ) return;
	const confirmed = await modal.show( {
		title: i18n.deleteRole || 'Delete role',
		body: ( i18n.deleteWarning || 'This permanently removes the role definition. Users must be reassigned before a role can be deleted.' ),
		confirmLabel: i18n.deleteRole || 'Delete role',
		cancelLabel: i18n.cancel || 'Cancel',
		confirmVariant: 'danger',
		typedConfirm: role.slug,
		typedConfirmHint: i18n.typeSlug || 'Type the role slug to confirm:',
	} );
	if ( ! confirmed ) return;
	await mutate( { action: 'delete', role: role.slug }, '' );
}

async function mutate( body, preferredRole = '' ) {
	setBusy( true );
	try {
		const payload = await request( '/action', { method: 'POST', body } );
		snapshot = payload.data || snapshot;
		capabilityDraft = null;
		draftRole = '';
		selectedRole = payload.selected || preferredRole || selectedRole;
		if ( ! snapshot.roles.some( ( role ) => role.slug === selectedRole ) ) {
			selectedRole = snapshot.roles[ 0 ]?.slug || '';
		}
		renderRoleList();
		renderRoleDetail();
		toast.success( payload.message || i18n.saved || 'Saved.' );
	} catch ( error ) {
		toast.error( error.message );
	} finally {
		setBusy( false );
	}
}

function fieldEl( labelText, control, help = '' ) {
	const wrap = document.createElement( 'div' );
	wrap.className = 'cb-core-field';
	const label = document.createElement( 'label' );
	label.className = 'cb-core-field__label';
	control.id = control.id || `cb-user-role-field-${ ++fieldCounter }`;
	label.htmlFor = control.id;
	label.textContent = labelText;
	wrap.append( label, control );
	if ( help ) {
		const hint = document.createElement( 'p' );
		hint.className = 'description cb-core-field__hint';
		hint.textContent = help;
		wrap.appendChild( hint );
	}
	return wrap;
}

function badge( text, variant = 'identity-muted' ) {
	const el = document.createElement( 'span' );
	const variants = {
		'identity': 'cb-core-badge-identity',
		'identity-muted': 'cb-core-badge-identity cb-core-badge-identity--muted',
		'standard': 'cb-core-badge-standard',
		'warning': 'cb-core-badge-severity cb-core-badge-severity--warning',
	};
	el.className = `cb-core-badge ${ variants[ variant ] || variants['identity-muted'] }`;
	el.textContent = text;
	return el;
}

function buttonEl( text, className ) {
	const button = document.createElement( 'button' );
	button.type = 'button';
	button.className = className;
	button.textContent = text;
	return button;
}

function optionEl( value, text, selected ) {
	const option = document.createElement( 'option' );
	option.value = value;
	option.textContent = text;
	option.selected = selected;
	return option;
}

function formatUsers( count ) {
	const n = Number( count || 0 );
	const template = n === 1 ? ( i18n.oneUser || '%d user' ) : ( i18n.manyUsers || '%d users' );
	return template.replace( '%d', String( n ) );
}

function slugify( value ) {
	return String( value )
		.toLowerCase()
		.trim()
		.normalize( 'NFKD' )
		.replace( /[\u0300-\u036f]/g, '' )
		.replace( /[^a-z0-9_\-\s]/g, '' )
		.replace( /[\s\-]+/g, '_' )
		.replace( /_+/g, '_' )
		.replace( /^_+|_+$/g, '' );
}

function escapeHtml( value ) {
	const div = document.createElement( 'div' );
	div.textContent = String( value ?? '' );
	return div.innerHTML;
}
