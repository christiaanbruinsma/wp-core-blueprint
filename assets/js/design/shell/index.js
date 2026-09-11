const element = (root, selector) => root?.querySelector?.(selector) ?? null;
const elements = (root, selector) => Array.from(root?.querySelectorAll?.(selector) ?? []);
const DEFAULT_GROUP = 'default';
const FULLSCREEN_ROOT_CLASS = 'is-fullscreen';
const FULLSCREEN_DOCUMENT_CLASS = 'cb-core-design-shell-focus-mode';
const FULLSCREEN_EVENT = 'cb:design-shell:fullscreenchange';
const FOCUSABLE_SELECTOR = [
	'a[href]',
	'button:not([disabled])',
	'input:not([disabled])',
	'select:not([disabled])',
	'textarea:not([disabled])',
	'iframe',
	'[tabindex]:not([tabindex="-1"])',
].join(',');

let activeFullscreenExit = null;

const normalizeGroupId = (value) => String(value || DEFAULT_GROUP).trim() || DEFAULT_GROUP;

const focusableElements = (root) => elements(root, FOCUSABLE_SELECTOR).filter((candidate) => {
	if (candidate.disabled === true || candidate.hidden === true) return false;
	if (candidate.getAttribute?.('aria-hidden') === 'true') return false;
	if (candidate.closest?.('[hidden]')) return false;
	return typeof candidate.focus === 'function';
});

const focusTargetIsUsable = (target) => (
	target
	&& typeof target.focus === 'function'
	&& target.isConnected !== false
);

/**
 * Shared Core Blueprint Designer shell.
 *
 * Owns only editor chrome/session presentation: tab groups, history controls,
 * fullscreen/focus-mode UI state and shell-level keyboard delegation. Profiles
 * and consumers keep ownership of canvas semantics, palettes, inspectors,
 * persistence and domain behaviour.
 */
