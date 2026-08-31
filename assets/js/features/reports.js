/**
 * Core Blueprint - Reports tabs
 *
 * Two responsibilities:
 *
 *   1. Maintenance Report tab (form generation)
 *      - Period preset dropdown ↔ custom-range row visibility sync
 *      - Submit form → AJAX generate → trigger PDF download
 *
 *   2. Overview tab (recent reports table)
 *      - Per-row Delete: confirm via danger modal, AJAX delete, fade row
 *      - Bulk Delete-all: type-to-confirm modal, AJAX delete-all, replace
 *        table with empty-state placeholder
 *
 * All confirmations route through cbCore.modal.show(); all error
 * feedback routes through cbCore.toast. The form-status text element on
 * the Maintenance Report form keeps its inline status pattern - that's
 * a long-running progress indicator, not a transient error.
 *
 * Native fetch via apiPost. No jQuery.
 *
 * @since   1.0.0
 */

import { qs, apiPost } from '../core/dom.js';

const dataEl = document.getElementById( 'wp-script-module-data-@cb-core/reports' );
const data   = dataEl ? JSON.parse( dataEl.textContent ) : {};
const moduleNonce = data.nonce || '';
const i18n  = data.i18n || {};
const modal = window.cbCore?.modal;
const toast = window.cbCore?.toast;

// ─── Maintenance Report form ────────────────────────────────────────────────

const FORM_SELECTOR    = '#cb-core-generate-maintenance-form';
const PERIOD_SELECTOR  = '#cb-core-mr-period';
const CUSTOM_ROW_CLASS = '.cb-core-mr-custom-row';
const START_INPUT      = '#cb-core-mr-start';
const END_INPUT        = '#cb-core-mr-end';
const SUBMIT_BUTTON    = '#cb-core-mr-generate';
const STATUS_EL        = '#cb-core-mr-status';

const form = qs( FORM_SELECTOR );
if ( form ) {
	const periodSelect = qs( PERIOD_SELECTOR, form );
	const customRow    = qs( CUSTOM_ROW_CLASS, form );
	const startInput   = qs( START_INPUT, form );
	const endInput     = qs( END_INPUT, form );
	const submitBtn    = qs( SUBMIT_BUTTON, form );
	const statusEl     = qs( STATUS_EL, form );

	// ─── Toggle custom-range row visibility ──────────────────────────────
	const syncCustomVisibility = () => {
		if ( ! periodSelect || ! customRow ) return;
		const isCustom = periodSelect.value === 'custom';
		customRow.hidden = ! isCustom;

		// When switching to a preset month, mirror its date range into the
		// custom inputs so submitting later as custom keeps continuity.
		if ( ! isCustom && startInput && endInput ) {
			const opt = periodSelect.selectedOptions[ 0 ];
			if ( opt?.dataset.start ) startInput.value = opt.dataset.start;
			if ( opt?.dataset.end )   endInput.value   = opt.dataset.end;
		}
	};

	if ( periodSelect ) {
		periodSelect.addEventListener( 'change', syncCustomVisibility );
		// Initialise on load so the row state matches the dropdown value
		// already selected by PHP (preserves page-reload state).
		syncCustomVisibility();
	}

	// ─── Submit → AJAX generate → trigger download ──────────────────────
	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		if ( ! submitBtn ) return;

		// Resolve period from current form state. Preset value drives the
		// hidden start/end inputs unless 'custom' is selected, in which
		// case the inputs are user-editable.
		let periodStart = startInput?.value || '';
		let periodEnd   = endInput?.value   || '';

		if ( periodSelect && periodSelect.value !== 'custom' ) {
			const opt   = periodSelect.selectedOptions[ 0 ];
			periodStart = opt?.dataset.start || periodStart;
			periodEnd   = opt?.dataset.end   || periodEnd;
		}

		if ( ! periodStart || ! periodEnd ) {
			setStatus( statusEl, i18n.reportsSelectPeriod || 'Select a period.', 'error' );
			return;
		}

		const nonce = form.dataset.nonce;
		if ( ! nonce ) {
			setStatus( statusEl, i18n.reportsNonceMissing || 'Nonce missing - reload the page.', 'error' );
			return;
		}

		submitBtn.disabled = true;
		setStatus( statusEl, i18n.reportsGenerating || 'Generating…', 'pending' );

		try {
			const response = await apiPost( 'cb_core_generate_maintenance_report', nonce, {
				period_start: periodStart,
				period_end:   periodEnd,
			} );

			if ( ! response?.success ) {
				const message = response?.data?.message
					|| ( i18n.reportsGenerateFailed || 'Generation failed - see audit log for details.' );
				setStatus( statusEl, message, 'error' );
				return;
			}

			// Generate-only flow: the report snapshot is persisted on the
			// server and listed on the Overview tab. We no longer auto-trigger
			// a browser download - that pattern forced a download-and-discard
			// cycle for operators who only wanted to view or share the PDF.
			// Instead, surface success + a CTA back to Overview where every
			// stored report has its own View / Download / Delete row-actions.
			const overviewUrl = form.dataset.overviewUrl || '';
			const successHtml = overviewUrl
				? buildSuccessHtml(
					i18n.reportsGenerated || 'Report generated.',
					i18n.reportsViewOnOverview || 'View on Overview',
					overviewUrl
				)
				: ( i18n.reportsGenerated || 'Report generated.' );

			setStatus( statusEl, successHtml, 'success', { allowHtml: true } );
		} catch ( err ) {
			setStatus( statusEl, i18n.networkError || 'Network error - try again.', 'error' );
		} finally {
			submitBtn.disabled = false;
		}
	} );
}

