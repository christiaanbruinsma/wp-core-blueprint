(() => {
	'use strict';

	const config = window.cbCoreDesignerLaunch || {};
	const BOOT_RETRY_DELAY_MS = 50;
	const BOOT_RETRY_LIMIT = 200;

	const sharedShellApi = () => window.cbCore?.designEditor?.shell ?? null;

	const discoverSidebarRoles = (shell) => {
		const roles = {};
		shell.querySelectorAll('[data-cb-design-shell-sidebar-role][data-cb-design-shell-tab]').forEach((tab) => {
			const role = String(tab.dataset.cbDesignShellSidebarRole || '').trim();
			const panelId = String(tab.dataset.cbDesignShellTab || '').trim();
			if (role && panelId) roles[role] = panelId;
		});
		return roles;
	};

	const configureSidebar = (shell, shellApi) => {
		const roles = discoverSidebarRoles(shell);
		const roleNames = Object.keys(roles);
		if (!roleNames.length) return;
		const activeRole = String(config.activeSidebarRole || '').trim()
			|| (roles.inspector ? 'inspector' : roleNames[0]);
		shellApi.configureSidebar(shell, {
			roles,
			labels: config.sidebarLabels || {},
			activeRole,
		});
	};

	const composeHeader = (shell, shellApi) => {
		const toolbar = shell.querySelector('.cb-core-design-shell__toolbar');
		if (!toolbar || toolbar.dataset.cbDesignShellHeader === 'true') return;

		const historyGroup = toolbar.querySelector('[data-cb-design-shell-undo]')?.closest('.cb-core-design-shell__toolbar-group') ?? null;
		const viewportGroup = toolbar.querySelector('[data-cb-design-shell-viewport]')?.closest('.cb-core-design-shell__toolbar-group') ?? null;
		const fullscreen = toolbar.querySelector('[data-cb-design-shell-fullscreen]');
		const save = toolbar.querySelector('[data-cb-design-shell-primary-action]');
		const status = toolbar.querySelector('[data-cb-design-shell-status]');

		if (historyGroup) {
			const heading = historyGroup.querySelector('[data-cb-design-shell-group-label]');
			const historyLabel = String(heading?.textContent || 'History').trim();
			heading?.remove();
			historyGroup.setAttribute('role', 'group');
			historyGroup.setAttribute('aria-label', historyLabel);
			historyGroup.classList.add('cb-core-design-shell__toolbar-group--history');
			const undo = historyGroup.querySelector('[data-cb-design-shell-undo]');
			const redo = historyGroup.querySelector('[data-cb-design-shell-redo]');
			if (undo) {
				const label = String(undo.textContent || 'Undo').trim();
				shellApi.icons.decorate(undo, 'undo-2', { iconOnly: true, label });
			}
			if (redo) {
				const label = String(redo.textContent || 'Redo').trim();
				shellApi.icons.decorate(redo, 'redo-2', { iconOnly: true, label });
			}
		}

		if (viewportGroup) {
			const heading = viewportGroup.querySelector('[data-cb-design-shell-group-label]');
			const viewportLabel = String(heading?.textContent || 'Canvas').trim();
			heading?.remove();
			viewportGroup.setAttribute('aria-label', viewportLabel);
			shellApi.configureViewports(shell);
		}

		if (fullscreen) {
			const label = String(fullscreen.getAttribute('aria-label') || fullscreen.textContent || 'Fullscreen mode').trim();
			shellApi.icons.decorate(fullscreen, 'maximize-2', { iconOnly: true, label });
		}
		if (save) {
			const label = String(save.textContent || 'Save').trim();
			shellApi.icons.decorate(save, 'save', { iconOnly: true, label });
		}

		configureSidebar(shell, shellApi);

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
		if (viewportGroup) center.append(viewportGroup);

		const end = document.createElement('div');
		end.className = 'cb-core-design-shell__toolbar-zone cb-core-design-shell__toolbar-zone--end';
		if (status) {
			status.classList.add('cb-core-design-shell__toolbar-status');
			end.append(status);
		}
		if (historyGroup) end.append(historyGroup);
		if (fullscreen) end.append(fullscreen);
		if (save) end.append(save);

		toolbar.classList.add('cb-core-design-shell__toolbar--designer');
		toolbar.dataset.cbDesignShellHeader = 'true';
		toolbar.replaceChildren(start, center, end);
	};

	const boot = () => {
		const shellApi = sharedShellApi();
		if (
			!shellApi?.icons?.decorate
			|| typeof shellApi.configureSidebar !== 'function'
			|| typeof shellApi.configureViewports !== 'function'
		) return false;

		document.querySelectorAll('[data-cb-design-launch-root]').forEach((root) => {
			const shell = root.querySelector('[data-cb-design-shell]');
			const fullscreen = shell?.querySelector('[data-cb-design-shell-fullscreen]');
			const context = root.querySelector('[data-cb-design-launch-context]');
			if (!shell || !fullscreen || !context || root.querySelector('[data-cb-design-launch]')) return;

			composeHeader(shell, shellApi);

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
				root.classList.toggle('is-designer-mode-active', active);
				wrapper.hidden = active;
				shell.hidden = !active;
			};

			button.addEventListener('click', () => {
				setDesignerMode(true);
				if (fullscreen.getAttribute('aria-pressed') !== 'true') fullscreen.click();
			});

			shell.addEventListener('cb:design-shell:fullscreenchange', (event) => {
				const active = Boolean(event.detail?.fullscreen);
				const labelText = String(fullscreen.getAttribute('aria-label') || 'Fullscreen mode').trim();
				shellApi.icons.decorate(fullscreen, active ? 'minimize-2' : 'maximize-2', {
					iconOnly: true,
					label: labelText,
				});
				setDesignerMode(active);
			});

			wrapper.append(button);
			context.insertAdjacentElement('afterend', wrapper);
			setDesignerMode(false);
		});
		return true;
	};

	const start = () => {
		let attempts = 0;
		let retryTimer = 0;
		let settled = false;

		const stop = () => {
			if (settled) return;
			settled = true;
			if (retryTimer) {
				window.clearTimeout(retryTimer);
				retryTimer = 0;
			}
			window.removeEventListener('cb:design-editor:ready', attemptBoot);
		};

		const attemptBoot = () => {
			if (settled) return;
			if (boot()) {
				stop();
				return;
			}

			attempts += 1;
			if (attempts >= BOOT_RETRY_LIMIT) {
				stop();
				return;
			}

			if (!retryTimer) {
				retryTimer = window.setTimeout(() => {
					retryTimer = 0;
					attemptBoot();
				}, BOOT_RETRY_DELAY_MS);
			}
		};

		window.addEventListener('cb:design-editor:ready', attemptBoot);
		attemptBoot();
	};

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
	else start();
})();
