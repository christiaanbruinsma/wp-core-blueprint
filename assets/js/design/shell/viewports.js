import { decorateDesignerControl } from './icons.js';

export const DESIGNER_VIEWPORT_ORDER = Object.freeze(['mobile', 'tablet', 'desktop']);

const VIEWPORT_EVENT = 'cb:design-shell:viewportchange';
const VIEWPORT_ICONS = Object.freeze({
	mobile: 'smartphone',
	tablet: 'tablet',
	desktop: 'monitor',
});
const viewportControllers = new WeakMap();

const controlsFor = (root) => Array.from(root.querySelectorAll('[data-cb-design-shell-viewport]'));

/**
 * Configure reusable viewport controls for one Designer Shell.
 *
 * Base owns viewport-control presentation, active state, canonical responsive
 * ordering and the change event. Consumers own what a viewport means for their
 * canvas or preview and can react through onChange or the bubbling event.
 */
export const configureDesignerViewports = (root, {
	active = null,
	onChange = null,
} = {}) => {
	if (!(root instanceof Element)) {
		throw new TypeError('Designer viewport configuration requires a shell root Element.');
	}

	const existing = viewportControllers.get(root);
	if (existing) {
		if (active) existing.activate(active, { emit: false });
		return existing;
	}

	const controls = controlsFor(root);
	if (!controls.length) return null;
	const group = controls[0]?.closest?.('[data-cb-design-shell-viewport-group], .cb-core-design-shell__toolbar-group') ?? null;
	if (group) {
		group.setAttribute('role', 'group');
		group.classList.add('cb-core-design-shell__toolbar-group--viewport');
	}

	controls.forEach((button) => {
		const viewport = String(button.dataset.cbDesignShellViewport || '').trim();
		const label = String(button.textContent || viewport).trim();
		const icon = String(button.dataset.cbDesignShellIcon || VIEWPORT_ICONS[viewport] || '').trim();
		if (icon) decorateDesignerControl(button, icon, { iconOnly: true, label });
	});

	const orderIndex = (button) => {
		const viewport = String(button.dataset.cbDesignShellViewport || '').trim();
		const index = DESIGNER_VIEWPORT_ORDER.indexOf(viewport);
		return index < 0 ? DESIGNER_VIEWPORT_ORDER.length : index;
	};
	if (group) group.append(...controls.sort((left, right) => orderIndex(left) - orderIndex(right)));

	let activeViewport = '';
	const activate = (viewport, { emit = true } = {}) => {
		const value = String(viewport || '').trim();
		if (!value) return false;
		const target = controls.find((button) => button.dataset.cbDesignShellViewport === value);
		if (!target) return false;
		activeViewport = value;
		root.dataset.cbDesignShellViewport = value;
		controls.forEach((button) => {
			const selected = button === target;
			button.classList.toggle('is-active', selected);
			button.setAttribute('aria-pressed', selected ? 'true' : 'false');
		});
		if (emit) {
			if (typeof onChange === 'function') onChange(value, target);
			root.dispatchEvent(new CustomEvent(VIEWPORT_EVENT, {
				bubbles: true,
				detail: Object.freeze({ viewport: value }),
			}));
		}
		return true;
	};

	controls.forEach((button) => {
		button.addEventListener('click', () => activate(button.dataset.cbDesignShellViewport));
	});

	const initial = String(active || '').trim()
		|| controls.find((button) => button.getAttribute('aria-pressed') === 'true')?.dataset.cbDesignShellViewport
		|| controls.find((button) => button.classList.contains('is-active'))?.dataset.cbDesignShellViewport
		|| controls[0]?.dataset.cbDesignShellViewport
		|| '';
	if (initial) activate(initial, { emit: false });

	const controller = Object.freeze({
		root,
		controls: Object.freeze([...controls]),
		activate,
		get activeViewport() {
			return activeViewport;
		},
	});
	viewportControllers.set(root, controller);
	return controller;
};
