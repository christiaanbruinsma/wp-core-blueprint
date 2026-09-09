import { cloneValue } from './value.js';
import { locationForPath, nodeAt } from './tree.js';

export class InspectorState {
	#activeSection = null;
	#expanded = new Set();

	activeSection() {
		return this.#activeSection;
	}

	setActiveSection(section) {
		this.#activeSection = section === null ? null : String(section || '').trim() || null;
	}

	setExpanded(section, expanded) {
		const id = String(section || '').trim();
		if (!id) return false;
		if (expanded) this.#expanded.add(id);
		else this.#expanded.delete(id);
		return true;
	}

	isExpanded(section) {
		return this.#expanded.has(String(section || '').trim());
	}

	snapshot() {
		return { activeSection: this.#activeSection, expanded: [...this.#expanded] };
	}

	restore(snapshot) {
		if (!snapshot || typeof snapshot !== 'object') throw new TypeError('Invalid inspector snapshot.');
		this.#activeSection = typeof snapshot.activeSection === 'string' && snapshot.activeSection ? snapshot.activeSection : null;
		this.#expanded = new Set(Array.isArray(snapshot.expanded) ? snapshot.expanded.filter((item) => typeof item === 'string' && item) : []);
	}

	toJSON() {
		throw new TypeError('InspectorState is editor-session state and must not be persisted in DesignProject.');
	}
}

export const resolveInspectorTarget = (root, selection) => {
	const paths = selection?.paths?.() ?? [];
	const entries = paths
		.map((path) => ({ path, node: nodeAt(root, path) }))
		.filter((entry) => entry.node !== null)
		.map((entry) => ({ path: [...entry.path], node: cloneValue(entry.node) }));

	if (!entries.length) return Object.freeze({ kind: 'none', entries: [] });
	if (entries.length === 1) return Object.freeze({ kind: 'single', entries });
	return Object.freeze({ kind: 'multiple', entries });
};

export const buildInspectorContext = (root, selection, feedback = null) => {
	const target = resolveInspectorTarget(root, selection);
	const diagnostics = target.entries.flatMap(({ path }) => (
		feedback?.forLocation?.(locationForPath(path), { includeDescendants: true }) ?? []
	));
	return Object.freeze({ target, diagnostics });
};
