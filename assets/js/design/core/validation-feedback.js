const normalizeDiagnostic = (diagnostic) => {
	if (!diagnostic || typeof diagnostic !== 'object' || Array.isArray(diagnostic)) return null;
	const code = typeof diagnostic.code === 'string' ? diagnostic.code.trim() : '';
	const message = typeof diagnostic.message === 'string' ? diagnostic.message.trim() : '';
	const location = typeof diagnostic.location === 'string' ? diagnostic.location.trim() : '';
	return code && message && location ? Object.freeze({ code, message, location }) : null;
};

export class ValidationFeedback {
	#items = [];

	replace(diagnostics) {
		if (!Array.isArray(diagnostics)) throw new TypeError('Validation diagnostics must be an array.');
		this.#items = diagnostics.map(normalizeDiagnostic).filter(Boolean);
	}

	append(diagnostic) {
		const normalized = normalizeDiagnostic(diagnostic);
		if (!normalized) return false;
		this.#items.push(normalized);
		return true;
	}

	clear() {
		this.#items = [];
	}

	all() {
		return this.#items.map((item) => ({ ...item }));
	}

	count() {
		return this.#items.length;
	}

	hasErrors() {
		return this.#items.length > 0;
	}

	forLocation(location, { includeDescendants = true } = {}) {
		const target = String(location || '').trim();
		if (!target) return [];
		return this.#items
			.filter((item) => item.location === target || (includeDescendants && item.location.startsWith(`${target}.`)))
			.map((item) => ({ ...item }));
	}

	toJSON() {
		throw new TypeError('ValidationFeedback is editor-session state and must not be persisted in DesignProject.');
	}
}
