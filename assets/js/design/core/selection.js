import { nodeAt, normalizePath, pathKey } from './tree.js';

const clonePath = (path) => [...path];

export class SelectionState {
	#paths = [];
	#primary = null;

	paths() {
		return this.#paths.map(clonePath);
	}

	primary() {
		return this.#primary === null ? null : clonePath(this.#primary);
	}

	clear() {
		this.#paths = [];
		this.#primary = null;
	}

	set(paths, primary = null) {
		if (!Array.isArray(paths)) throw new TypeError('Selection paths must be an array.');
		const unique = [];
		const keys = new Set();
		paths.forEach((path) => {
			const normalized = normalizePath(path);
			const key = pathKey(normalized);
			if (!keys.has(key)) {
				keys.add(key);
				unique.push(normalized);
			}
		});
		this.#paths = unique;

		if (primary !== null) {
			const normalizedPrimary = normalizePath(primary);
			this.#primary = keys.has(pathKey(normalizedPrimary)) ? normalizedPrimary : null;
		} else {
			this.#primary = unique.length ? clonePath(unique.at(-1)) : null;
		}
	}

	select(path, { additive = false } = {}) {
		const normalized = normalizePath(path);
		if (!additive) {
			this.#paths = [normalized];
			this.#primary = clonePath(normalized);
			return;
		}
		const key = pathKey(normalized);
		if (!this.#paths.some((candidate) => pathKey(candidate) === key)) this.#paths.push(normalized);
		this.#primary = clonePath(normalized);
	}

	remap(mapper) {
		if (typeof mapper !== 'function') throw new TypeError('Selection remappers must be functions.');
		const primaryKey = this.#primary === null ? null : pathKey(this.#primary);
		const remapped = [];
		let remappedPrimary = null;
		const seen = new Set();

		this.#paths.forEach((path) => {
			const candidate = mapper(clonePath(path));
			if (candidate === null) return;
			const normalized = normalizePath(candidate);
			const key = pathKey(normalized);
			if (!seen.has(key)) {
				seen.add(key);
				remapped.push(normalized);
			}
			if (primaryKey !== null && pathKey(path) === primaryKey) remappedPrimary = normalized;
		});

		this.#paths = remapped;
		this.#primary = remappedPrimary ?? (remapped.length ? clonePath(remapped.at(-1)) : null);
	}

	reconcile(root) {
		this.remap((path) => nodeAt(root, path) ? path : null);
	}

	snapshot() {
		return { paths: this.paths(), primary: this.primary() };
	}

	restore(snapshot) {
		if (!snapshot || typeof snapshot !== 'object') throw new TypeError('Invalid selection snapshot.');
		this.set(snapshot.paths ?? [], snapshot.primary ?? null);
	}

	toJSON() {
		throw new TypeError('SelectionState is editor-session state and must not be persisted in DesignProject.');
	}
}
