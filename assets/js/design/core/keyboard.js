const editableTarget = (target) => {
	if (!target || typeof target !== 'object') return false;
	if (target.isContentEditable === true) return true;
	const tag = String(target.tagName || '').toLowerCase();
	return ['input', 'textarea', 'select'].includes(tag);
};

export const shortcutForEvent = (event) => {
	if (!event || event.defaultPrevented || editableTarget(event.target) || event.altKey) return null;
	const key = String(event.key || '').toLowerCase();
	const primaryModifier = Boolean(event.metaKey || event.ctrlKey);

	if (primaryModifier && key === 'z') return event.shiftKey ? 'redo' : 'undo';
	if (primaryModifier && key === 'y' && !event.shiftKey) return 'redo';
	if (!primaryModifier && !event.shiftKey && (key === 'delete' || key === 'backspace')) return 'delete';
	return null;
};

export const handleEditorShortcut = (event, { history = null, onDelete = null } = {}) => {
	const action = shortcutForEvent(event);
	if (!action) return false;

	let handled = false;
	if (action === 'undo') handled = Boolean(history?.undo?.());
	else if (action === 'redo') handled = Boolean(history?.redo?.());
	else if (action === 'delete' && typeof onDelete === 'function') handled = onDelete() !== false;

	if (handled && typeof event.preventDefault === 'function') event.preventDefault();
	return handled;
};
