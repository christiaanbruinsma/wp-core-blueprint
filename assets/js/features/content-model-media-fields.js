(() => {
	'use strict';

	if (!window.wp?.media) {
		return;
	}

	const attachmentImageUrl = (attachment) => {
		const sizes = attachment?.get?.('sizes') || attachment?.attributes?.sizes || {};
		return sizes.thumbnail?.url || sizes.medium?.url || attachment?.get?.('url') || attachment?.attributes?.url || '';
	};

	const attachmentLabel = (attachment) => {
		return attachment?.get?.('filename') || attachment?.attributes?.filename || attachment?.get?.('title') || attachment?.attributes?.title || '';
	};

	const createImage = (url, className) => {
		const img = document.createElement('img');
		img.src = url;
		img.alt = '';
		img.className = className;
		return img;
	};

	const initSingleMediaField = (root) => {
		if (root.dataset.cbCmReady === '1') return;
		root.dataset.cbCmReady = '1';

		const input = root.querySelector('[data-cb-cm-media-value]');
		const preview = root.querySelector('[data-cb-cm-media-preview]');
		const selectButton = root.querySelector('[data-cb-cm-media-select]');
		const removeButton = root.querySelector('[data-cb-cm-media-remove]');
		if (!input || !preview || !selectButton || !removeButton) return;

		const imageOnly = root.dataset.mediaKind === 'image';
		let frame = null;

		const renderSelection = (attachment) => {
			preview.replaceChildren();
			if (imageOnly) {
				const url = attachmentImageUrl(attachment);
				if (url) preview.append(createImage(url, 'cb-cm-media-thumbnail'));
			} else {
				const file = document.createElement('div');
				file.className = 'cb-cm-file-preview';
				const icon = attachment?.get?.('icon') || attachment?.attributes?.icon || '';
				if (icon) file.append(createImage(icon, ''));
				const label = document.createElement('span');
				label.textContent = attachmentLabel(attachment);
				file.append(label);
				preview.append(file);
			}
		};

		selectButton.addEventListener('click', () => {
			if (!frame) {
				const options = {
					title: root.dataset.frameTitle || '',
					button: { text: root.dataset.selectLabel || '' },
					multiple: false,
				};
				if (imageOnly) options.library = { type: 'image' };
				frame = window.wp.media(options);
				frame.on('select', () => {
					const attachment = frame.state().get('selection').first();
					if (!attachment) return;
					input.value = String(attachment.id || '');
					renderSelection(attachment);
					selectButton.textContent = root.dataset.replaceLabel || root.dataset.selectLabel || '';
					removeButton.hidden = false;
				});
				frame.on('open', () => {
					const id = Number.parseInt(input.value, 10);
					if (!Number.isInteger(id) || id <= 0) return;
					const attachment = window.wp.media.attachment(id);
					frame.state().get('selection').reset([attachment]);
				});
			}
			frame.open();
		});

		removeButton.addEventListener('click', () => {
			input.value = '';
			preview.replaceChildren();
			selectButton.textContent = root.dataset.selectLabel || '';
			removeButton.hidden = true;
		});
	};

	const initGalleryField = (root) => {
		if (root.dataset.cbCmReady === '1') return;
		root.dataset.cbCmReady = '1';

		const items = root.querySelector('[data-cb-cm-gallery-items]');
		const selectButton = root.querySelector('[data-cb-cm-gallery-select]');
		if (!items || !selectButton) return;
		let frame = null;
		let dragging = null;

		const inputName = () => items.querySelector('input[type="hidden"]')?.name || root.dataset.inputName || '';

		const createItem = (attachment, name) => {
			const id = Number.parseInt(String(attachment.id || attachment.get?.('id') || ''), 10);
			if (!Number.isInteger(id) || id <= 0) return null;
			const card = document.createElement('div');
			card.className = 'cb-cm-gallery-item';
			card.draggable = true;
			card.dataset.attachmentId = String(id);
			const hidden = document.createElement('input');
			hidden.type = 'hidden';
			hidden.name = name;
			hidden.value = String(id);
			card.append(hidden);
			const url = attachmentImageUrl(attachment);
			if (url) card.append(createImage(url, 'cb-cm-gallery-thumbnail'));
			const remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'button-link-delete cb-cm-gallery-remove';
			remove.dataset.cbCmGalleryRemove = '';
			remove.setAttribute('aria-label', root.dataset.removeLabel || 'Remove image');
			remove.textContent = '×';
			card.append(remove);
			return card;
		};

		const bindItem = (card) => {
			card.querySelector('[data-cb-cm-gallery-remove]')?.addEventListener('click', () => card.remove());
			card.addEventListener('dragstart', (event) => {
				dragging = card;
				card.classList.add('is-dragging');
				if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
			});
			card.addEventListener('dragend', () => {
				card.classList.remove('is-dragging');
				items.querySelectorAll('.is-drop-target').forEach((node) => node.classList.remove('is-drop-target'));
				dragging = null;
			});
			card.addEventListener('dragover', (event) => {
				if (!dragging || dragging === card) return;
				event.preventDefault();
				items.querySelectorAll('.is-drop-target').forEach((node) => node.classList.remove('is-drop-target'));
				card.classList.add('is-drop-target');
				const rect = card.getBoundingClientRect();
				items.insertBefore(dragging, event.clientX - rect.left < rect.width / 2 ? card : card.nextSibling);
			});
			card.addEventListener('drop', (event) => {
				event.preventDefault();
				card.classList.remove('is-drop-target');
			});
		};

		items.querySelectorAll('.cb-cm-gallery-item').forEach(bindItem);

		selectButton.addEventListener('click', () => {
			if (!frame) {
				frame = window.wp.media({
					title: root.dataset.frameTitle || '',
					button: { text: root.dataset.selectLabel || '' },
					library: { type: 'image' },
					multiple: true,
				});
				frame.on('open', () => {
					const current = Array.from(items.querySelectorAll('[data-attachment-id]'))
						.map((node) => Number.parseInt(node.dataset.attachmentId || '', 10))
						.filter((id) => Number.isInteger(id) && id > 0)
						.map((id) => window.wp.media.attachment(id));
					frame.state().get('selection').reset(current);
				});
				frame.on('select', () => {
					const name = inputName() || root.dataset.inputName || '';
					items.replaceChildren();
					frame.state().get('selection').each((attachment) => {
						const card = createItem(attachment, name);
						if (card) {
							items.append(card);
							bindItem(card);
						}
					});
				});
			}
			frame.open();
		});
	};

	const init = (scope = document) => {
		scope.querySelectorAll?.('[data-cb-cm-media-field]').forEach(initSingleMediaField);
		scope.querySelectorAll?.('[data-cb-cm-gallery-field]').forEach(initGalleryField);
	};

	window.cbCore = window.cbCore || {};
	window.cbCore.contentModelMedia = { init };
	init();
})();
