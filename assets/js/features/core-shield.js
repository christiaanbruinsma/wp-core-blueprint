/**
 * Core Blueprint - Core Shield page
 *
 * Wires up the Core Shield (Safeguards › Core Shield tab) configuration:
 *   - Apply recommended defaults - consequential configuration reset, confirmed
 *   - Per-module master toggle - toggles a module on/off, syncs feature dots
 *   - Per-feature toggle - toggles an individual feature within a module
 *   - Module body collapse/expand - chevron, persisted in localStorage
 *   - "All modules" master toggle - atomic flip-everything via single AJAX
 *   - Header test - runs cb_core_header_test, renders the result table
 *
 * delegation on `document` because most of these elements live inside
 * tabs / module rows that are server-rendered but conceptually dynamic.
 *
 * @since   1.0.0
 */

import { qs, qsa, apiPost } from '../core/dom.js';

const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/core-shield' );
const data   = dataEl ? JSON.parse( dataEl.textContent ) : {};
const nonce  = data.nonce || '';
const i18n   = data.i18n  || {};
const privilegedMode = data.privilegedMode || 'enforce';

if ( nonce ) {
	const modal = window.cbCore?.modal;
	const toast = window.cbCore?.toast;
	const busy  = window.cbCore?.busy;
	const icon  = window.cbCore?.icon;

	// ─── Apply recommended defaults ─────────────────────────────────────────
	document.addEventListener( 'click', async ( event ) => {
		const btn = event.target.closest( '.cb-core-apply-defaults' );
		if ( ! btn ) return;
		event.preventDefault();

		if ( ! modal ) return;

		const ok = await modal.show( {
			title:        i18n.shieldApplyDefaultsTitle  || 'Apply recommended defaults?',
			body:         i18n.shieldApplyDefaultsBody   || 'This overwrites your current module and feature toggle configuration with the recommended defaults for the current site mode.',
			confirmLabel: i18n.shieldApplyDefaultsConfirm || 'Apply defaults',
			confirmVariant: 'primary',
		} );

		if ( ! ok ) return;

		btn.disabled = true;
		try {
			const response = await apiPost( 'cb_core_apply_defaults', nonce );
			if ( response?.success ) {
				window.location.reload();
			} else {
				toast?.error( response?.data?.message || i18n.networkError || 'Request failed' );
				btn.disabled = false;
			}
		} catch {
			toast?.error( i18n.networkError || 'Request failed' );
			btn.disabled = false;
		}
	} );


	// ─── Privileged Access Protection mode ────────────────────────────────
	document.addEventListener( 'change', async ( event ) => {
		const input = event.target.closest( '[data-cb-core-privileged-mode]' );
		if ( ! input ) return;

		const mode = input.value || '';
		if ( mode !== 'enforce' && mode !== 'monitor' ) return;

		const radios = qsa( '[data-cb-core-privileged-mode]' );
		for ( const radio of radios ) radio.disabled = true;

		try {
			const response = await apiPost( 'cb_core_set_privileged_access_mode', nonce, { mode } );
			if ( response?.success ) {
				window.location.reload();
				return;
			}
			for ( const radio of radios ) radio.checked = radio.value === privilegedMode;
			toast?.error( response?.data?.message || i18n.privilegedModeSaveFailed || 'Could not update Privileged Access Protection.' );
		} catch {
			for ( const radio of radios ) radio.checked = radio.value === privilegedMode;
			toast?.error( i18n.networkError || i18n.privilegedModeSaveFailed || 'Could not update Privileged Access Protection.' );
		} finally {
			for ( const radio of radios ) radio.disabled = false;
		}
	} );


	// ─── Privileged Access approvals ───────────────────────────────────────
	document.addEventListener( 'click', async ( event ) => {
		const btn = event.target.closest( '.cb-core-privileged-approve' );
		if ( ! btn ) return;
		event.preventDefault();

		const userId    = Number.parseInt( btn.dataset.userId || '0', 10 );
		const userLogin = btn.dataset.userLogin || '';
		if ( ! userId || ! modal ) return;

		const body = `${ i18n.privilegedApproveBody || 'This immediately restores administrator-level capabilities for the current privilege state.' }${ userLogin ? `\n\nAccount: ${ userLogin }` : '' }`;
		const ok = await modal.show( {
			title:        i18n.privilegedApproveTitle || 'Approve privileged access?',
			body,
			confirmLabel: i18n.privilegedApproveConfirm || 'Approve access',
			confirmVariant: 'primary',
		} );
		if ( ! ok ) return;

		btn.disabled = true;
		try {
			const response = await apiPost( 'cb_core_approve_privileged_user', nonce, { user_id: userId } );
			if ( response?.success ) {
				window.location.reload();
				return;
			}
			toast?.error( response?.data?.message || i18n.privilegedApproveFailed || 'Could not approve privileged access.' );
		} catch {
			toast?.error( i18n.networkError || i18n.privilegedApproveFailed || 'Could not approve privileged access.' );
		} finally {
			btn.disabled = false;
		}
	} );


	// ─── Per-module master toggle ───────────────────────────────────────────
	const repaintFeatureDots = ( moduleEl, moduleEnabled ) => {
		for ( const featureToggle of qsa( '.cb-core-feature-toggle', moduleEl ) ) {
			const feature   = featureToggle.closest( '.cb-core-feature' );
			if ( ! feature ) continue;
			const dot       = qs( ':scope > .cb-core-feature-main > .cb-core-rack-dot', feature );
			const delegated = feature.classList.contains( 'is-delegated' );
			const fEnabled  = featureToggle.checked;

			if ( delegated ) {
				featureToggle.disabled = true;
				if ( dot ) {
					dot.classList.remove( 'is-active', 'is-idle' );
					dot.classList.add( 'is-delegated' );
				}
			} else {
				featureToggle.disabled = ! moduleEnabled;
				if ( dot ) {
					const shouldPulse = moduleEnabled && fEnabled;
					dot.classList.remove( 'is-active', 'is-idle', 'is-delegated' );
					dot.classList.add( shouldPulse ? 'is-active' : 'is-idle' );
				}
			}
		}
	};

	document.addEventListener( 'change', async ( event ) => {
		const toggle = event.target.closest( '.cb-core-module-toggle' );
		if ( ! toggle ) return;

		const moduleEl = toggle.closest( '.cb-core-module' );
		if ( ! moduleEl ) return;
		const slug    = moduleEl.dataset.module;
		const enabled = toggle.checked;
		if ( ! slug ) return;

		toggle.disabled = true;

		try {
			const response = await apiPost( 'cb_core_toggle_module', nonce, {
				module:  slug,
				enabled: enabled ? 1 : 0,
			} );

			if ( response?.success ) {
				moduleEl.classList.toggle( 'is-enabled', enabled );
				const moduleDot = qs( '.cb-core-module-header > .cb-core-rack-dot', moduleEl );
				if ( moduleDot ) {
					moduleDot.classList.remove( 'is-active', 'is-idle' );
					moduleDot.classList.add( enabled ? 'is-active' : 'is-idle' );
				}
				repaintFeatureDots( moduleEl, enabled );
			} else {
				toggle.checked = ! enabled;
			}
		} catch {
			toggle.checked = ! enabled;
		} finally {
			toggle.disabled = false;
			// Sync the global "all modules" master after the per-module change.
			window.setTimeout( syncGlobalToggle, 0 );
		}
	} );

	// ─── Per-feature toggle ─────────────────────────────────────────────────
	document.addEventListener( 'change', async ( event ) => {
		const toggle = event.target.closest( '.cb-core-feature-toggle' );
		if ( ! toggle ) return;

		const moduleEl  = toggle.closest( '.cb-core-module' );
		const featureEl = toggle.closest( '.cb-core-feature' );
		if ( ! moduleEl || ! featureEl ) return;

		const dot      = qs( ':scope > .cb-core-feature-main > .cb-core-rack-dot', featureEl );
		const slug     = moduleEl.dataset.module;
		const feature  = toggle.dataset.feature;
		const enabled  = toggle.checked;
		const moduleOn = moduleEl.classList.contains( 'is-enabled' );
		if ( ! slug || ! feature ) return;

		toggle.disabled = true;
		try {
			const response = await apiPost( 'cb_core_toggle_feature', nonce, {
				module:  slug,
				feature,
				enabled: enabled ? 1 : 0,
			} );
			if ( response?.success ) {
				if ( dot ) {
					const shouldPulse = moduleOn && enabled;
					dot.classList.remove( 'is-active', 'is-idle', 'is-delegated' );
					dot.classList.add( shouldPulse ? 'is-active' : 'is-idle' );
				}
			} else {
				toggle.checked = ! enabled;
			}
		} catch {
			toggle.checked = ! enabled;
		} finally {
			toggle.disabled = false;
		}
	} );

	// ─── Module body collapse/expand (localStorage-persisted) ───────────────
	// Default state is all collapsed; expanded slugs persist as a JSON array
	// under one localStorage key. Quota / private-mode failures are silenced.
	const EXPANDED_KEY = 'cbCoreExpandedModulesV2';

	const readExpanded = () => {
		try {
			const raw = localStorage.getItem( EXPANDED_KEY );
			if ( ! raw ) return [];
			const parsed = JSON.parse( raw );
			return Array.isArray( parsed ) ? parsed : [];
		} catch {
			return [];
		}
	};

	const writeExpanded = ( slugs ) => {
		try {
			localStorage.setItem( EXPANDED_KEY, JSON.stringify( slugs ) );
		} catch { /* ignore */ }
	};

	// Restore saved expand-state on load.
	const expanded = readExpanded();
	if ( expanded.length ) {
		for ( const slug of expanded ) {
			const moduleEl = qs( `.cb-core-module[data-module="${ slug }"]` );
			if ( moduleEl ) {
				moduleEl.classList.add( 'is-expanded' );
				const collapseBtn = qs( '.cb-core-module-collapse', moduleEl );
				if ( collapseBtn ) collapseBtn.setAttribute( 'aria-expanded', 'true' );
			}
		}
	}

	document.addEventListener( 'click', ( event ) => {
		const btn = event.target.closest( '.cb-core-module-collapse' );
		if ( ! btn ) return;
		const moduleEl = btn.closest( '.cb-core-module' );
		if ( ! moduleEl ) return;

		const slug        = moduleEl.dataset.module;
		const nowExpanded = ! moduleEl.classList.contains( 'is-expanded' );
		moduleEl.classList.toggle( 'is-expanded', nowExpanded );
		btn.setAttribute( 'aria-expanded', nowExpanded ? 'true' : 'false' );

		if ( ! slug ) return;
		const current = readExpanded();
		const idx     = current.indexOf( slug );
		if ( nowExpanded && idx === -1 ) current.push( slug );
		else if ( ! nowExpanded && idx !== -1 ) current.splice( idx, 1 );
		writeExpanded( current );
	} );

	document.addEventListener( 'click', ( event ) => {
		const btn = event.target.closest( '.cb-core-feature-details-toggle' );
		if ( ! btn ) return;

		const featureEl = btn.closest( '.cb-core-feature' );
		if ( ! featureEl ) return;
		const body = qs( '.cb-core-feature-body', featureEl );
		if ( ! body ) return;

		const nowExpanded = btn.getAttribute( 'aria-expanded' ) !== 'true';
		btn.setAttribute( 'aria-expanded', nowExpanded ? 'true' : 'false' );
		body.hidden = ! nowExpanded;
		featureEl.classList.toggle( 'is-details-expanded', nowExpanded );
	} );

	// ─── "All modules" master toggle ────────────────────────────────────────
	function syncGlobalToggle() {
		const master = qs( '.cb-core-all-modules-toggle' );
		if ( ! master ) return;

		const toggles = qsa( '.cb-core-module-toggle' );
		const total   = toggles.length;
		if ( total === 0 ) return;

		const on = toggles.filter( ( t ) => t.checked ).length;
		if ( on === total ) {
			master.indeterminate = false;
			master.checked       = true;
		} else if ( on === 0 ) {
			master.indeterminate = false;
			master.checked       = false;
		} else {
			master.indeterminate = true;
		}
	}

	// Initial hydration - server can render `checked` for all-on, but
	// `indeterminate` cannot be expressed in HTML, so we set it here.
	syncGlobalToggle();

	document.addEventListener( 'change', async ( event ) => {
		const master = event.target.closest( '.cb-core-all-modules-toggle' );
		if ( ! master ) return;

		const target  = master.checked;
		const toggles = qsa( '.cb-core-module-toggle' );

		master.disabled      = true;
		master.indeterminate = false;
		for ( const t of toggles ) t.disabled = true;

		try {
			const response = await apiPost( 'cb_core_toggle_all_modules', nonce, {
				enabled: target ? 1 : 0,
			} );

			if ( ! response?.success ) {
				syncGlobalToggle();
				return;
			}

			// Flip every toggle's state + classes to match the new server truth.
			// We mutate `.checked` directly so no `change` event fires - the
			// per-module handler is intentionally bypassed.
			for ( const t of toggles ) {
				const moduleEl = t.closest( '.cb-core-module' );
				if ( ! moduleEl ) continue;
				t.checked = target;
				moduleEl.classList.toggle( 'is-enabled', target );
				const moduleDot = qs( '.cb-core-module-header > .cb-core-rack-dot', moduleEl );
				if ( moduleDot ) {
					moduleDot.classList.remove( 'is-active', 'is-idle' );
					moduleDot.classList.add( target ? 'is-active' : 'is-idle' );
				}
				repaintFeatureDots( moduleEl, target );
			}
		} catch {
			master.checked = ! target;
		} finally {
			master.disabled = false;
			for ( const t of toggles ) t.disabled = false;
			syncGlobalToggle();
		}
	} );

	// ─── Header test ────────────────────────────────────────────────────────
	const escapeHtml = ( str ) =>
		String( str == null ? '' : str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );

	const renderHeaderTestResults = ( target, data ) => {
		if ( data.error ) {
			target.textContent = `${ i18n.headerTestError || 'Header test failed:' } ${ data.error }`;
			toast?.error( target.textContent );
			return;
		}

		const score = data.score || 0;
		const total = data.total || 0;
		const grade = data.grade || 'N/A';

		const summaryTpl = ( i18n.headerScore || '' )
			.replace( '%1$d', score )
			.replace( '%2$d', total )
			.replace( '%3$s', escapeHtml( grade ) );

		const normalizedGrade = grade.replace( /\+/g, 'plus' ).toLowerCase();
		const gradeVariant = [ 'aplus', 'a' ].includes( normalizedGrade )
			? 'success'
			: ( normalizedGrade === 'b' ? 'info' : ( [ 'c', 'd' ].includes( normalizedGrade ) ? 'warning' : 'error' ) );

		let html = '';
		html += '<div class="cb-core-header-test-summary">';
		html += `<span class="cb-core-state-badge cb-core-state-badge--${ gradeVariant } cb-core-state-badge--default">${ escapeHtml( i18n.headerGradeLabel || 'Grade' ) } ${ escapeHtml( grade ) }</span>`;
		html += '<div class="cb-core-grade-meta">';
		html += `<div class="cb-core-grade-summary">${ summaryTpl }</div>`;
		html += `<div class="cb-core-grade-url">URL: <code>${ escapeHtml( data.url ) }</code> · HTTP ${ escapeHtml( data.status ) }</div>`;
		html += '</div></div>';

		html += '<table class="widefat striped cb-core-header-test-table"><thead><tr>';
		html += '<th style="width:32px;">&nbsp;</th>';
		html += `<th style="width:280px;">${ escapeHtml( i18n.headerColumnHeader || 'Header' ) }</th>`;
		html += `<th>${ escapeHtml( i18n.headerColumnValue || 'Value' ) }</th>`;
		html += '</tr></thead><tbody>';

		for ( const key of Object.keys( data.results ) ) {
			const r = data.results[ key ];
			const glyph = icon?.create( r.present ? 'feedback-success' : 'feedback-error', {
				size: 'compact',
				label: r.present ? ( i18n.headerPresent || 'Present' ) : ( i18n.headerMissing || 'Missing' ),
			} );
			const iconHtml = glyph ? glyph.outerHTML : escapeHtml( r.present ? ( i18n.headerPresent || 'Present' ) : ( i18n.headerMissing || 'Missing' ) );
			const value = r.present
				? `<code>${ escapeHtml( r.value ) }</code>`
				: `<span class="cb-core-muted">${ escapeHtml( i18n.headerMissing ) }</span>`;

			html += '<tr>';
			html += `<td class="cb-core-header-test-state">${ iconHtml }</td>`;
			html += `<td><strong>${ escapeHtml( r.label || key ) }</strong><br /><span class="cb-core-muted">${ escapeHtml( r.description || '' ) }</span></td>`;
			html += `<td class="cb-core-header-value">${ value }</td>`;
			html += '</tr>';
		}

		html += '</tbody></table>';
		target.innerHTML = html;
	};

	document.addEventListener( 'click', async ( event ) => {
		const btn = event.target.closest( '.cb-core-run-header-test' );
		if ( ! btn ) return;
		event.preventDefault();

		const results = qs( '.cb-core-header-test-results' );
		if ( ! results ) return;

		busy?.button( btn, true, { label: i18n.testingHeaders || 'Running test…' } );
		busy?.region( results, true );
		results.textContent = i18n.testingHeaders || 'Running test…';

		try {
			const response = await apiPost( 'cb_core_header_test', nonce );
			if ( ! response?.success ) {
				const msg = response?.data?.message || 'Unknown error';
				results.textContent = `${ i18n.headerTestError || 'Header test failed:' } ${ msg }`;
				toast?.error( results.textContent );
				return;
			}
			renderHeaderTestResults( results, response.data );
		} catch ( error ) {
			const msg = error?.message || 'Request failed';
			results.textContent = `${ i18n.headerTestError || 'Header test failed:' } ${ msg }`;
			toast?.error( results.textContent );
		} finally {
			busy?.region( results, false );
			busy?.button( btn, false );
		}
	} );
}
