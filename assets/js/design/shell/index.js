const element = (root, selector) => root?.querySelector?.(selector) ?? null;
const elements = (root, selector) => Array.from(root?.querySelectorAll?.(selector) ?? []);

/**
 * Shared Core Blueprint Designer shell.
 *
 * Owns only editor chrome/session presentation: side panels, history controls
 * and shell-level keyboard delegation. Profiles and consumers keep ownership of
 * canvas semantics, palettes, inspectors, persistence and domain behaviour.
 */
export const createDesignerShell = (root, {
	session = null,
	defaultPanel = null,
	onPanelChange = null,
} = {}) => {
	if (!(root instanceof Element)) {
		throw new TypeError('Designer shell requires a root Element.');
	}

	const undo = element(root, '[data-cb-design-shell-undo]');
	const redo = element(root, '[data-cb-design-shell-redo]');
	const tabs = elements(root, '[data-cb-design-shell-tab]');
	const panels = elements(root, '[data-cb-design-shell-panel]');
	let activePanel = null;

	const syncHistory = () => {
		if (!session?.history) return;
		if (undo instanceof HTMLButtonElement) undo.disabled = !session.history.canUndo;
		if (redo instanceof HTMLButtonElement) redo.disabled = !session.history.canRedo;
	};

	const activatePanel = (panelId, { focus = false } = {}) => {
		const id = String(panelId || '').trim();
		if (!id) return false;
		const target = panels.find((panel) => panel.dataset.cbDesignShellPanel === id);
		if (!target) return false;

		activePanel = id;
		tabs.forEach((tab) => {
			const active = tab.dataset.cbDesignShellTab === id;
			tab.classList.toggle('is-active', active);
			tab.setAttribute('aria-selected', active ? 'true' : 'false');
			tab.tabIndex = active ? 0 : -1;
			if (active && focus) tab.focus();
		});
		panels.forEach((panel) => {
			panel.hidden = panel !== target;
		});
		if (typeof onPanelChange === 'function') onPanelChange(id, target);
		return true;
	};

	const moveTabFocus = (current, direction) => {
		if (!tabs.length) return;
		const index = tabs.indexOf(current);
		if (index < 0) return;
		const next = tabs[(index + direction + tabs.length) % tabs.length];
		activatePanel(next.dataset.cbDesignShellTab, { focus: true });
	};

	tabs.forEach((tab) => {
		tab.addEventListener('click', () => activatePanel(tab.dataset.cbDesignShellTab));
		tab.addEventListener('keydown', (event) => {
			if (event.key === 'ArrowLeft') {
				event.preventDefault();
				moveTabFocus(tab, -1);
			} else if (event.key === 'ArrowRight') {
				event.preventDefault();
				moveTabFocus(tab, 1);
			} else if (event.key === 'Home') {
				event.preventDefault();
				activatePanel(tabs[0]?.dataset.cbDesignShellTab, { focus: true });
			} else if (event.key === 'End') {
				event.preventDefault();
				activatePanel(tabs.at(-1)?.dataset.cbDesignShellTab, { focus: true });
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

	const initialPanel = String(defaultPanel || tabs.find((tab) => tab.classList.contains('is-active'))?.dataset.cbDesignShellTab || tabs[0]?.dataset.cbDesignShellTab || '').trim();
	if (initialPanel) activatePanel(initialPanel);
	syncHistory();

	return Object.freeze({
		root,
		activatePanel,
		syncHistory,
		get activePanel() {
			return activePanel;
		},
		panel(id) {
			return panels.find((candidate) => candidate.dataset.cbDesignShellPanel === String(id || '')) ?? null;
		},
	});
};
