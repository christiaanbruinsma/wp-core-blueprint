const dataEl = document.getElementById('wp-script-module-data-@cb-core/object-picker');
let data = {};
try {
	data = dataEl ? JSON.parse(dataEl.textContent) : {};
} catch {
	data = {};
}
const ajaxUrl = data.ajaxUrl || window.ajaxurl || '';
const i18n = data.i18n || {};

const parseJson = (value, fallback) => {
	try {
		return JSON.parse(value || '');
	} catch {
		return fallback;
	}
};

const initPicker = (root) => {
	if (root.dataset.cbCoreObjectPickerReady === '1') return;
	const input = root.querySelector('[data-cb-core-object-picker-input]');
	const enhanced = root.querySelector('[data-cb-core-object-picker-enhanced]');
	const selectedEl = root.querySelector('[data-cb-core-object-picker-selected]');
	const search = root.querySelector('[data-cb-core-object-picker-search]');
	const results = root.querySelector('[data-cb-core-object-picker-results]');
	if (!input || !enhanced || !selectedEl || !search || !results || !ajaxUrl) return;

	const multiple = root.dataset.multiple === '1';
	const action = root.dataset.searchAction || '';
	const nonce = root.dataset.searchNonce || '';
	const context = parseJson(root.dataset.searchContext, {});
	const initial = parseJson(root.dataset.selected, []);
	if (!action || !nonce || !Array.isArray(initial)) return;

	let selected = initial
		.filter((item) => item && Number.parseInt(item.id, 10) > 0)
		.map((item) => ({
			id: Number.parseInt(item.id, 10),
			label: String(item.label || `#${item.id}`),
			meta: String(item.meta || ''),
		}));
	if (!multiple && selected.length > 1) selected = [selected[0]];

	let timer = null;
	let controller = null;

	const syncInput = () => {
		input.value = selected.map((item) => item.id).join(',');
		input.dispatchEvent(new Event('change', { bubbles: true }));
	};

	const renderSelected = () => {
		selectedEl.textContent = '';
		if (!selected.length) {
			const empty = document.createElement('span');
			empty.className = 'cb-core-object-picker__selected-empty';
			empty.textContent = i18n.noneSelected || 'Nothing selected.';
			selectedEl.appendChild(empty);
			return;
		}
		selected.forEach((item) => {
			const chip = document.createElement('span');
			chip.className = 'cb-core-object-picker__chip';
			const copy = document.createElement('span');
			copy.className = 'cb-core-object-picker__chip-copy';
			const label = document.createElement('span');
			label.className = 'cb-core-object-picker__chip-label';
			label.textContent = item.label;
			copy.appendChild(label);
			if (item.meta) {
				const meta = document.createElement('span');
				meta.className = 'cb-core-object-picker__chip-meta';
				meta.textContent = item.meta;
				copy.appendChild(meta);
			}
			const remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'button-link cb-core-object-picker__remove';
			remove.setAttribute('aria-label', `${i18n.remove || 'Remove'} ${item.label}`);
			remove.textContent = '×';
			remove.addEventListener('click', () => {
				selected = selected.filter((candidate) => candidate.id !== item.id);
				syncInput();
				renderSelected();
			});
			chip.append(copy, remove);
			selectedEl.appendChild(chip);
		});
	};

	const closeResults = () => {
		results.hidden = true;
		results.textContent = '';
	};

	const renderResults = (items) => {
		results.textContent = '';
		if (!Array.isArray(items) || !items.length) {
			const empty = document.createElement('div');
			empty.className = 'cb-core-object-picker__empty';
			empty.textContent = root.dataset.emptyMessage || i18n.noResults || 'No matching items found.';
			results.appendChild(empty);
			results.hidden = false;
			return;
		}
		items.forEach((item) => {
			const id = Number.parseInt(item?.id, 10);
			if (!id || selected.some((candidate) => candidate.id === id)) return;
			const button = document.createElement('button');
			button.type = 'button';
			button.className = 'button cb-core-object-picker__result';
			const label = document.createElement('span');
			label.className = 'cb-core-object-picker__result-label';
			label.textContent = String(item.label || `#${id}`);
			button.appendChild(label);
			if (item.meta) {
				const meta = document.createElement('span');
				meta.className = 'cb-core-object-picker__result-meta';
				meta.textContent = String(item.meta);
				button.appendChild(meta);
			}
			button.addEventListener('click', () => {
				const chosen = { id, label: label.textContent, meta: String(item.meta || '') };
				selected = multiple ? [...selected, chosen] : [chosen];
				syncInput();
				renderSelected();
				search.value = '';
				closeResults();
				search.focus();
			});
			results.appendChild(button);
		});
		if (!results.children.length) {
			closeResults();
			return;
		}
		results.hidden = false;
	};

	const runSearch = async () => {
		const term = search.value.trim();
		if (term.length < 2) {
			closeResults();
			return;
		}
		controller?.abort();
		controller = new AbortController();
		const body = new FormData();
		body.append('action', action);
		body.append('_ajax_nonce', nonce);
		body.append('search', term);
		Object.entries(context).forEach(([key, value]) => body.append(`context[${key}]`, String(value ?? '')));
		try {
			const response = await fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body,
				signal: controller.signal,
			});
			const payload = await response.json();
			if (!payload?.success) throw new Error(payload?.data?.message || i18n.searchError || 'Search failed.');
			renderResults(payload.data?.items || []);
		} catch (error) {
			if (error?.name === 'AbortError') return;
			renderResults([]);
		}
	};

	root.dataset.cbCoreObjectPickerReady = '1';
	root.classList.add('is-enhanced');
	input.classList.add('screen-reader-text');
	input.setAttribute('aria-hidden', 'true');
	input.tabIndex = -1;
	enhanced.hidden = false;
	renderSelected();

	search.addEventListener('input', () => {
		window.clearTimeout(timer);
		timer = window.setTimeout(runSearch, 250);
	});
	search.addEventListener('keydown', (event) => {
		if (event.key === 'Escape') closeResults();
	});
	document.addEventListener('click', (event) => {
		if (!root.contains(event.target)) closeResults();
	});
};

const init = (scope = document) => {
	scope.querySelectorAll('[data-cb-core-object-picker]').forEach(initPicker);
};

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () => init());
} else {
	init();
}

window.cbCore = window.cbCore || {};
window.cbCore.objectPicker = Object.freeze({ init });
