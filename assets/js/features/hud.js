/**
 * @cb-core/hud - Core Blueprint HUD launcher behaviour.
 *
 * Wires the floating launcher button + slide-in panel:
 *   - Click toggle button → open/close panel
 *   - Drag toggle → snap to nearest of 8 dock anchors
 *   - Ghost toggle in panel → fade button to low opacity
 *   - Esc key → close panel
 *
 * Persistence strategy:
 *   user_meta is the source of truth; localStorage is an immediate-write
 *   cache so position/ghost changes feel instant. Every state change
 *   writes localStorage synchronously, then POSTs to REST asynchronously.
 *   If the REST call fails (offline, capability lost, server-side
 *   validation rejection) the localStorage value still drives the UI
 *   until next page load - at which point the server-rendered position
 *   reasserts truth.
 *
 * Cross-page consistency:
 *   Server renders initial state from user_meta on every page load. JS
 *   reads localStorage on mount and applies it as an immediate override
 *   (covers the rare case where REST persistence on the previous page
 *   didn't complete before navigation). After mount, all changes flow
 *   localStorage → REST in that order.
 *
 * Reads server data from:
 *   <script type="application/json" id="wp-script-module-data-@cb-core/hud">
 *     { brandId, position, ghost, restRoot, restNonce, i18n }
 *
 * @since   1.0.0
 */

const STORAGE_PREFIX = 'cb_core_hud_';
const STORAGE_POSITION = `${STORAGE_PREFIX}position`;
const STORAGE_GHOST    = `${STORAGE_PREFIX}ghost`;
const DRAG_THRESHOLD = 6;

const VALID_POSITIONS = new Set([
	'top-left', 'top-center', 'top-right',
	'middle-left', 'middle-right',
	'bottom-left', 'bottom-center', 'bottom-right',
]);

// ─── Server-data ingest ────────────────────────────────────────────────

const dataEl = document.getElementById('wp-script-module-data-@cb-core/hud');
const serverData = dataEl ? JSON.parse(dataEl.textContent) : {};

const config = {
	brandId:   serverData.brandId   || 'core-blueprint',
	position:  serverData.position  || 'bottom-right',
	ghost:     Boolean(serverData.ghost),
	restRoot:  serverData.restRoot  || '',
	restNonce: serverData.restNonce || '',
	i18n:      serverData.i18n      || {},
};

// ─── REST helpers ──────────────────────────────────────────────────────

/**
 * POST a state change to the HUD REST namespace. Returns the response
 * body on success, throws on HTTP failure. Failures are non-fatal -
 * callers should catch and log without disrupting the local UI state.
 */
async function postHudState(route, body) {
	if (!config.restRoot || !config.restNonce) {
		throw new Error('HUD REST not configured');
	}
	const url = config.restRoot.replace(/\/$/, '') + '/' + route.replace(/^\//, '');
	const response = await fetch(url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce':   config.restNonce,
		},
		body: JSON.stringify(body),
	});
	if (!response.ok) {
		throw new Error(`HTTP ${response.status}`);
	}
	return response.json();
}

// ─── HUD instance ──────────────────────────────────────────────────────

class CoreBlueprintHUD {
	constructor(root) {
		this.root          = root;
		this.toggle        = root.querySelector('[data-cb-hud-toggle]');
		this.panel         = root.querySelector('[data-cb-hud-panel]');
		this.closeBtn      = root.querySelector('[data-cb-hud-close]');
		this.ghostToggle   = root.querySelector('[data-cb-hud-ghost-toggle]');
		this.collapsibleSections = root.querySelectorAll('[data-cb-hud-collapsible]');
		this.sideSwitchBtns = root.querySelectorAll('[data-cb-hud-side-switch]');

		this.pointer = {
			active:   false,
			dragging: false,
			startX: 0, startY: 0,
			offsetX: 0, offsetY: 0,
		};
	}

	init() {
		if (!this.toggle || !this.panel) {
			return;
		}
		this.applyStoredState();
		this.applyStoredSectionState();
		this.bindEvents();
	}

