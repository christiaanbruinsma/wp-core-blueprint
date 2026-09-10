import { cloneFrozen, cloneValue, isPlainObject } from './value.js';

const PROJECT_KEYS = new Set(['schema_version', 'design_type', 'root']);

const normalizeProject = (project) => {
	if (
		!isPlainObject(project)
		|| Object.keys(project).some((key) => !PROJECT_KEYS.has(key))
		|| !Number.isInteger(project.schema_version)
		|| project.schema_version < 0
		|| typeof project.design_type !== 'string'
		|| project.design_type === ''
		|| !isPlainObject(project.root)
	) {
		throw new TypeError('ProjectState requires a DesignProject-shaped object.');
	}
	return cloneFrozen(project);
};

export class ProjectState {
	#project;
	#revision = 0;
	#listeners = new Set();
	#validate;

	constructor(project, { validate = null } = {}) {
		if (null !== validate && typeof validate !== 'function') {
			throw new TypeError('ProjectState validate must be a function or null.');
		}
		this.#validate = validate;
		this.#project = this.#normalize(project);
	}

	get revision() {
		return this.#revision;
	}

	current() {
		return this.#project;
	}

	snapshot() {
		return cloneValue(this.#project);
	}

	replace(project, { source = 'editor' } = {}) {
		const next = this.#normalize(project);
		this.#project = next;
		this.#revision += 1;
		const event = Object.freeze({ revision: this.#revision, source: String(source || 'editor') });
		this.#listeners.forEach((listener) => listener(event, this.#project));
		return this.#project;
	}

	subscribe(listener) {
		if (typeof listener !== 'function') throw new TypeError('ProjectState listeners must be functions.');
		this.#listeners.add(listener);
		return () => this.#listeners.delete(listener);
	}

	#normalize(project) {
		const normalized = normalizeProject(project);
		if (this.#validate) this.#validate(normalized);
		return normalized;
	}
}
