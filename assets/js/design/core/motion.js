const MOTION_KEY_ATTRIBUTE = 'data-cb-design-motion-key';
const MOTION_SELECTOR = `[${MOTION_KEY_ATTRIBUTE}]`;

export const DESIGNER_MOTION_DEFAULTS = Object.freeze({
	selector: MOTION_SELECTOR,
	duration: 170,
	easing: 'cubic-bezier(.2, .8, .2, 1)',
});

export const DESIGNER_MOTION_KEY_ATTRIBUTE = MOTION_KEY_ATTRIBUTE;

const activeAnimations = new WeakMap();

const prefersReducedMotion = () => (
	typeof window !== 'undefined'
	&& typeof window.matchMedia === 'function'
	&& window.matchMedia('(prefers-reduced-motion: reduce)').matches === true
);

const motionKeyFor = (node) => {
	const datasetKey = node?.dataset?.cbDesignMotionKey;
	const attributeKey = typeof node?.getAttribute === 'function'
		? node.getAttribute(MOTION_KEY_ATTRIBUTE)
		: null;
	return String(datasetKey ?? attributeKey ?? '').trim();
};

const rectFor = (node) => {
	if (typeof node?.getBoundingClientRect !== 'function') return null;
	const rect = node.getBoundingClientRect();
	if (!rect || !Number.isFinite(rect.left) || !Number.isFinite(rect.top)) return null;
	return Object.freeze({ left: rect.left, top: rect.top });
};

const cancelActiveAnimation = (node) => {
	const animation = activeAnimations.get(node);
	if (!animation) return;
	try {
		animation.cancel?.();
	} catch (error) {
		// Motion is progressive enhancement; a stale animation must never block a command.
	}
	activeAnimations.delete(node);
};

const collectMotionItems = (root, selector, { cancel = false } = {}) => {
	const items = new Map();
	for (const node of Array.from(root.querySelectorAll(selector))) {
		const key = motionKeyFor(node);
		if (!key) continue;
		if (items.has(key)) {
			throw new RangeError(`Designer Motion requires unique stable keys. Duplicate key: ${key}.`);
		}
		if (cancel) cancelActiveAnimation(node);
		const rect = rectFor(node);
		if (!rect) continue;
		items.set(key, Object.freeze({ node, rect }));
	}
	return items;
};

const normalizeOptions = (options = {}) => {
	const selector = String(options.selector || DESIGNER_MOTION_DEFAULTS.selector).trim();
	if (!selector) throw new TypeError('Designer Motion selector must be a non-empty string.');

	const duration = options.duration === undefined
		? DESIGNER_MOTION_DEFAULTS.duration
		: Number(options.duration);
	if (!Number.isFinite(duration) || duration < 0) {
		throw new RangeError('Designer Motion duration must be a non-negative finite number.');
	}

	const easing = String(options.easing || DESIGNER_MOTION_DEFAULTS.easing).trim();
	if (!easing) throw new TypeError('Designer Motion easing must be a non-empty string.');

	return Object.freeze({ selector, duration, easing });
};

const animateDisplacement = (node, dx, dy, options) => {
	if ((Math.abs(dx) < 0.5 && Math.abs(dy) < 0.5) || typeof node?.animate !== 'function') return;

	const animation = node.animate(
		[
			{ translate: `${dx}px ${dy}px` },
			{ translate: '0px 0px' },
		],
		{
			duration: options.duration,
			easing: options.easing,
			fill: 'none',
		}
	);
	activeAnimations.set(node, animation);

	const cleanup = () => {
		if (activeAnimations.get(node) === animation) activeAnimations.delete(node);
	};
	if (animation?.finished && typeof animation.finished.then === 'function') {
		animation.finished.then(cleanup, cleanup);
	}
};

/**
 * Animate a synchronous consumer-owned layout mutation using stable element keys.
 *
 * Base owns only the visual transition. The consumer owns the model mutation,
 * DOM rendering and the meaning of each keyed item. Motion uses the individual
 * CSS `translate` property so existing consumer `transform` semantics remain
 * untouched.
 *
 * @param {Element|object} root Container exposing querySelectorAll().
 * @param {Function} mutate Synchronous consumer mutation/render callback.
 * @param {object} options Optional selector/duration/easing overrides.
 * @returns {*} The consumer callback result.
 */
export const animateLayoutChange = (root, mutate, options = {}) => {
	if (!root || typeof root.querySelectorAll !== 'function') {
		throw new TypeError('Designer Motion requires a root element with querySelectorAll().');
	}
	if (typeof mutate !== 'function') {
		throw new TypeError('Designer Motion requires a synchronous mutation callback.');
	}

	const normalized = normalizeOptions(options);
	const before = collectMotionItems(root, normalized.selector, { cancel: true });
	const result = mutate();

	if (normalized.duration === 0 || prefersReducedMotion()) return result;

	const after = collectMotionItems(root, normalized.selector);
	for (const [key, current] of after.entries()) {
		const previous = before.get(key);
		if (!previous) continue;
		animateDisplacement(
			current.node,
			previous.rect.left - current.rect.left,
			previous.rect.top - current.rect.top,
			normalized
		);
	}

	return result;
};
