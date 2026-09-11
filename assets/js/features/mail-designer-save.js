const root = document.querySelector('[data-cb-mail-designer]');

if (root) {
	const form = root.querySelector('[data-cb-mail-designer-form]');
	const shell = root.querySelector('[data-cb-design-shell]');
	const ajaxUrl = String(root.dataset.ajaxUrl || '').trim();
	let saveController = null;

	const announceSaveState = (state, message = '') => {
		if (!shell) return;
		shell.dispatchEvent(new CustomEvent('cb:design-shell:savechange', {
			bubbles: true,
			detail: Object.freeze({
				state: String(state || '').trim(),
				message: String(message || '').trim(),
			}),
		}));
	};

	const saveInPlace = async () => {
		if (!form || !ajaxUrl) return;

		saveController?.abort();
		saveController = new AbortController();
		announceSaveState('saving');

		try {
			const response = await fetch(ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: new FormData(form),
				signal: saveController.signal,
			});
			const payload = await response.json();
			if (!response.ok || payload?.success !== true) {
				throw new Error(payload?.data?.message || 'The mail template could not be saved.');
			}
			announceSaveState('saved', payload?.data?.message || '');
		} catch (error) {
			if (error?.name === 'AbortError') return;
			announceSaveState('error', error?.message || 'The mail template could not be saved.');
		} finally {
			saveController = null;
		}
	};

	if (form && ajaxUrl && typeof fetch === 'function' && typeof FormData === 'function') {
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			void saveInPlace();
		});
	}

	window.addEventListener('beforeunload', () => {
		saveController?.abort();
	}, { once: true });
}
