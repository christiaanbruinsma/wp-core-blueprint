import { createSession, commands } from '@cb-core/design-editor';

const root = document.querySelector('[data-cb-mail-designer]');

if (root) {
	const form = root.querySelector('[data-cb-mail-designer-form]');
	const projectField = root.querySelector('[data-cb-mail-project]');
	const componentField = root.querySelector('[data-cb-mail-components]');
	const subjectField = root.querySelector('[data-cb-mail-subject]');
	const canvas = root.querySelector('[data-cb-mail-canvas]');
	const inspector = root.querySelector('[data-cb-mail-inspector]');
	const preview = root.querySelector('[data-cb-mail-preview]');
	const previewFrame = root.querySelector('[data-cb-mail-preview-frame]');
	const previewStatus = root.querySelector('[data-cb-mail-preview-status]');
	const undoButton = root.querySelector('[data-cb-mail-undo]');
	const redoButton = root.querySelector('[data-cb-mail-redo]');
	const ajaxUrl = root.dataset.ajaxUrl || '';
	const nonce = root.dataset.previewNonce || '';
	const templateId = root.dataset.templateId || '';

	const parseJson = (field, fallback) => {
		try {
			return JSON.parse(field?.value || '');
		} catch (error) {
			return fallback;
		}
	};

	const project = parseJson(projectField, null);
	const components = parseJson(componentField, {});
	const componentDefinitions = Object.values(components).filter((item) => item && typeof item === 'object');

	if (!form || !projectField || !subjectField || !canvas || !inspector || !preview || !project) {
		throw new Error('Mail Designer could not initialize because its editor payload is incomplete.');
	}

	let previewTimer = 0;
	let previewController = null;
	let dragPath = null;

	const definitionForNode = (node) => componentDefinitions.find(
		(definition) => definition.node_type === node?.type && definition.provider === node?.provider
	) || null;

	const nodeAt = (projectValue, path) => {
		let node = projectValue?.root;
		for (const index of path || []) {
			node = node?.children?.[index];
			if (!node) return null;
		}
		return node || null;
	};

	const primarySection = () => {
		const children = session.project()?.root?.children;
		if (!Array.isArray(children)) return null;
		const index = children.findIndex((node) => node?.type === 'mail.section');
		return index < 0 ? null : { index, node: children[index] };
	};

	const makeNode = (definition) => ({
		type: String(definition.node_type || ''),
		provider: String(definition.provider || ''),
		properties: structuredClone(definition.defaults || {}),
		children: [],
	});

	const updateSerializedProject = () => {
		projectField.value = JSON.stringify(session.snapshot());
	};

	const updateHistoryButtons = () => {
		if (undoButton) undoButton.disabled = !session.history.canUndo;
		if (redoButton) redoButton.disabled = !session.history.canRedo;
	};

	const setPreviewStatus = (message, isError = false) => {
		if (!previewStatus) return;
		previewStatus.textContent = message;
		previewStatus.classList.toggle('is-error', Boolean(isError));
	};

	const refreshPreview = async () => {
		if (!ajaxUrl || !nonce || !templateId) return;
		previewController?.abort();
		previewController = new AbortController();
		setPreviewStatus('Updating preview…');

		const body = new URLSearchParams({
			action: 'cb_core_mail_designer_preview',
			nonce,
			template_id: templateId,
			subject: subjectField.value,
			project_json: projectField.value,
		});

		try {
			const response = await fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString(),
				signal: previewController.signal,
			});
			const payload = await response.json();
			if (!response.ok || !payload?.success || typeof payload?.data?.html !== 'string') {
				throw new Error(payload?.data?.message || 'Preview failed.');
			}
			preview.srcdoc = payload.data.html;
			setPreviewStatus('Preview updated');
		} catch (error) {
			if (error?.name === 'AbortError') return;
			setPreviewStatus(error?.message || 'Preview failed.', true);
		}
	};

	const schedulePreview = () => {
		window.clearTimeout(previewTimer);
		previewTimer = window.setTimeout(refreshPreview, 250);
	};

	const fieldValue = (input, type) => {
		if (type === 'number') {
			const value = Number(input.value);
			return Number.isFinite(value) ? value : 0;
		}
		return input.value;
	};

	const createInspectorField = (field, value, path) => {
		const wrapper = document.createElement('div');
		wrapper.className = 'cb-core-field cb-core-mail-inspector-field';
		const id = `cb-mail-inspector-${field.key}-${path.join('-') || 'root'}`;
		const label = document.createElement('label');
		label.className = 'cb-core-field__label';
		label.htmlFor = id;
		label.textContent = field.label || field.key;
		wrapper.append(label);

		let input;
		if (field.type === 'textarea') {
			input = document.createElement('textarea');
			input.rows = 5;
		} else if (field.type === 'select') {
			input = document.createElement('select');
			Object.entries(field.options || {}).forEach(([optionValue, optionLabel]) => {
				const option = document.createElement('option');
				option.value = optionValue;
				option.textContent = optionLabel;
				input.append(option);
			});
		} else {
			input = document.createElement('input');
			input.type = ['number', 'color', 'url'].includes(field.type) ? field.type : 'text';
			if (field.type === 'number') {
				if (Number.isFinite(Number(field.min))) input.min = String(field.min);
				if (Number.isFinite(Number(field.max))) input.max = String(field.max);
				if (Number.isFinite(Number(field.step))) input.step = String(field.step);
			}
		}
		input.id = id;
		input.value = value ?? '';
		input.addEventListener('change', () => {
			session.execute(commands.setProperty(path, [field.key], fieldValue(input, field.type)));
		});
		wrapper.append(input);
		return wrapper;
	};

	const renderRootInspector = () => {
		inspector.replaceChildren();
		const heading = document.createElement('h3');
		heading.textContent = 'Email style';
		inspector.append(heading);

		const rootProperties = session.project()?.root?.properties || {};
		const layout = rootProperties.layout || {};
		const fields = [
			{ key: 'preheader', label: 'Preheader', type: 'text', propertyPath: ['preheader'], value: rootProperties.preheader || '' },
			{ key: 'width', label: 'Content width', type: 'number', min: 320, max: 800, step: 1, propertyPath: ['layout', 'width'], value: layout.width ?? 600 },
			{ key: 'background', label: 'Page background', type: 'color', propertyPath: ['layout', 'background'], value: layout.background || '#f3f4f6' },
			{ key: 'contentBackground', label: 'Content background', type: 'color', propertyPath: ['layout', 'contentBackground'], value: layout.contentBackground || '#ffffff' },
			{ key: 'textColor', label: 'Default text color', type: 'color', propertyPath: ['layout', 'textColor'], value: layout.textColor || '#1f2937' },
			{ key: 'accentColor', label: 'Accent color', type: 'color', propertyPath: ['layout', 'accentColor'], value: layout.accentColor || '#2563eb' },
		];

		fields.forEach((field) => {
			const wrapper = document.createElement('div');
			wrapper.className = 'cb-core-field cb-core-mail-inspector-field';
			const id = `cb-mail-root-${field.key}`;
			const label = document.createElement('label');
			label.className = 'cb-core-field__label';
			label.htmlFor = id;
			label.textContent = field.label;
			const input = document.createElement('input');
			input.id = id;
			input.type = field.type;
			input.value = field.value;
			if (field.type === 'number') {
				input.min = String(field.min);
				input.max = String(field.max);
				input.step = String(field.step);
			}
			input.addEventListener('change', () => {
				const value = fieldValue(input, field.type);
				session.execute(commands.setProperty([], field.propertyPath, value));
			});
			wrapper.append(label, input);
			inspector.append(wrapper);
		});
	};

	const renderInspector = () => {
		const selectedPath = session.editorState.selection.primary();
		if (!selectedPath) {
			renderRootInspector();
			return;
		}
		const node = nodeAt(session.project(), selectedPath);
		const definition = definitionForNode(node);
		if (!node || !definition) {
			renderRootInspector();
			return;
		}

		inspector.replaceChildren();
		const heading = document.createElement('h3');
		heading.textContent = definition.label || node.type;
		inspector.append(heading);
		(definition.inspector || []).forEach((field) => {
			inspector.append(createInspectorField(field, node.properties?.[field.key], selectedPath));
		});

		const remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'button cb-core-button cb-core-mail-inspector-remove';
		remove.textContent = 'Remove element';
		remove.addEventListener('click', () => session.execute(commands.removeNode(selectedPath)));
		inspector.append(remove);
	};

	const nodeSummary = (node, definition) => {
		const properties = node?.properties || {};
		const source = properties.text || properties.label || properties.alt || properties.url || '';
		const text = String(source).replace(/\s+/g, ' ').trim();
		return text ? text.slice(0, 90) : (definition?.label || node?.type || 'Element');
	};

	const renderCanvas = () => {
		canvas.replaceChildren();
		const section = primarySection();
		if (!section) {
			const empty = document.createElement('p');
			empty.textContent = 'This template does not contain an editable mail section.';
			canvas.append(empty);
			return;
		}

		const selectedKey = JSON.stringify(session.editorState.selection.primary());
		section.node.children.forEach((node, index) => {
			const path = [section.index, index];
			const definition = definitionForNode(node);
			const card = document.createElement('div');
			card.className = 'cb-core-mail-node';
			card.tabIndex = 0;
			card.draggable = true;
			card.dataset.path = JSON.stringify(path);
			if (JSON.stringify(path) === selectedKey) card.classList.add('is-selected');

			const meta = document.createElement('div');
			meta.className = 'cb-core-mail-node__meta';
			const label = document.createElement('strong');
			label.textContent = definition?.label || node.type;
			const provider = document.createElement('small');
			provider.textContent = node.provider === 'core' ? 'Core Blueprint' : node.provider;
			meta.append(label, provider);

			const summary = document.createElement('p');
			summary.textContent = nodeSummary(node, definition);

			const actions = document.createElement('div');
			actions.className = 'cb-core-mail-node__actions';
			const move = (labelText, toIndex, disabled) => {
				const button = document.createElement('button');
				button.type = 'button';
				button.className = 'button cb-core-button';
				button.textContent = labelText;
				button.disabled = disabled;
				button.addEventListener('click', (event) => {
					event.stopPropagation();
					session.execute(commands.reorderNode([section.index], index, toIndex));
				});
				return button;
			};
			actions.append(
				move('↑', Math.max(0, index - 1), index === 0),
				move('↓', Math.min(section.node.children.length - 1, index + 1), index === section.node.children.length - 1)
			);

			card.append(meta, summary, actions);
			card.addEventListener('click', () => {
				session.editorState.selection.select(path);
				renderCanvas();
				renderInspector();
			});
			card.addEventListener('keydown', (event) => {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					card.click();
				}
			});
			card.addEventListener('dragstart', () => { dragPath = path; });
			card.addEventListener('dragover', (event) => event.preventDefault());
			card.addEventListener('drop', (event) => {
				event.preventDefault();
				if (!Array.isArray(dragPath) || dragPath[0] !== section.index || dragPath[1] === index) return;
				session.execute(commands.reorderNode([section.index], dragPath[1], index));
				dragPath = null;
			});
			canvas.append(card);
		});
	};

	const render = () => {
		updateSerializedProject();
		renderCanvas();
		renderInspector();
		updateHistoryButtons();
		schedulePreview();
	};

	const session = createSession({
		project,
		profile: 'mail',
		onChange: render,
	});

	root.querySelectorAll('[data-cb-mail-add]').forEach((button) => {
		button.addEventListener('click', () => {
			const definition = components[button.dataset.cbMailAdd || ''];
			const section = primarySection();
			if (!definition || !section) return;
			session.execute(commands.insertNode([section.index], section.node.children.length, makeNode(definition)));
		});
	});

	root.querySelectorAll('[data-cb-mail-binding]').forEach((button) => {
		button.addEventListener('click', async () => {
			const token = button.dataset.cbMailBinding || '';
			if (!token) return;
			try {
				await navigator.clipboard.writeText(token);
				const previous = button.textContent;
				button.textContent = 'Copied';
				window.setTimeout(() => { button.textContent = previous; }, 900);
			} catch (error) {
				button.focus();
			}
		});
	});

	undoButton?.addEventListener('click', () => {
		session.undo();
		render();
	});
	redoButton?.addEventListener('click', () => {
		session.redo();
		render();
	});

	root.querySelectorAll('[data-cb-mail-viewport]').forEach((button) => {
		button.addEventListener('click', () => {
			const mobile = button.dataset.cbMailViewport === 'mobile';
			previewFrame?.classList.toggle('is-mobile', mobile);
			root.querySelectorAll('[data-cb-mail-viewport]').forEach((candidate) => candidate.classList.toggle('is-active', candidate === button));
		});
	});

	subjectField.addEventListener('input', schedulePreview);
	form.addEventListener('submit', updateSerializedProject);

	window.addEventListener('beforeunload', () => {
		previewController?.abort();
		session.dispose();
	}, { once: true });

	render();
}
