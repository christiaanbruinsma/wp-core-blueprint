const element = (root, selector) => root?.querySelector?.(selector) ?? null;
const elements = (root, selector) => Array.from(root?.querySelectorAll?.(selector) ?? []);
const DEFAULT_GROUP = 'default';

const normalizeGroupId = (value) => String(value || DEFAULT_GROUP).trim() || DEFAULT_GROUP;

/**
 * Shared Core Blueprint Designer shell.
 *
 * Owns only editor chrome/session presentation: tab groups, history controls
 * and shell-level keyboard delegation. Profiles and consumers keep ownership of
 * canvas semantics, palettes, inspectors, persistence and domain behaviour.
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
	const tabs = elements(root, '[data-cb-design-shell-tab]');
	const panels = elements(root, '[data-cb-design-shell-panel]');
	const groups = new Map();

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

	return Object.freeze({
		root,
		activatePanel,
		syncHistory,
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
};
