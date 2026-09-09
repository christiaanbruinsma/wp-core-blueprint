import { nodeAt, normalizePath } from '../../core/index.js';
import { fixedFrameFromNode, fixedPageFromRoot, frameWithinPage } from './geometry.js';

export const fixedTreeEntry = (root, path) => {
	const target = normalizePath(path);
	const node = nodeAt(root, target);
	if (!node) return null;
	if (target.length === 0) {
		return Object.freeze({ path: target, node, page: fixedPageFromRoot(root), frame: null });
	}
	const frame = fixedFrameFromNode(node);
	return Object.freeze({ path: target, node, page: fixedPageFromRoot(root), frame, withinPage: frameWithinPage(frame, fixedPageFromRoot(root)) });
};
