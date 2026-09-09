import { resolveInspectorTarget } from '../../core/index.js';
import { fixedFrameFromNode, fixedPageFromRoot } from './geometry.js';

export const buildFixedInspectorContext = (root, selection) => {
	const page = fixedPageFromRoot(root);
	const target = resolveInspectorTarget(root, selection);
	const frames = target.entries.map(({ path, node }) => ({
		path: [...path],
		frame: fixedFrameFromNode(node),
	}));
	return Object.freeze({ target, page, frames });
};
