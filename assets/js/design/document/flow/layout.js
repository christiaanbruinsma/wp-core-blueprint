const MAX_MM = 2000;

const isFiniteNumber = (value, allowZero = true) => (
	(typeof value === 'number')
	&& Number.isFinite(value)
	&& value <= MAX_MM
	&& (allowZero ? value >= 0 : value > 0)
);

const hasOnly = (value, keys) => value && typeof value === 'object' && !Array.isArray(value)
	&& Object.keys(value).every((key) => keys.includes(key));

export const normalizeFlowLayout = (layout) => {
	if (!hasOnly(layout, ['mode', 'units', 'page', 'margins']) || layout.mode !== 'flow' || layout.units !== 'mm') {
		throw new TypeError('Flow layout must use the root-owned millimetre contract.');
	}
	if (!hasOnly(layout.page, ['width', 'height']) || !isFiniteNumber(layout.page.width, false) || !isFiniteNumber(layout.page.height, false)) {
		throw new TypeError('Flow page dimensions are invalid.');
	}
	if (!hasOnly(layout.margins, ['top', 'right', 'bottom', 'left'])) {
		throw new TypeError('Flow margins are invalid.');
	}
	const margins = {};
	for (const key of ['top', 'right', 'bottom', 'left']) {
		if (!isFiniteNumber(layout.margins[key])) throw new TypeError('Flow margins are invalid.');
		margins[key] = layout.margins[key];
	}
	if (margins.left + margins.right >= layout.page.width || margins.top + margins.bottom >= layout.page.height) {
		throw new RangeError('Flow margins must leave positive content space.');
	}
	return Object.freeze({
		mode: 'flow',
		units: 'mm',
		page: Object.freeze({ width: layout.page.width, height: layout.page.height }),
		margins: Object.freeze(margins),
	});
};

export const normalizeFlowHints = (hints = {}) => {
	if (!hasOnly(hints, ['space_before', 'space_after', 'break_before', 'break_after', 'keep_together'])) {
		throw new TypeError('Flow hints contain unsupported keys.');
	}
	const before = hints.space_before ?? 0;
	const after = hints.space_after ?? 0;
	if (!isFiniteNumber(before) || !isFiniteNumber(after)) throw new TypeError('Flow spacing is invalid.');
	for (const key of ['break_before', 'break_after', 'keep_together']) {
		if (key in hints && typeof hints[key] !== 'boolean') throw new TypeError('Flow break hints must be boolean.');
	}
	return Object.freeze({
		space_before: before,
		space_after: after,
		break_before: hints.break_before ?? false,
		break_after: hints.break_after ?? false,
		keep_together: hints.keep_together ?? false,
	});
};
