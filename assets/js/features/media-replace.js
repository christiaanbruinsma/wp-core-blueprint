/**
 * Media Replace replacement-file preview.
 *
 * Uses an object URL so the selected local image can be compared with the
 * current attachment before the form is submitted. Nothing is uploaded until
 * the user explicitly clicks Replace file.
 *
 * @since   1.0.0
 */

const forms = document.querySelectorAll('[data-cb-media-replace-form]');

forms.forEach((form) => {
	const input = form.querySelector('[data-cb-media-replace-input]');
	const preview = form.querySelector('[data-cb-media-replace-preview]');
	const image = form.querySelector('[data-cb-media-replace-preview-image]');

	if (!(input instanceof HTMLInputElement) || !(preview instanceof HTMLElement) || !(image instanceof HTMLImageElement)) {
		return;
	}

	let objectUrl = '';

	const clearPreview = () => {
		if (objectUrl) {
			URL.revokeObjectURL(objectUrl);
			objectUrl = '';
		}

		image.removeAttribute('src');
		preview.hidden = true;
	};

	input.addEventListener('change', () => {
		clearPreview();

		const file = input.files?.[0];
		if (!(file instanceof File) || !file.type.startsWith('image/')) {
			return;
		}

		objectUrl = URL.createObjectURL(file);
		image.src = objectUrl;
		preview.hidden = false;
	});

	window.addEventListener('pagehide', clearPreview, { once: true });
});
