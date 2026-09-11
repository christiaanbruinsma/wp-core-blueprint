(() => {
	'use strict';

	const config = window.cbCoreDesignerLaunch || {};
	const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';
	const ICONS = Object.freeze({
		mobile: '<rect x="8" y="3" width="8" height="18" rx="2"/><path d="M11 18h2"/>',
		tablet: '<rect x="6" y="3" width="12" height="18" rx="2"/><path d="M11 18h2"/>',
		desktop: '<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
		undo: '<path d="M9 7 4 12l5 5"/><path d="M20 17a8 8 0 0 0-8-8H4"/>',
		redo: '<path d="m15 7 5 5-5 5"/><path d="M4 17a8 8 0 0 1 8-8h8"/>',
		fullscreen: '<path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5"/>',
		save: '<path d="M5 3h12l2 2v16H5z"/><path d="M8 3v6h8V3M8 21v-7h8v7"/>',
	});

	const createIcon = (name) => {
		const wrapper = document.createElement('span');
		wrapper.className = 'cb-core-design-shell__icon';
		wrapper.setAttribute('aria-hidden', 'true');

		const svg = document.createElementNS(SVG_NAMESPACE, 'svg');
		svg.setAttribute('viewBox', '0 0 24 24');
		svg.setAttribute('fill', 'none');
		svg.setAttribute('stroke', 'currentColor');
		svg.setAttribute('stroke-width', '1.8');
		svg.setAttribute('stroke-linecap', 'round');
		svg.setAttribute('stroke-linejoin', 'round');
		svg.setAttribute('focusable', 'false');
		svg.innerHTML = ICONS[name] || '';
		wrapper.append(svg);
		return wrapper;
	};

	const iconize = (button, iconName, label) => {
		if (!button) return;
		const accessibleLabel = String(label || button.getAttribute('aria-label') || button.textContent || '').trim();
		button.classList.add('cb-core-design-shell__icon-button');
		if (accessibleLabel) {
			button.setAttribute('aria-label', accessibleLabel);
			button.setAttribute('title', accessibleLabel);
		}
		button.replaceChildren(createIcon(iconName));
	};

	const composeHeader = (root, shell) => {
		const toolbar = shell.querySelector('.cb-core-design-shell__toolbar');
		if (!toolbar || toolbar.dataset.cbDesignShellHeader === 'true') return;

		const historyGroup = toolbar.querySelector('[data-cb-design-shell-undo]')?.closest('.cb-core-design-shell__toolbar-group');
		const viewportGroup = toolbar.querySelector('[data-cb-mail-viewport]')?.closest('.cb-core-design-shell__toolbar-group');
		const fullscreen = toolbar.querySelector('[data-cb-design-shell-fullscreen]');
		const save = toolbar.querySelector('button[type="submit"]');
		const status = toolbar.querySelector('[data-cb-mail-preview-status]');
		const previewFrame = root.querySelector('[data-cb-mail-preview-frame]');
		if (!historyGroup || !viewportGroup || !fullscreen || !save) return;

		const historyHeading = historyGroup.querySelector('.cb-core-mail-designer__toolbar-label');
		const viewportHeading = viewportGroup.querySelector('.cb-core-mail-designer__toolbar-label');
		const historyLabel = String(historyHeading?.textContent || 'History').trim();
		const viewportLabel = String(viewportHeading?.textContent || 'Canvas').trim();
		historyHeading?.remove();
		viewportHeading?.remove();

		const undo = historyGroup.querySelector('[data-cb-design-shell-undo]');
		const redo = historyGroup.querySelector('[data-cb-design-shell-redo]');
		const undoLabel = String(undo?.textContent || 'Undo').trim();
		const redoLabel = String(redo?.textContent || 'Redo').trim();
		const fullscreenLabel = String(fullscreen.getAttribute('aria-label') || fullscreen.textContent || 'Fullscreen mode').trim();
		const saveLabel = String(save.textContent || 'Save template').trim();

		historyGroup.setAttribute('role', 'group');
		historyGroup.setAttribute('aria-label', historyLabel);
		viewportGroup.setAttribute('role', 'group');
		viewportGroup.setAttribute('aria-label', viewportLabel);
		historyGroup.classList.add('cb-core-design-shell__toolbar-group--history');
		viewportGroup.classList.add('cb-core-design-shell__toolbar-group--viewport');

		iconize(undo, 'undo', undoLabel);
		iconize(redo, 'redo', redoLabel);
		iconize(fullscreen, 'fullscreen', fullscreenLabel);
		iconize(save, 'save', saveLabel);

		const desktop = viewportGroup.querySelector('[data-cb-mail-viewport="desktop"]');
		const mobile = viewportGroup.querySelector('[data-cb-mail-viewport="mobile"]');
		let tablet = viewportGroup.querySelector('[data-cb-mail-viewport="tablet"]');
		if (!tablet) {
			tablet = document.createElement('button');
			tablet.type = 'button';
			tablet.className = 'button cb-core-button';
			tablet.dataset.cbMailViewport = 'tablet';
			tablet.textContent = String(config.tabletLabel || 'Tablet');
		}

		const desktopLabel = String(desktop?.textContent || 'Desktop').trim();
		const mobileLabel = String(mobile?.textContent || 'Mobile').trim();
		const tabletLabel = String(config.tabletLabel || tablet.textContent || 'Tablet').trim();
		iconize(mobile, 'mobile', mobileLabel);
		iconize(tablet, 'tablet', tabletLabel);
		iconize(desktop, 'desktop', desktopLabel);
		viewportGroup.replaceChildren(...[mobile, tablet, desktop].filter(Boolean));

		const viewportButtons = Array.from(viewportGroup.querySelectorAll('[data-cb-mail-viewport]'));
		const setViewportState = (value, activeButton) => {
			previewFrame?.classList.toggle('is-mobile', value === 'mobile');
			previewFrame?.classList.toggle('is-tablet', value === 'tablet');
			viewportButtons.forEach((button) => {
				const active = button === activeButton;
				button.classList.toggle('is-active', active);
				button.setAttribute('aria-pressed', active ? 'true' : 'false');
			});
		};
		viewportButtons.forEach((button) => {
			button.addEventListener('click', () => {
				setViewportState(String(button.dataset.cbMailViewport || 'desktop'), button);
			});
		});
		const activeViewport = viewportButtons.find((button) => button.classList.contains('is-active')) || desktop || viewportButtons.at(-1);
		if (activeViewport) setViewportState(String(activeViewport.dataset.cbMailViewport || 'desktop'), activeViewport);

		const start = document.createElement('div');
		start.className = 'cb-core-design-shell__toolbar-zone cb-core-design-shell__toolbar-zone--start';
		const brand = document.createElement('div');
		brand.className = 'cb-core-design-shell__brand';
		brand.setAttribute('aria-label', 'Core Blueprint');
		const iconUrl = String(config.iconUrl || '').trim();
		if (iconUrl) {
			const markWrap = document.createElement('span');
			markWrap.className = 'cb-core-design-shell__brand-mark';
			const mark = document.createElement('img');
			mark.src = iconUrl;
			mark.alt = '';
			mark.setAttribute('aria-hidden', 'true');
			markWrap.append(mark);
			brand.append(markWrap);
		}
		const wordmark = document.createElement('span');
		wordmark.className = 'cb-core-design-shell__brand-wordmark';
		wordmark.textContent = 'Core Blueprint';
		brand.append(wordmark);
		start.append(brand);

		const center = document.createElement('div');
		center.className = 'cb-core-design-shell__toolbar-zone cb-core-design-shell__toolbar-zone--center';
		center.append(viewportGroup);

		const end = document.createElement('div');
		end.className = 'cb-core-design-shell__toolbar-zone cb-core-design-shell__toolbar-zone--end';
		if (status) {
			status.classList.add('cb-core-design-shell__toolbar-status');
			end.append(status);
		}
		end.append(historyGroup, fullscreen, save);

		toolbar.classList.add('cb-core-design-shell__toolbar--designer');
		toolbar.dataset.cbDesignShellHeader = 'true';
		toolbar.replaceChildren(start, center, end);
	};

	const boot = () => {
		document.querySelectorAll('[data-cb-mail-designer]').forEach((root) => {
			const shell = root.querySelector('[data-cb-design-shell]');
			const fullscreen = shell?.querySelector('[data-cb-design-shell-fullscreen]');
			const context = root.querySelector('.cb-core-mail-designer__context');
			if (!shell || !fullscreen || !context || root.querySelector('[data-cb-design-launch]')) return;

			composeHeader(root, shell);

			const wrapper = document.createElement('div');
			wrapper.className = 'cb-core-design-launch-wrap';
			wrapper.dataset.cbDesignLaunch = '';

			const button = document.createElement('button');
			button.type = 'button';
			button.className = 'cb-core-button cb-core-button--primary cb-core-design-launch';
			button.setAttribute('aria-label', String(config.ariaLabel || config.label || 'Design with Core Blueprint'));

			const iconUrl = String(config.iconUrl || '').trim();
			if (iconUrl) {
				const icon = document.createElement('img');
				icon.className = 'cb-core-design-launch__mark';
				icon.src = iconUrl;
				icon.alt = '';
				icon.setAttribute('aria-hidden', 'true');
				button.append(icon);
			}

			const label = document.createElement('span');
			label.className = 'cb-core-design-launch__label';
			label.textContent = String(config.label || 'Design with Core Blueprint');
			button.append(label);

			const setDesignerMode = (active) => {
				wrapper.hidden = active;
				shell.hidden = !active;
			};

			button.addEventListener('click', () => {
				setDesignerMode(true);
				if (fullscreen.getAttribute('aria-pressed') !== 'true') fullscreen.click();
			});

			shell.addEventListener('cb:design-shell:fullscreenchange', (event) => {
				setDesignerMode(Boolean(event.detail?.fullscreen));
			});

			wrapper.append(button);
			context.insertAdjacentElement('afterend', wrapper);
			setDesignerMode(false);
		});
	};

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
	else boot();
})();