	/**
	 * On mount, prefer localStorage values over the server-rendered
	 * defaults. Covers the case where the previous page's REST call
	 * didn't finish before navigation, so the server-rendered state
	 * lags by one save cycle.
	 */
	applyStoredState() {
		const stored = window.localStorage.getItem(STORAGE_POSITION);
		const position = (stored && VALID_POSITIONS.has(stored)) ? stored : config.position;

		const storedGhost = window.localStorage.getItem(STORAGE_GHOST);
		const ghost = storedGhost === null ? config.ghost : storedGhost === 'true';

		this.applyDockPosition(position);
		this.setGhostMode(ghost, false); // false = don't persist on init
	}

	/**
	 * Restore per-section collapsed/expanded state from localStorage.
	 * Section state is browser-local (not user_meta) because it's a UI
	 * preference rather than a site setting - operators may genuinely
	 * want different sections collapsed on different devices.
	 *
	 * Storage key pattern: cb_core_hud_section_<id>_collapsed
	 *   "true"  → section is collapsed (.is-collapsed class on root)
	 *   "false" → section is expanded
	 *   missing → use server-rendered default (collapsed_default flag)
	 */
	applyStoredSectionState() {
		this.collapsibleSections.forEach((section) => {
			const sectionId = section.dataset.section;
			if (!sectionId) return;

			const key = `cb_core_hud_section_${sectionId}_collapsed`;
			const stored = window.localStorage.getItem(key);
			if (stored === null) {
				return; // keep server-rendered default
			}

			const shouldCollapse = stored === 'true';
			this.applySectionCollapsed(section, shouldCollapse);
		});
	}

