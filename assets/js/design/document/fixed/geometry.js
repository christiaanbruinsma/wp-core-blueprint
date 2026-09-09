import { isPlainObject } from '../../core/index.js';

export const FIXED_LAYOUT_MODE = 'fixed';
export const FIXED_UNITS = 'mm';

const FRAME_KEYS = ['x', 'y', 'width', 'height'];
const round = (value) => Math.round(value * 10000) / 10000;
const finite = (value) => typeof value === 'number' && Number.isFinite(value);

const exactKeys = (value, keys) => isPlainObject(value)
	&& Object.keys(value).length === keys.length
	&& Object.keys(value).every((key) => keys.includes(key));

export const normalizePage = (layout) => {
	if (
		!exactKeys(layout, ['mode', 'units', 'page'])
		|| layout.mode !== FIXED_LAYOUT_MODE
		|| layout.units !== FIXED_UNITS
		|| !exactKeys(layout.page, ['width', 'height'])
		|| !finite(layout.page.width)
		|| !finite(layout.page.height)
		|| layout.page.width <= 0
		|| layout.page.height <= 0
	) {
		throw new TypeError('Fixed layout requires positive finite page dimensions in millimetres.');
	}
	return Object.freeze({ width: round(layout.page.width), height: round(layout.page.height), units: FIXED_UNITS });
};

export const normalizeFrame = (frame) => {
	if (
		!exactKeys(frame, FRAME_KEYS)
		|| !FRAME_KEYS.every((key) => finite(frame[key]))
		|| frame.x < 0
		|| frame.y < 0
		|| frame.width <= 0
		|| frame.height <= 0
	) {
		throw new TypeError('Fixed frames require non-negative x/y and positive finite width/height.');
	}
	return Object.freeze(Object.fromEntries(FRAME_KEYS.map((key) => [key, round(frame[key])])));
};

export const fixedPageFromRoot = (root) => {
	if (!isPlainObject(root?.properties)) throw new TypeError('Fixed document root is invalid.');
	return normalizePage(root.properties.layout);
};

export const fixedFrameFromNode = (node) => {
	if (!isPlainObject(node?.properties)) throw new TypeError('Fixed document node is invalid.');
	return normalizeFrame(node.properties.frame);
};

const normalizedPageInput = (page) => normalizePage({
	mode: FIXED_LAYOUT_MODE,
	units: FIXED_UNITS,
	page: { width: page?.width, height: page?.height },
});

export const frameWithinPage = (frame, page) => {
	const normalized = normalizeFrame(frame);
	const target = normalizedPageInput(page);
	return normalized.x + normalized.width <= target.width + 0.01
		&& normalized.y + normalized.height <= target.height + 0.01;
};

export const clampFrame = (frame, page) => {
	const current = normalizeFrame(frame);
	const target = normalizedPageInput(page);
	const width = Math.min(current.width, target.width);
	const height = Math.min(current.height, target.height);
	return normalizeFrame({
		x: Math.min(current.x, target.width - width),
		y: Math.min(current.y, target.height - height),
		width,
		height,
	});
};

export const translateFrame = (frame, deltaX, deltaY, page) => {
	if (!finite(deltaX) || !finite(deltaY)) throw new TypeError('Fixed drag deltas must be finite numbers.');
	const current = normalizeFrame(frame);
	return clampFrame({ ...current, x: Math.max(0, current.x + deltaX), y: Math.max(0, current.y + deltaY) }, page);
};

export const resizeFrame = (frame, deltaWidth, deltaHeight, page, { minWidth = 1, minHeight = 1 } = {}) => {
	if (![deltaWidth, deltaHeight, minWidth, minHeight].every(finite) || minWidth <= 0 || minHeight <= 0) {
		throw new TypeError('Fixed resize values must be positive finite constraints and finite deltas.');
	}
	const current = normalizeFrame(frame);
	const target = normalizedPageInput(page);
	const maxWidth = target.width - current.x;
	const maxHeight = target.height - current.y;
	if (maxWidth < minWidth || maxHeight < minHeight) throw new RangeError('Fixed frame cannot satisfy minimum size within the page.');
	return normalizeFrame({
		...current,
		width: Math.min(maxWidth, Math.max(minWidth, current.width + deltaWidth)),
		height: Math.min(maxHeight, Math.max(minHeight, current.height + deltaHeight)),
	});
};
