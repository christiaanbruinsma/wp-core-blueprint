import { normalizePage } from './geometry.js';

export const validateProject = (project) => {
	const layout = project?.root?.properties?.layout;
	normalizePage(layout);
	return project;
};

export {
	FIXED_LAYOUT_MODE,
	FIXED_UNITS,
	clampFrame,
	fixedFrameFromNode,
	fixedPageFromRoot,
	frameWithinPage,
	normalizeFrame,
	normalizePage,
	resizeFrame,
	translateFrame,
} from './geometry.js';
export { moveFixedNodeCommand, resizeFixedNodeCommand, setFixedFrameCommand } from './commands.js';
export { buildFixedInspectorContext } from './inspector.js';
export { fixedTreeEntry } from './tree.js';
