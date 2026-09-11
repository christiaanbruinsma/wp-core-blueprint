const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);

export const MAIL_DESIGN_TYPE = 'mail-template';
export const MAIL_ROOT_TYPE = 'mail.root';
export const MAIL_WIDTH_MIN = 320;
export const MAIL_WIDTH_MAX = 800;
export const MAIL_WIDTH_DEFAULT = 600;

const normalizeNumber = (value, fallback, min, max) => {
	const number = Number(value);
	if (!Number.isFinite(number)) return fallback;
	return Math.max(min, Math.min(max, number));
};

export const normalizeMailLayout = (layout = {}) => {
	if (!isObject(layout)) throw new TypeError('Mail layout must be an object.');
	return Object.freeze({
		width: normalizeNumber(layout.width, MAIL_WIDTH_DEFAULT, MAIL_WIDTH_MIN, MAIL_WIDTH_MAX),
		background: String(layout.background || '#f3f4f6'),
		contentBackground: String(layout.contentBackground || '#ffffff'),
		fontFamily: String(layout.fontFamily || 'Arial, Helvetica, sans-serif'),
		textColor: String(layout.textColor || '#1f2937'),
		accentColor: String(layout.accentColor || '#2563eb'),
	});
};

const validateNode = (node, depth = 0) => {
	if (depth > 64) throw new RangeError('Mail design tree exceeds the supported nesting depth.');
	if (!isObject(node)) throw new TypeError('Every mail design node must be an object.');
	if (typeof node.type !== 'string' || node.type === '') throw new TypeError('Mail design nodes require a type.');
	if (typeof node.provider !== 'string' || node.provider === '') throw new TypeError('Mail design nodes require a provider.');
	if (!isObject(node.properties)) throw new TypeError('Mail design node properties must be an object.');
	if (!Array.isArray(node.children)) throw new TypeError('Mail design node children must be an array.');
	node.children.forEach((child) => validateNode(child, depth + 1));
};

export const validateProject = (project) => {
	if (!isObject(project) || project.design_type !== MAIL_DESIGN_TYPE) {
		throw new TypeError(`Mail editor requires design_type ${MAIL_DESIGN_TYPE}.`);
	}
	if (!isObject(project.root) || project.root.type !== MAIL_ROOT_TYPE) {
		throw new TypeError(`Mail editor requires root type ${MAIL_ROOT_TYPE}.`);
	}
	normalizeMailLayout(project.root.properties?.layout);
	validateNode(project.root);
	return project;
};
