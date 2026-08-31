/**
 * Core Blueprint - Public API: theme & locale setters
 *
 * Wraps the cb_core_set_theme and cb_core_set_locale AJAX actions in tiny
 * helpers that downstream Core Blueprint plugins can call without knowing
 * the action names or nonce keys. The classic IIFE in admin.js previously
 * exposed these on `window.cbCore` using jQuery `$.post`; this module
 * replaces that with native `fetch` via the shared `apiPost` helper from
 * core/dom.js.
 *
 * **Breaking change vs. 1.0.21:** the returned Promise is now a native
 * Promise, not a jQuery `$.Deferred`. Callers that used `.done()` / `.fail()`
 * must use `.then()` / `.catch()` instead. The Appearance and Language
 * pages (CB Base's own callers) migrate in the same phase - see
 * features/appearance.js and features/language.js.
 *
 * The response shape is unchanged: the resolved value is the parsed JSON
 * envelope `{ success: bool, data: { ... } }`. Inspect `success` before
 * using `data`. On HTTP errors, apiPost rejects with an `Error` whose
 * `.message` is the server-provided `data.message` when available.
 *
 * Nonces are delivered via the `script_module_data_@cb-core/public-api`
 * filter (WP 6.7+) - they're read from the per-module data tag rather than
 * from a shared `window.cbCore` global.
 *
 * @since   1.0.0
 */

import { apiPost } from './dom.js';

// ─── Server-side data ────────────────────────────────────────────────────────

const dataEl     = document.getElementById( 'wp-script-module-data-@cb-core/public-api' );
const data       = dataEl ? JSON.parse( dataEl.textContent ) : {};
const nonceTheme  = data.nonceTheme  || '';
const nonceLocale = data.nonceLocale || '';

// ─── Public API ──────────────────────────────────────────────────────────────

/**
 * Write the user's theme preference. value may be '' (clear), 'auto', or
 * any registered theme slug. scope is 'user' (default) or 'site'.
 *
 * @param {string} value
 * @param {string} [scope='user']
 * @returns {Promise<Object>} Parsed JSON envelope from the server.
 */
export const setTheme = ( value, scope = 'user' ) =>
	apiPost( 'cb_core_set_theme', nonceTheme, { scope, value } );

/**
 * Write a locale preference. value may be '', 'auto', or an allowed locale.
 * scope is 'user' (default) or 'site'.
 *
 * @param {string} value
 * @param {string} [scope='user']
 * @returns {Promise<Object>} Parsed JSON envelope from the server.
 */
export const setLocale = ( value, scope = 'user' ) =>
	apiPost( 'cb_core_set_locale', nonceLocale, { scope, value } );

// ─── Public API exposure ─────────────────────────────────────────────────────
//
// Attach to window.cbCore so downstream CB plugins can call these without
// importing across plugin boundaries. apiVersion stays at '1.0' - this is
// helpers, so no version bump is required. Consumers can feature-detect
// with `typeof window.cbCore.setTheme === 'function'`.

window.cbCore = window.cbCore || {};
window.cbCore.setTheme  = setTheme;
window.cbCore.setLocale = setLocale;
