import { nodeAt } from '../../core/tree.js';
import { normalizeFlowHints } from './layout.js';

export const buildFlowInspectorContext = (root, selection) => {
	const paths = selection?.paths?.() ?? [];
	if (paths.length !== 1) return Object.freeze({ kind: paths.length ? 'multiple' : 'none', path: null, hints: null });
	const path = paths[0];
	const node = nodeAt(root, path);
	if (!node) return Object.freeze({ kind: 'none', path: null, hints: null });
	const raw = node.properties?.flow ?? {};
	return Object.freeze({ kind: 'single', path: [...path], hints: normalizeFlowHints(raw) });
};
