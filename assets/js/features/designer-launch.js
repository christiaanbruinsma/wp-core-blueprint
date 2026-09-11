(() => {
	'use strict';

	const config = window.cbCoreDesignerLaunch || {};

	const boot = () => {
		document.querySelectorAll('[data-cb-mail-designer]').forEach((root) => {
			const shell = root.querySelector('[data-cb-design-shell]');
			const fullscreen = shell?.querySelector('[data-cb-design-shell-fullscreen]');
			const context = root.querySelector('.cb-core-mail-designer__context');
			if (!shell || !fullscreen || !context || root.querySelector('[data-cb-design-launch]')) return;

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

			button.addEventListener('click', () => {
				if (fullscreen.getAttribute('aria-pressed') !== 'true') fullscreen.click();
			});

			shell.addEventListener('cb:design-shell:fullscreenchange', (event) => {
				wrapper.hidden = Boolean(event.detail?.fullscreen);
			});

			wrapper.append(button);
			context.insertAdjacentElement('afterend', wrapper);
		});
	};

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true });
	else boot();
})();