	bindEvents() {
		this.toggle.addEventListener('pointerdown', (e) => this.onPointerDown(e));
		this.toggle.addEventListener('click',       (e) => this.onToggleClick(e));

		if (this.closeBtn) {
			this.closeBtn.addEventListener('click', () => this.close());
		}

		if (this.ghostToggle) {
			this.ghostToggle.addEventListener('change', () => {
				this.setGhostMode(this.ghostToggle.checked, true);
			});
		}

		// Header ghost-action button (1.7.0) — replaces the legacy
		// in-body ghost-toggle row. Toggles the same state via the
		// existing setGhostMode flow so persistence + dom mirroring
		// stay consistent. Multiple buttons can target this hook
		// (legacy checkbox + new header button) without conflict.
		const ghostActionBtns = this.root.querySelectorAll('[data-cb-hud-ghost-action]');
		ghostActionBtns.forEach((btn) => {
			btn.addEventListener('click', (event) => {
				event.preventDefault();
				const isActive = btn.classList.contains('is-active');
				this.setGhostMode(!isActive, true);
			});
		});

		// Header theme-toggle button (1.7.0) — single-button theme
		// switcher in the HUD header. Reads the current mode (dark/light)
		// from <html data-cb-mode> and the target slug for each mode
		// from the button's data-attrs (server-rendered via the Themes
		// registry, so partner brands work without hardcoded slugs).
		const themeToggleBtn = this.root.querySelector('[data-cb-hud-theme-toggle]');
		if (themeToggleBtn) {
			themeToggleBtn.addEventListener('click', (event) => {
				event.preventDefault();
				const currentMode = document.documentElement.dataset.cbMode || 'dark';
				const darkSlug    = themeToggleBtn.dataset.cbThemeDark  || '';
				const lightSlug   = themeToggleBtn.dataset.cbThemeLight || '';
				const targetSlug  = (currentMode === 'dark') ? lightSlug : darkSlug;

				if (!targetSlug) {
					console.warn('[CB Core HUD] Theme toggle: no target slug for mode swap');
					return;
				}
				this.selectTheme(targetSlug);
				// Icon swap is driven from applyTheme → refreshThemeToggleIcon,
				// which now reads mode from the registered theme entries.
			});
		}

		// Mode switching is handled by core/mode-switcher.js, which
		// wires every cb-core-mode-switcher (page-level + HUD) to
		// the same click-handler, persistence write, and broadcast
		// event. Nothing to bind here.

		// Section toggles - collapsible sections wire their header
		// button to flip the .is-collapsed class + persist to
		// localStorage. Aria-expanded on the button mirrors visual state.
		this.collapsibleSections.forEach((section) => {
			const toggleBtn = section.querySelector('[data-cb-hud-section-toggle]');
			if (!toggleBtn) return;

			toggleBtn.addEventListener('click', () => {
				const isCollapsed = section.classList.contains('is-collapsed');
				this.applySectionCollapsed(section, !isCollapsed);
				this.persistSectionCollapsed(section.dataset.section, !isCollapsed);
			});
		});

		// Step buttons in footer - four directions (left/right/up/down)
		// each move the toggle one anchor along the requested axis.
		// All four buttons carry data-cb-hud-side-switch + data-direction;
		// CSS shows only the appropriate subset per current anchor.
		this.sideSwitchBtns.forEach((btn) => {
			btn.addEventListener('click', () => {
				const direction = btn.dataset.direction;
				if (['left', 'right', 'up', 'down'].includes(direction)) {
					this.stepPosition(direction);
				}
			});
		});

		// Global keyboard shortcut - Ctrl/Cmd + Shift + H opens/closes
		// HUD from anywhere on the page. Modifier combination chosen
		// to avoid conflict with browser/OS reserved chords (Ctrl+H is
		// browser history, Ctrl+Shift+H is rarely bound). Standard Esc-
		// to-close already handled below.
		document.addEventListener('keydown', (e) => {
			// Open/close shortcut.
			if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'h') {
				e.preventDefault();
				this.togglePanel();
				return;
			}
			// Esc closes when open.
			if (e.key === 'Escape' && this.root.dataset.state === 'open') {
				this.close();
			}
		});
	}

	// ─── Drag-to-dock ────────────────────────────────────────────────

	onPointerDown(event) {
		if (event.button !== 0) return;
		const rect = this.toggle.getBoundingClientRect();
		this.pointer.active   = true;
		this.pointer.dragging = false;
		this.pointer.startX   = event.clientX;
		this.pointer.startY   = event.clientY;
		this.pointer.offsetX  = event.clientX - rect.left;
		this.pointer.offsetY  = event.clientY - rect.top;

		this.toggle.setPointerCapture(event.pointerId);
		this.toggle.addEventListener('pointermove', this._onMove = (e) => this.onPointerMove(e));
		this.toggle.addEventListener('pointerup',   this._onUp   = (e) => this.onPointerUp(e));
	}

	onPointerMove(event) {
		if (!this.pointer.active) return;

		const dx = event.clientX - this.pointer.startX;
		const dy = event.clientY - this.pointer.startY;

		if (!this.pointer.dragging && Math.hypot(dx, dy) > DRAG_THRESHOLD) {
			this.pointer.dragging = true;
		}

		if (this.pointer.dragging) {
			this.toggle.style.left = `${event.clientX - this.pointer.offsetX}px`;
			this.toggle.style.top  = `${event.clientY - this.pointer.offsetY}px`;
			this.toggle.style.right  = 'auto';
			this.toggle.style.bottom = 'auto';
			this.toggle.removeAttribute('data-position'); // free-floating during drag
		}
	}

	onPointerUp(event) {
		this.pointer.active = false;
		this.toggle.removeEventListener('pointermove', this._onMove);
		this.toggle.removeEventListener('pointerup',   this._onUp);

		if (!this.pointer.dragging) {
			return; // click handler will pick it up
		}

		// Snap to nearest dock anchor.
		const rect   = this.toggle.getBoundingClientRect();
		const cx     = rect.left + rect.width  / 2;
		const cy     = rect.top  + rect.height / 2;
		const anchor = this.nearestDockPosition(cx, cy);

		this.applyDockPosition(anchor);
		this.persistPosition(anchor);
	}

	dockPoints() {
		const w = window.innerWidth;
		const h = window.innerHeight;
		return [
			{ id: 'top-left',      x: 0,     y: 0     },
			{ id: 'top-center',    x: w / 2, y: 0     },
			{ id: 'top-right',     x: w,     y: 0     },
			{ id: 'middle-left',   x: 0,     y: h / 2 },
			{ id: 'middle-right',  x: w,     y: h / 2 },
			{ id: 'bottom-left',   x: 0,     y: h     },
			{ id: 'bottom-center', x: w / 2, y: h     },
			{ id: 'bottom-right',  x: w,     y: h     },
		];
	}

	nearestDockPosition(x, y) {
		let nearest  = this.dockPoints()[0];
		let minDist  = Infinity;
		for (const p of this.dockPoints()) {
			const d = (x - p.x) ** 2 + (y - p.y) ** 2;
			if (d < minDist) {
				minDist = d;
				nearest = p;
			}
		}
		return nearest.id;
	}

	applyDockPosition(position) {
		const safe = VALID_POSITIONS.has(position) ? position : 'bottom-right';
		this.toggle.removeAttribute('style');
		this.toggle.dataset.position = safe;

		// Single source of truth - the panel's anchor follows the
		// toggle's dock position. Both axes (horizontal: left/right/
		// center, vertical: top/bottom from middle/bottom alike)
		// resolve from this one attribute via CSS selectors.
		//
		// Side-switch button visibility is also driven by data-anchor
		// via CSS - no JS needed for show/hide. Both buttons (target-
		// left, target-right) are always in the DOM; CSS shows the
		// right set per anchor.
		this.root.dataset.anchor = safe;
	}

	// ─── Ghost mode ──────────────────────────────────────────────────

	setGhostMode(enabled, persist) {
		this.toggle.dataset.ghost = enabled ? 'true' : 'false';
		if (this.ghostToggle) {
			this.ghostToggle.checked = enabled;
		}

		// Sync header ghost-action buttons (1.7.0) — flip is-active class
		// and refresh aria-label/title so screen readers and tooltips
		// reflect the new state without a re-render.
		const ghostActionBtns = this.root.querySelectorAll('[data-cb-hud-ghost-action]');
		ghostActionBtns.forEach((btn) => {
			btn.classList.toggle('is-active', enabled);
			btn.dataset.cbHudGhostState = enabled ? 'on' : 'off';
			const label = enabled
				? 'Disable ghost mode'
				: 'Enable ghost mode (fade the floating button)';
			btn.setAttribute('aria-label', label);
			btn.setAttribute('title', label);
		});

		if (persist) {
			window.localStorage.setItem(STORAGE_GHOST, enabled ? 'true' : 'false');
			this.persistGhost(enabled);
		}
	}

	// ─── Panel open/close ────────────────────────────────────────────

	onToggleClick(event) {
		// Suppress click after drag so dock-snap doesn't immediately open
		// the panel.
		if (this.pointer.dragging) {
			event.preventDefault();
			this.pointer.dragging = false;
			return;
		}
		this.togglePanel();
	}

	togglePanel() {
		if (this.root.dataset.state === 'open') {
			this.close();
		} else {
			this.open();
		}
	}

	open() {
		this.root.dataset.state = 'open';
		this.panel.setAttribute('aria-hidden', 'false');
		this.toggle.setAttribute('aria-expanded', 'true');
	}

	close() {
		this.root.dataset.state = 'closed';
		this.panel.setAttribute('aria-hidden', 'true');
		this.toggle.setAttribute('aria-expanded', 'false');
	}

	// ─── REST persistence ────────────────────────────────────────────

	async persistPosition(position) {
		// Immediate-write to localStorage cache.
		window.localStorage.setItem(STORAGE_POSITION, position);
		// Async server save.
		try {
			await postHudState('position', { position });
		} catch (err) {
			console.warn('[CB Core HUD] Position save failed:', err);
		}
	}

	async persistGhost(ghost) {
		try {
			await postHudState('ghost', { ghost });
		} catch (err) {
			console.warn('[CB Core HUD] Ghost-mode save failed:', err);
		}
	}

	// ─── Theme switching ─────────────────────────────────────────────

	/**
	 * Activate a theme. Same instant-local + async-sync pattern as
	 * brand selection, plus a 300ms transition class so the colour
	 * change cross-fades rather than hard-flips.
	 */
	async selectTheme(themeSlug) {
		const previousTheme = document.documentElement.dataset.cbTheme || '';
		if (themeSlug === previousTheme) {
			return; // no-op
		}

		this.applyTheme(themeSlug);

		try {
			await postHudState('theme', { theme: themeSlug });
		} catch (err) {
			console.warn('[CB Core HUD] Theme save failed, reverting:', err);
			this.applyTheme(previousTheme);
		}
	}

	/**
	 * Apply a theme client-side with a 300ms colour cross-fade.
	 * Sequence:
	 *   1. Add cb-theme-transitioning class (CSS adds transitions to *)
	 *   2. Flip data-cb-theme on html + body (matches Themes.php's
	 *      double-attribute pattern - body[data-cb-theme] selectors
	 *      exist in some legacy components)
	 *   3. Wait 320ms (slightly more than the 300ms transition so we
	 *      remove the class only after the cross-fade completes)
	 *   4. Remove cb-theme-transitioning class
	 *
	 * The class is removed via a setTimeout, NOT via a transitionend
	 * listener - because the wildcard transition triggers on countless
	 * elements and waiting for "the last one" is unreliable. 320ms is
	 * the conservative ceiling.
	 */
	applyTheme(themeSlug) {
		const html = document.documentElement;
		const body = document.body;

		html.classList.add('cb-theme-transitioning');
		html.setAttribute('data-cb-theme', themeSlug);
		if (body) {
			body.setAttribute('data-cb-theme', themeSlug);
		}

		// Update html[data-cb-mode] so any code reading the active mode
		// (theme-toggle icon swap, mode-aware partner code) sees the new
		// state without waiting for a page reload. We derive the mode
		// from the toggle button's slug pair when available; otherwise
		// fall back to the prior attribute (no change).
		const toggleBtn = this.root.querySelector('[data-cb-hud-theme-toggle]');
		if (toggleBtn) {
			const darkSlug  = toggleBtn.dataset.cbThemeDark  || '';
			const lightSlug = toggleBtn.dataset.cbThemeLight || '';
			let nextMode = '';
			if (themeSlug === darkSlug)  nextMode = 'dark';
			if (themeSlug === lightSlug) nextMode = 'light';
			if (nextMode) {
				html.setAttribute('data-cb-mode', nextMode);
				if (body) body.setAttribute('data-cb-mode', nextMode);
			}
		}

		window.setTimeout(() => {
			html.classList.remove('cb-theme-transitioning');
		}, 320);


		// Sync header theme-toggle icon if present.
		this.refreshThemeToggleIcon();
	}

	/**
	 * Swap the inline SVG of the header theme-toggle to match the
	 * currently active mode. When dark is active we show a sun
	 * (clicking goes back to light); when light is active we show a
	 * moon (clicking switches to dark).
	 *
	 * Mode is read from `<html data-cb-mode>`, set by Themes::emit_prepaint_hooks
	 * and updated client-side by applyTheme(). Falls back to 'dark' when
	 * the attribute is missing.
	 */
	refreshThemeToggleIcon() {
		const btn = this.root.querySelector('[data-cb-hud-theme-toggle]');
		if (!btn) return;

		const mode = document.documentElement.dataset.cbMode || 'dark';
		const isDark = (mode === 'dark');

		// Sun (rendered when dark active — click goes to light)
		const sun = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>';
		// Moon (rendered when light active — click goes to dark)
		const moon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';

		btn.innerHTML = isDark ? sun : moon;
	}

	// ─── Side switching (panel left ↔ right) ─────────────────────────

	/**
	 * Move the toggle one step toward the given direction across the
	 * 3-column × 3-row anchor grid. Eight valid anchors total -
	 * `middle-center` is excluded because it would put the toggle in
	 * the dead-center of the screen blocking content.
	 *
	 * Step rules per axis:
	 *
	 *   **Horizontal** (direction: 'left' | 'right')
	 *     - top / bottom row: step through center one column at a time
	 *       (right → center → left, or vice versa)
	 *     - middle row: jump directly across (no middle-center exists)
	 *     - already at edge → no-op
	 *
	 *   **Vertical** (direction: 'up' | 'down')
	 *     - left / right column: step through middle one row at a time
	 *       (top → middle → bottom, or vice versa)
	 *     - center column: jump directly across (no middle-center)
	 *     - already at edge → no-op
	 *
	 * The vertical part is preserved across horizontal steps; the
	 * horizontal part is preserved across vertical steps. Each click
	 * moves the toggle exactly one cell in the requested direction
	 * (or skips middle-center where it doesn't exist).
	 */
	stepPosition(direction) {
		const currentPosition = this.toggle.dataset.position || 'bottom-right';
		const parts = currentPosition.split('-');
		const vertical = parts[0];   // 'top' | 'middle' | 'bottom'
		const horizontal = parts[1]; // 'left' | 'center' | 'right'

		let newVertical = vertical;
		let newHorizontal = horizontal;

		if (direction === 'left' || direction === 'right') {
			// Horizontal axis
			if (vertical === 'middle') {
				// Middle row has no center anchor - jump straight across.
				if (horizontal === direction) return;
				newHorizontal = direction;
			} else {
				// Top and bottom rows step through center one column at a time.
				if (direction === 'left') {
					if (horizontal === 'right')      newHorizontal = 'center';
					else if (horizontal === 'center') newHorizontal = 'left';
					else return;
				} else {
					if (horizontal === 'left')        newHorizontal = 'center';
					else if (horizontal === 'center') newHorizontal = 'right';
					else return;
				}
			}
		} else if (direction === 'up' || direction === 'down') {
			// Vertical axis
			if (horizontal === 'center') {
				// Center column has no middle-center anchor - jump straight across.
				if (direction === 'up') {
					if (vertical === 'bottom') newVertical = 'top';
					else return;
				} else {
					if (vertical === 'top') newVertical = 'bottom';
					else return;
				}
			} else {
				// Left and right columns step through middle one row at a time.
				if (direction === 'up') {
					if (vertical === 'bottom')      newVertical = 'middle';
					else if (vertical === 'middle') newVertical = 'top';
					else return;
				} else {
					if (vertical === 'top')         newVertical = 'middle';
					else if (vertical === 'middle') newVertical = 'bottom';
					else return;
				}
			}
		} else {
			return;
		}

		const newPosition = `${newVertical}-${newHorizontal}`;
		this.applyDockPosition(newPosition);
		this.persistPosition(newPosition);
	}

	// ─── Section collapse/expand ─────────────────────────────────────

	/**
	 * Apply collapsed/expanded state to a section element. Updates the
	 * .is-collapsed class on the section root, the data-collapsed
	 * attribute, and the aria-expanded on the toggle button. Visual
	 * change happens via CSS reading these signals.
	 */
	applySectionCollapsed(section, collapsed) {
		section.classList.toggle('is-collapsed', collapsed);
		section.setAttribute('data-collapsed', collapsed ? 'true' : 'false');
		const toggleBtn = section.querySelector('[data-cb-hud-section-toggle]');
		if (toggleBtn) {
			toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
		}
	}

	/**
	 * Persist a section's collapsed state to localStorage. Browser-
	 * local storage chosen over user_meta because section preferences
	 * are device-contextual (a tablet operator may want different
	 * sections collapsed than on desktop).
	 */
	persistSectionCollapsed(sectionId, collapsed) {
		if (!sectionId) return;
		const key = `cb_core_hud_section_${sectionId}_collapsed`;
		try {
			window.localStorage.setItem(key, collapsed ? 'true' : 'false');
		} catch (err) {
			console.warn('[CB Core HUD] Section state save failed:', err);
		}
	}
}

// ─── Boot ──────────────────────────────────────────────────────────────

const boot = () => {
	document.querySelectorAll('[data-cb-hud]').forEach((root) => {
		const hud = new CoreBlueprintHUD(root);
		hud.init();
	});
};

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', boot);
} else {
	boot();
}