/**
 * Compose a success message with an inline link to the Overview tab.
 * Returns markup as a string for setStatus's allowHtml path. Both pieces
 * of text come from i18n, the URL from a server-rendered data attribute -
 * no user input is injected, so the inline anchor is safe.
 */
function buildSuccessHtml( message, ctaLabel, overviewUrl ) {
	const url = String( overviewUrl ).replace( /"/g, '&quot;' );
	const msg = String( message ).replace( /</g, '&lt;' );
	const cta = String( ctaLabel ).replace( /</g, '&lt;' );
	return `${ msg } <a href="${ url }" class="cb-core-form-status__link">${ cta } &rarr;</a>`;
}

/**
 * Update the inline form-status text. Three states map to data-attributes
 * that CSS targets for colour: pending / success / error.
 */
function setStatus( el, text, kind = 'pending', opts = {} ) {
	if ( ! el ) return;
	if ( opts.allowHtml ) {
		// Caller is responsible for escaping any user-derived content
		// before passing it in. The maintenance-report success path
		// composes its HTML in buildSuccessHtml() above using only
		// i18n strings and a server-rendered URL.
		el.innerHTML = text;
	} else {
		el.textContent = text;
	}
	el.dataset.kind = kind;
}

// ─── Per-row Delete handler (Recently generated table) ─────────────────────
//
// Listens at the tbody level so newly-rendered rows (after future "Refresh"
// or pagination wiring) inherit the handler automatically. Confirms via a
// danger modal; on success fades the row out in place rather than reloading.

const recentTbody = qs( '[data-cb-core-reports-recent]' );

if ( recentTbody && moduleNonce && modal && toast ) {
	const nonce = recentTbody.dataset.nonce || moduleNonce;

	recentTbody.addEventListener( 'click', async ( event ) => {
		const button = event.target.closest( '.cb-core-report-delete' );
		if ( ! button ) return;

		const reportId = parseInt( button.dataset.reportId || '0', 10 );
		if ( reportId <= 0 ) return;

		const ok = await modal.show( {
			title:        i18n.reportsDeleteOneTitle  || 'Delete this report?',
			body:         i18n.reportsDeleteOneBody   || 'The stored report snapshot will be removed and this cannot be undone.',
			confirmLabel: i18n.reportsDeleteOneConfirm || 'Delete',
			confirmVariant: 'danger',
		} );

		if ( ! ok ) return;

		button.disabled = true;
		try {
			const response = await apiPost( 'cb_core_delete_maintenance_report', nonce, {
				report_id: reportId,
			} );

			if ( response?.success ) {
				const row = button.closest( 'tr' );
				if ( row ) {
					// Soft-fade gives visual confirmation before removal.
					row.style.transition = 'opacity 200ms';
					row.style.opacity    = '0';
					setTimeout( () => row.remove(), 200 );
				}

				// If we just removed the last row, replace the empty tbody
				// with the same "no reports" message the server-side
				// template would have rendered - avoids leaving an empty
				// striped table.
				setTimeout( () => {
					if ( recentTbody.querySelectorAll( 'tr' ).length === 0 ) {
						const table     = recentTbody.closest( 'table' );
						const emptyMsg  = i18n.reportsNoneYet || 'No reports generated yet on this site.';
						const p         = document.createElement( 'p' );
						p.className     = 'description';
						const em        = document.createElement( 'em' );
						em.textContent  = emptyMsg;
						p.appendChild( em );
						table?.replaceWith( p );
					}
				}, 250 );
			} else {
				const message = response?.data?.message || i18n.saveFailedShort || 'Delete failed.';
				toast.error( message );
				button.disabled = false;
			}
		} catch {
			toast.error( i18n.networkError || 'Network error - try again.' );
			button.disabled = false;
		}
	} );
}

// ─── Bulk Delete-all (type-to-confirm modal) ───────────────────────────────
//
// Phrase is hardcoded in the modal call; the same phrase is enforced by
// the server. Bypassing JS by hand-crafting the request hits a 400 unless
// the phrase matches exactly.

const bulkTrigger = qs( '[data-cb-core-delete-all-trigger]' );

if ( bulkTrigger && moduleNonce && modal && toast ) {
	const phrase = 'DELETE ALL REPORTS';
	const nonce  = moduleNonce;

	bulkTrigger.addEventListener( 'click', async () => {
		const ok = await modal.show( {
			title:            i18n.reportsDeleteAllTitle  || 'Delete all reports?',
			body:             i18n.reportsDeleteAllBody   || 'This permanently removes every stored Maintenance Report snapshot on this site. This action cannot be undone.',
			confirmLabel:     i18n.reportsDeleteAllConfirm || 'Delete all reports',
			confirmVariant:     'danger',
			typedConfirm:     phrase,
			typedConfirmHint: i18n.reportsDeleteAllHint || 'Type the phrase to confirm:',
		} );

		if ( ! ok ) return;

		try {
			const response = await apiPost( 'cb_core_delete_all_maintenance_reports', nonce, {
				confirm: phrase,
			} );

			if ( response?.success ) {
				// Replace the entire table with the "no reports" placeholder,
				// matching the server-side template's empty branch.
				const recentTable = qs( '.cb-core-recent-reports' );
				const bulkBlock   = qs( '.cb-core-reports-cleanup-section' );
				const emptyMsg    = i18n.reportsNoneYet || 'No reports generated yet on this site.';
				const p           = document.createElement( 'p' );
				p.className       = 'description';
				const em          = document.createElement( 'em' );
				em.textContent    = emptyMsg;
				p.appendChild( em );
				recentTable?.replaceWith( p );
				bulkBlock?.remove();

				toast.success( i18n.reportsDeleteAllDone || 'All reports deleted.' );
			} else {
				const message = response?.data?.message || i18n.saveFailedShort || 'Delete failed.';
				toast.error( message );
			}
		} catch ( error ) {
			toast.error( error?.message || i18n.networkError || 'Network error - try again.' );
		}
	} );
}
