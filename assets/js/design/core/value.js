const MAX_VALUE_DEPTH = 128;

export const isPlainObject = (value) => {
	if (value === null || typeof value !== 'object' || Array.isArray(value)) return false;
	const prototype = Object.getPrototypeOf(value);
	return prototype === Object.prototype || prototype === null;
};

export const cloneValue = (value, depth = 0) => {
	if (depth > MAX_VALUE_DEPTH) throw new TypeError('Design value exceeds the supported nesting depth.');
	if (value === null || typeof value === 'string' || typeof value === 'boolean') return value;
	if (typeof value === 'number') {
		if (!Number.isFinite(value)) throw new TypeError('Design values must contain finite numbers.');
		return value;
	}
	if (Array.isArray(value)) return value.map((item) => cloneValue(item, depth + 1));
	if (!isPlainObject(value)) throw new TypeError('Design values must be JSON-compatible plain values.');
	return Object.fromEntries(
		Object.entries(value).map(([key, item]) => [key, cloneValue(item, depth + 1)])
	);
};

export const freezeValue = (value) => {
	if (Array.isArray(value)) {
		value.forEach(freezeValue);
		return Object.freeze(value);
	}
	if (isPlainObject(value)) {
		Object.values(value).forEach(freezeValue);
		return Object.freeze(value);
	}
	return value;
};

export const cloneFrozen = (value) => freezeValue(cloneValue(value));
