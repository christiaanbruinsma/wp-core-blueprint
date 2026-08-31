(() => {
	'use strict';

	const uuid = () => {
		if (window.crypto?.randomUUID) return `row_${window.crypto.randomUUID().replaceAll('-', '')}`;
		return `row_${Date.now().toString(36)}${Math.random().toString(36).slice(2)}`;
	};

	const replaceIndex = (root, from, to) => {
		root.querySelectorAll('[name], [id], label[for]').forEach((node) => {
			if (node.hasAttribute('name')) node.name = node.name.replaceAll(`[${from}]`, `[${to}]`);
			if (node.id) node.id = node.id.replaceAll(`-${from}-`, `-${to}-`).replaceAll(`-${from}`, `-${to}`);
			const labelFor = node.getAttribute('for');
			if (labelFor) node.setAttribute('for', labelFor.replaceAll(`-${from}-`, `-${to}-`).replaceAll(`-${from}`, `-${to}`));
		});
	};

	const refreshEnhancedFields = (scope) => {
		window.cbCore?.objectPicker?.init?.(scope);
		window.cbCore?.contentModelMedia?.init?.(scope);
	};

	const initRepeater = (root) => {
		if (root.dataset.cbCmRepeaterReady === '1') return;
		root.dataset.cbCmRepeaterReady = '1';
		const rows = root.querySelector('[data-cb-cm-repeater-rows]');
		const template = root.querySelector('template[data-cb-cm-repeater-template]');
		const add = root.querySelector('[data-cb-cm-repeater-add]');
		if (!rows || !template || !add) return;

		const min = Math.max(0, Number.parseInt(root.dataset.min || '0', 10) || 0);
		const max = Math.max(0, Number.parseInt(root.dataset.max || '0', 10) || 0);
		const rowLabelTemplate = root.dataset.rowLabelTemplate || 'Row %d';
		const rowLabel = (index) => rowLabelTemplate.replace('%d', String(index + 1));
		let dragging = null;

		const currentRows = () => Array.from(rows.querySelectorAll(':scope > [data-cb-cm-repeater-row]'));
		const renumber = () => {
			currentRows().forEach((row, index) => {
				const previous = row.dataset.rowIndex ?? String(index);
				replaceIndex(row, previous, String(index));
				row.dataset.rowIndex = String(index);
				const label = row.querySelector('[data-cb-cm-repeater-row-label]');
				if (label) label.textContent = rowLabel(index);
			});
			add.disabled = max > 0 && currentRows().length >= max;
		};

		const bindRow = (row) => {
			if (row.dataset.cbCmRepeaterRowReady === '1') return;
			row.dataset.cbCmRepeaterRowReady = '1';
			row.querySelector('[data-cb-cm-repeater-remove]')?.addEventListener('click', () => {
				if (currentRows().length <= min) return;
				row.remove();
				renumber();
			});
			row.addEventListener('dragstart', (event) => {
				if (!event.target.closest('[data-cb-cm-repeater-handle]') && event.target !== row) return;
				dragging = row;
				row.classList.add('is-dragging');
				if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
			});
			row.addEventListener('dragend', () => {
				row.classList.remove('is-dragging');
				currentRows().forEach((item) => item.classList.remove('is-drop-target'));
				dragging = null;
				renumber();
			});
			row.addEventListener('dragover', (event) => {
				if (!dragging || dragging === row) return;
				event.preventDefault();
				const rect = row.getBoundingClientRect();
				rows.insertBefore(dragging, event.clientY - rect.top < rect.height / 2 ? row : row.nextSibling);
				row.classList.add('is-drop-target');
			});
			row.addEventListener('drop', (event) => event.preventDefault());
		};

		currentRows().forEach((row, index) => {
			row.dataset.rowIndex = String(index);
			bindRow(row);
		});
		renumber();

		add.addEventListener('click', () => {
			if (max > 0 && currentRows().length >= max) return;
			const index = currentRows().length;
			const fragment = template.content.cloneNode(true);
			const row = fragment.querySelector('[data-cb-cm-repeater-row]');
			if (!row) return;
			replaceIndex(row, '__INDEX__', String(index));
			row.dataset.rowIndex = String(index);
			const rowId = row.querySelector('[data-cb-cm-row-id]');
			if (rowId) rowId.value = uuid();
			rows.append(row);
			bindRow(row);
			refreshEnhancedFields(row);
			renumber();
		});
	};

	const fieldValue = (field) => {
		const type = field.dataset.fieldType || '';
		const controls = Array.from(field.querySelectorAll('input, select, textarea'))
			.filter((control) => !control.name.includes('_present') && !control.closest('template'));
		if (type === 'true_false') {
			return controls.some((control) => control.type === 'checkbox' && control.checked && control.value === '1') ? '1' : '0';
		}
		if (type === 'checkbox') {
			return controls.filter((control) => control.type === 'checkbox' && control.checked).map((control) => control.value);
		}
		if (type === 'radio') {
			return controls.find((control) => control.type === 'radio' && control.checked)?.value ?? '';
		}
		const values = controls
			.filter((control) => control.type !== 'button' && control.type !== 'submit' && control.type !== 'hidden' || control.dataset.cbCoreObjectPickerValue !== undefined || control.dataset.cbCmMediaValue !== undefined)
			.map((control) => control.value)
			.filter((value) => value !== '');
		return values.length > 1 ? values : (values[0] ?? '');
	};

	const isEmpty = (value, type = '') => {
		if (Array.isArray(value)) return value.length === 0;
		if (type === 'true_false') return value === '' || value === null || value === undefined || value === '0' || value === false;
		return value === '' || value === null || value === undefined;
	};
	const matchesRule = (rule, fieldMap) => {
		const source = fieldMap.get(String(rule.field || ''));
		if (!source) return false;
		const value = fieldValue(source);
		const type = source.dataset.fieldType || '';
		const expected = String(rule.value ?? '');
		const equals = Array.isArray(value) ? value.map(String).includes(expected) : String(value) === expected;
		switch (rule.operator) {
			case 'not_equals': return !equals;
			case 'empty': return isEmpty(value, type);
			case 'not_empty': return !isEmpty(value, type);
			case 'equals':
			default: return equals;
		}
	};

	const initConditionalLogic = (scope = document) => {
		const fields = Array.from(scope.querySelectorAll('[data-cb-cm-runtime-field]'));
		if (!fields.length) return;
		const fieldMap = new Map(fields.map((field) => [field.dataset.fieldId || '', field]));
		const conditionalFields = fields.filter((field) => field.dataset.conditional);
		if (!conditionalFields.length) return;
		const evaluate = () => {
			conditionalFields.forEach((field) => {
				let groups = [];
				try { groups = JSON.parse(field.dataset.conditional || '[]'); } catch (_) { groups = []; }
				const visible = !groups.length || groups.some((group) => Array.isArray(group) && group.every((rule) => matchesRule(rule, fieldMap)));
				field.hidden = !visible;
			});
		};
		scope.addEventListener('input', evaluate);
		scope.addEventListener('change', evaluate);
		evaluate();
	};

	const init = (scope = document) => {
		scope.querySelectorAll?.('[data-cb-cm-repeater]').forEach(initRepeater);
		initConditionalLogic(scope);
	};

	window.cbCore = window.cbCore || {};
	window.cbCore.contentModelFields = { init };
	init();
})();