export const createDesignerShell = (root, {
	session = null,
	defaultPanel = null,
	defaultPanels = {},
	onPanelChange = null,
} = {}) => {
	if (!(root instanceof Element)) {
		throw new TypeError('Designer shell requires a root Element.');
	}

	const undo = element(root, '[data-cb-design-shell-undo]');
	const redo = element(root, '[data-cb-design-shell-redo]');
	const fullscreen = element(root, '[data-cb-design-shell-fullscreen]');
	const fullscreenLabel = element(fullscreen, '[data-cb-design-shell-fullscreen-label]');
	const tabs = elements(root, '[data-cb-design-shell-tab]');
	const panels = elements(root, '[data-cb-design-shell-panel]');
	const groups = new Map();
	let fullscreenState = false;
	let focusReturnTarget = null;
	let temporaryRootTabIndex = null;
	let controller = null;

	const groupIdFor = (node) => normalizeGroupId(node?.dataset?.cbDesignShellGroup);
	const group = (groupId = DEFAULT_GROUP) => {
		const id = normalizeGroupId(groupId);
		if (!groups.has(id)) {
			groups.set(id, {
				id,
				tabs: [],
				panels: [],
				activePanel: null,
			});
		}
		return groups.get(id);
	};

	tabs.forEach((tab) => group(groupIdFor(tab)).tabs.push(tab));
	panels.forEach((panel) => group(groupIdFor(panel)).panels.push(panel));

	const syncHistory = () => {
		if (!session?.history) return;
		if (undo instanceof HTMLButtonElement) undo.disabled = !session.history.canUndo;
		if (redo instanceof HTMLButtonElement) redo.disabled = !session.history.canRedo;
	};

	const syncFullscreenControl = () => {
		if (!fullscreen) return;
		fullscreen.setAttribute('aria-pressed', fullscreenState ? 'true' : 'false');
		const label = fullscreenState
			? fullscreen.dataset?.cbDesignShellFullscreenExitLabel
			: fullscreen.dataset?.cbDesignShellFullscreenEnterLabel;
		if (!label) return;
		fullscreen.setAttribute('aria-label', label);
		if (fullscreenLabel) fullscreenLabel.textContent = label;
	};

	const dispatchFullscreenChange = () => {
		root.dispatchEvent(new CustomEvent(FULLSCREEN_EVENT, {
			bubbles: true,
			detail: Object.freeze({ fullscreen: fullscreenState }),
		}));
	};

	const focusInside = () => {
		const active = document.activeElement;
		if (active && root.contains(active)) return;
		const candidates = focusableElements(root);
		if (candidates.length) {
			candidates[0].focus();
			return;
		}
		if (!root.hasAttribute('tabindex')) {
			temporaryRootTabIndex = true;
			root.setAttribute('tabindex', '-1');
		}
		root.focus?.();
	};

	const restoreRootTabIndex = () => {
		if (temporaryRootTabIndex !== true) return;
		root.removeAttribute('tabindex');
		temporaryRootTabIndex = null;
	};

	const exitFullscreenInternal = ({ restoreFocus = true } = {}) => {
		if (!fullscreenState) return false;
		fullscreenState = false;
		if (activeFullscreenExit === exitFullscreenInternal) activeFullscreenExit = null;
		document.removeEventListener('keydown', handleFullscreenKeydown, true);
		root.classList.remove(FULLSCREEN_ROOT_CLASS);
		document.documentElement?.classList?.remove(FULLSCREEN_DOCUMENT_CLASS);
		restoreRootTabIndex();
		syncFullscreenControl();
		dispatchFullscreenChange();

		const returnTarget = focusReturnTarget;
		focusReturnTarget = null;
		if (restoreFocus && focusTargetIsUsable(returnTarget)) returnTarget.focus();
		return true;
	};

	const enterFullscreen = () => {
		if (fullscreenState) return false;
		if (activeFullscreenExit) activeFullscreenExit({ restoreFocus: false });

		focusReturnTarget = focusTargetIsUsable(document.activeElement) ? document.activeElement : null;
		fullscreenState = true;
		activeFullscreenExit = exitFullscreenInternal;
		root.classList.add(FULLSCREEN_ROOT_CLASS);
		document.documentElement?.classList?.add(FULLSCREEN_DOCUMENT_CLASS);
		document.addEventListener('keydown', handleFullscreenKeydown, true);
		syncFullscreenControl();
		focusInside();
		dispatchFullscreenChange();
		return true;
	};

	const exitFullscreen = () => exitFullscreenInternal();
	const toggleFullscreen = () => fullscreenState ? exitFullscreen() : enterFullscreen();
	const isFullscreen = () => fullscreenState;

	function handleFullscreenKeydown(event) {
		if (!fullscreenState || event.defaultPrevented) return;
		if (event.key === 'Escape') {
			event.preventDefault();
			exitFullscreen();
			return;
		}
		if (event.key !== 'Tab') return;

		const candidates = focusableElements(root);
		if (!candidates.length) {
			event.preventDefault();
			focusInside();
			return;
		}
		const first = candidates[0];
		const last = candidates.at(-1);
		const active = document.activeElement;
		if (!root.contains(active)) {
			event.preventDefault();
			(event.shiftKey ? last : first).focus();
		} else if (event.shiftKey && active === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && active === last) {
			event.preventDefault();
			first.focus();
		}
	}

	const activatePanel = (panelId, { focus = false, group: requestedGroup = DEFAULT_GROUP } = {}) => {
		const id = String(panelId || '').trim();
		if (!id) return false;
		const state = group(requestedGroup);
		const target = state.panels.find((panel) => panel.dataset.cbDesignShellPanel === id);
		if (!target) return false;

		state.activePanel = id;
		state.tabs.forEach((tab) => {
			const active = tab.dataset.cbDesignShellTab === id;
			tab.classList.toggle('is-active', active);
			tab.setAttribute('aria-selected', active ? 'true' : 'false');
			tab.tabIndex = active ? 0 : -1;
			if (active && focus) tab.focus();
		});
		state.panels.forEach((panel) => {
			panel.hidden = panel !== target;
		});
		if (typeof onPanelChange === 'function') onPanelChange(id, target, state.id);
		return true;
	};

	const moveTabFocus = (current, direction) => {
		const state = group(groupIdFor(current));
		if (!state.tabs.length) return;
		const index = state.tabs.indexOf(current);
		if (index < 0) return;
		const next = state.tabs[(index + direction + state.tabs.length) % state.tabs.length];
		activatePanel(next.dataset.cbDesignShellTab, { focus: true, group: state.id });
	};

	tabs.forEach((tab) => {
		const state = group(groupIdFor(tab));
		tab.addEventListener('click', () => activatePanel(tab.dataset.cbDesignShellTab, { group: state.id }));
		tab.addEventListener('keydown', (event) => {
			if (event.key === 'ArrowLeft') {
				event.preventDefault();
				moveTabFocus(tab, -1);
			} else if (event.key === 'ArrowRight') {
				event.preventDefault();
				moveTabFocus(tab, 1);
			} else if (event.key === 'Home') {
				event.preventDefault();
				activatePanel(state.tabs[0]?.dataset.cbDesignShellTab, { focus: true, group: state.id });
			} else if (event.key === 'End') {
				event.preventDefault();
				activatePanel(state.tabs.at(-1)?.dataset.cbDesignShellTab, { focus: true, group: state.id });
			}
		});
	});

	undo?.addEventListener('click', () => {
		if (!session?.undo) return;
		session.undo();
		syncHistory();
	});
	redo?.addEventListener('click', () => {
		if (!session?.redo) return;
		session.redo();
		syncHistory();
	});
	fullscreen?.addEventListener('click', toggleFullscreen);

	groups.forEach((state) => {
		const requested = state.id === DEFAULT_GROUP
			? defaultPanel
			: defaultPanels?.[state.id];
		const initialPanel = String(
			requested
			|| state.tabs.find((tab) => tab.classList.contains('is-active'))?.dataset.cbDesignShellTab
			|| state.tabs[0]?.dataset.cbDesignShellTab
			|| ''
		).trim();
		if (initialPanel) activatePanel(initialPanel, { group: state.id });
	});
	syncHistory();
	syncFullscreenControl();

	controller = Object.freeze({
		root,
		activatePanel,
		syncHistory,
		isFullscreen,
		enterFullscreen,
		exitFullscreen,
		toggleFullscreen,
		get activePanel() {
			return group(DEFAULT_GROUP).activePanel;
		},
		activePanelFor(groupId) {
			return group(groupId).activePanel;
		},
		panel(id, groupId = DEFAULT_GROUP) {
			return group(groupId).panels.find((candidate) => candidate.dataset.cbDesignShellPanel === String(id || '')) ?? null;
		},
	});
	return controller;
};
