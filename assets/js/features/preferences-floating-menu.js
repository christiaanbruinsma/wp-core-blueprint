/**
 * Preferences → Floating Menu editor.
 *
 * Site-wide ordering/visibility is serialized into one validated payload.
 * Registry/runtime capability checks remain server-owned.
 */

const dataEl = document.getElementById('wp-script-module-data-@cb-core/preferences-floating-menu');
const data = dataEl ? JSON.parse(dataEl.textContent) : {};
const i18n = data.i18n || {};

const root = document.querySelector('[data-cb-hud-menu-editor]');

if (root && !root.dataset.cbHudMenuReady) {
	root.dataset.cbHudMenuReady = '1';

	const form = root.querySelector('[data-cb-hud-menu-form]');
	const payload = root.querySelector('[data-cb-hud-menu-payload]');
	const sectionList = root.querySelector('[data-cb-hud-section-list]');
	const addButton = root.querySelector('[data-cb-hud-add-custom]');
	const customLabel = root.querySelector('[data-cb-hud-custom-label]');
	const customUrl = root.querySelector('[data-cb-hud-custom-url]');
	const customSection = root.querySelector('[data-cb-hud-custom-section]');
	const template = root.querySelector('[data-cb-hud-custom-item-template]');
	const validation = root.querySelector('[data-cb-hud-validation]');

	let drag = null;

	const directChildren = (container, selector) => Array.from(container?.children || []).filter((node) => node.matches(selector));

	const updateEmptyState = (list) => {
		if (!list) return;
		const empty = list.querySelector('[data-cb-hud-empty]');
		if (!empty) return;
		empty.classList.toggle('is-hidden', directChildren(list, '[data-cb-hud-item]').length > 0);
	};

	const updateMoveButtons = () => {
		for (const container of root.querySelectorAll('[data-cb-hud-section-list], [data-cb-hud-item-list]')) {
			const selector = container.matches('[data-cb-hud-section-list]') ? '[data-cb-hud-section]' : '[data-cb-hud-item]';
			const nodes = directChildren(container, selector);
			nodes.forEach((node, index) => {
				const up = node.querySelector(':scope > header [data-cb-hud-move="up"], :scope > [data-cb-hud-move="up"], :scope .cb-hud-menu-editor__item-actions [data-cb-hud-move="up"]');
				const down = node.querySelector(':scope > header [data-cb-hud-move="down"], :scope > [data-cb-hud-move="down"], :scope .cb-hud-menu-editor__item-actions [data-cb-hud-move="down"]');
				if (up) up.disabled = index === 0;
				if (down) down.disabled = index === nodes.length - 1;
			});
		}
	};

	const moveNode = (button) => {
		const direction = button.dataset.cbHudMove;
		const node = button.closest('[data-cb-hud-item], [data-cb-hud-section]');
		if (!node || !node.parentElement) return;
		const selector = node.matches('[data-cb-hud-section]') ? '[data-cb-hud-section]' : '[data-cb-hud-item]';
		const siblings = directChildren(node.parentElement, selector);
		const index = siblings.indexOf(node);
		if (direction === 'up' && index > 0) {
			node.parentElement.insertBefore(node, siblings[index - 1]);
		}
		if (direction === 'down' && index >= 0 && index < siblings.length - 1) {
			node.parentElement.insertBefore(siblings[index + 1], node);
		}
		updateMoveButtons();
	};

	const makeCustomId = () => {
		if (globalThis.crypto && typeof globalThis.crypto.randomUUID === 'function') {
			return `cb-hud-custom-${globalThis.crypto.randomUUID()}`;
		}
		return `cb-hud-custom-${Date.now()}-${Math.random().toString(16).slice(2)}`;
	};

	const setValidation = (message = '') => {
		if (validation) validation.textContent = message;
	};

	const addCustomLink = () => {
		if (!customLabel || !customUrl || !customSection || !template) return;
		const label = customLabel.value.trim();
		const url = customUrl.value.trim();
		const sectionId = customSection.value;

		if (!label) {
			setValidation(i18n.labelRequired || 'Enter a label for the custom link.');
			customLabel.focus();
			return;
		}

		let parsed;
		try {
			parsed = new URL(url);
		} catch {
			setValidation(i18n.urlInvalid || 'Enter a valid http or https URL.');
			customUrl.focus();
			return;
		}
		if (!['http:', 'https:'].includes(parsed.protocol)) {
			setValidation(i18n.urlInvalid || 'Enter a valid http or https URL.');
			customUrl.focus();
			return;
		}

		const section = root.querySelector(`[data-cb-hud-section][data-section-id="${CSS.escape(sectionId)}"]`);
		const list = section?.querySelector('[data-cb-hud-item-list]');
		if (!list) {
			setValidation(i18n.sectionInvalid || 'Choose an available menu section.');
			return;
		}

		const fragment = template.content.cloneNode(true);
		const row = fragment.querySelector('[data-cb-hud-item]');
		if (!row) return;
		const id = makeCustomId();
		row.dataset.itemId = id;
		row.dataset.itemLabel = label;
		row.dataset.itemUrl = parsed.href;
		row.querySelector('.cb-hud-menu-editor__item-label').textContent = label;
		row.querySelector('.cb-hud-menu-editor__item-url').textContent = parsed.href;
		list.appendChild(fragment);
		updateEmptyState(list);
		updateMoveButtons();
		customLabel.value = '';
		customUrl.value = '';
		setValidation('');
		customLabel.focus();
	};

	const serialize = () => {
		const config = {
			version: 1,
			section_order: [],
			hidden_sections: [],
			item_order: {},
			hidden_items: [],
			custom_items: [],
		};

		for (const section of directChildren(sectionList, '[data-cb-hud-section]')) {
			const sectionId = section.dataset.sectionId || '';
			if (!sectionId) continue;
			config.section_order.push(sectionId);
			const sectionVisible = section.querySelector('[data-cb-hud-section-visible]');
			if (sectionVisible && !sectionVisible.checked) config.hidden_sections.push(sectionId);

			const list = section.querySelector('[data-cb-hud-item-list]');
			config.item_order[sectionId] = [];
			for (const item of directChildren(list, '[data-cb-hud-item]')) {
				const itemId = item.dataset.itemId || '';
				if (!itemId) continue;
				config.item_order[sectionId].push(itemId);
				const itemVisible = item.querySelector('[data-cb-hud-item-visible]');
				if (itemVisible && !itemVisible.checked) config.hidden_items.push(itemId);
				if (item.dataset.custom === '1') {
					config.custom_items.push({
						id: itemId,
						label: item.dataset.itemLabel || '',
						url: item.dataset.itemUrl || '',
						section: sectionId,
					});
				}
			}
		}

		if (payload) payload.value = JSON.stringify(config);
	};

	root.addEventListener('change', (event) => {
		const control = event.target;
		if (!(control instanceof HTMLInputElement)) return;
		if (control.matches('[data-cb-hud-section-visible]')) {
			control.closest('[data-cb-hud-section]')?.classList.toggle('is-disabled', !control.checked);
		}
		if (control.matches('[data-cb-hud-item-visible]')) {
			control.closest('[data-cb-hud-item]')?.classList.toggle('is-disabled', !control.checked);
		}
	});

	root.addEventListener('click', (event) => {
		const target = event.target instanceof Element ? event.target : null;
		if (!target) return;
		const move = target.closest('[data-cb-hud-move]');
		if (move) {
			moveNode(move);
			return;
		}
		const remove = target.closest('[data-cb-hud-remove-custom]');
		if (remove) {
			const row = remove.closest('[data-cb-hud-item]');
			const list = row?.parentElement;
			row?.remove();
			updateEmptyState(list);
			updateMoveButtons();
		}
	});

	root.addEventListener('pointerdown', (event) => {
		const target = event.target instanceof Element ? event.target : null;
		const handle = target?.closest('[data-cb-hud-drag-handle]');
		const node = handle?.closest('[data-cb-hud-item], [data-cb-hud-section]');
		if (node) node.draggable = true;
	});

	root.addEventListener('dragstart', (event) => {
		const node = event.target instanceof Element ? event.target.closest('[data-cb-hud-item], [data-cb-hud-section]') : null;
		if (!node || !node.draggable) return;
		drag = {
			node,
			type: node.matches('[data-cb-hud-section]') ? 'section' : 'item',
		};
		node.classList.add('is-dragging');
		event.dataTransfer?.setData('text/plain', node.dataset.itemId || node.dataset.sectionId || 'hud-item');
		if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
	});

	root.addEventListener('dragover', (event) => {
		if (!drag) return;
		const target = event.target instanceof Element ? event.target : null;
		if (!target) return;
		const selector = drag.type === 'section' ? '[data-cb-hud-section]' : '[data-cb-hud-item]';
		const over = target.closest(selector);
		if (!over || over === drag.node || over.parentElement !== drag.node.parentElement) return;
		event.preventDefault();
		const rect = over.getBoundingClientRect();
		const after = event.clientY > rect.top + rect.height / 2;
		over.parentElement.insertBefore(drag.node, after ? over.nextSibling : over);
	});

	root.addEventListener('dragend', () => {
		if (drag?.node) {
			drag.node.classList.remove('is-dragging');
			drag.node.draggable = false;
		}
		drag = null;
		updateMoveButtons();
	});

	root.addEventListener('pointerup', (event) => {
		const target = event.target instanceof Element ? event.target : null;
		const node = target?.closest('[data-cb-hud-item], [data-cb-hud-section]');
		if (node && !drag) node.draggable = false;
	});

	addButton?.addEventListener('click', addCustomLink);

	form?.addEventListener('submit', (event) => {
		if (event.submitter instanceof HTMLButtonElement && event.submitter.name === 'cb_hud_menu_reset') {
			return;
		}
		serialize();
	});

	for (const list of root.querySelectorAll('[data-cb-hud-item-list]')) updateEmptyState(list);
	updateMoveButtons();
}
