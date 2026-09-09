import { cloneValue, nodeAt, normalizePath, setNodeProperty } from '../../core/index.js';
import {
	fixedFrameFromNode,
	fixedPageFromRoot,
	frameWithinPage,
	normalizeFrame,
	resizeFrame,
	translateFrame,
} from './geometry.js';

const editablePath = (path) => {
	const normalized = normalizePath(path);
	if (normalized.length === 0) throw new RangeError('The Fixed document root is not an element frame.');
	return normalized;
};

const command = (label, apply) => Object.freeze({ label, apply });

const replaceFrame = (project, path, frame) => ({
	...cloneValue(project),
	root: setNodeProperty(project.root, path, ['frame'], frame),
});

export const setFixedFrameCommand = (path, frame) => {
	const target = editablePath(path);
	const nextFrame = normalizeFrame(frame);
	return command('fixed-set-frame', (project) => {
		const page = fixedPageFromRoot(project.root);
		if (!nodeAt(project.root, target)) throw new RangeError('Unable to edit an unknown Fixed node.');
		if (!frameWithinPage(nextFrame, page)) throw new RangeError('Fixed frame must remain within the page.');
		return { project: replaceFrame(project, target, nextFrame) };
	});
};

export const moveFixedNodeCommand = (path, deltaX, deltaY) => {
	const target = editablePath(path);
	return command('fixed-move-node', (project) => {
		const page = fixedPageFromRoot(project.root);
		const node = nodeAt(project.root, target);
		if (!node) throw new RangeError('Unable to move an unknown Fixed node.');
		return { project: replaceFrame(project, target, translateFrame(fixedFrameFromNode(node), deltaX, deltaY, page)) };
	});
};

export const resizeFixedNodeCommand = (path, deltaWidth, deltaHeight, options = {}) => {
	const target = editablePath(path);
	return command('fixed-resize-node', (project) => {
		const page = fixedPageFromRoot(project.root);
		const node = nodeAt(project.root, target);
		if (!node) throw new RangeError('Unable to resize an unknown Fixed node.');
		return {
			project: replaceFrame(
				project,
				target,
				resizeFrame(fixedFrameFromNode(node), deltaWidth, deltaHeight, page, options)
			),
		};
	});
};
