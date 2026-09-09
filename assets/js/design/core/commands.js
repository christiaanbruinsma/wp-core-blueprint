import { cloneValue } from './value.js';
import {
	insertChild,
	normalizePath,
	removeNode,
	reorderChild,
	remapPathAfterInsertion,
	remapPathAfterRemoval,
	remapPathAfterReorder,
	setNodeProperty,
} from './tree.js';

const withRoot = (project, root) => ({ ...cloneValue(project), root });

const command = (label, apply) => Object.freeze({
	label,
	apply(project) {
		if (!project || typeof project !== 'object' || Array.isArray(project)) {
			throw new TypeError('Editor commands require a DesignProject-shaped object.');
		}
		return apply(project);
	},
});

export const insertNodeCommand = (parentPath, index, node) => {
	const parent = normalizePath(parentPath);
	const nextNode = cloneValue(node);
	return command('insert-node', (project) => ({
		project: withRoot(project, insertChild(project.root, parent, index, nextNode)),
		remapSelection: (path) => remapPathAfterInsertion(path, parent, index),
		selectPath: [...parent, index],
	}));
};

export const removeNodeCommand = (path) => {
	const target = normalizePath(path);
	return command('remove-node', (project) => ({
		project: withRoot(project, removeNode(project.root, target)),
		remapSelection: (selectedPath) => remapPathAfterRemoval(selectedPath, target),
	}));
};

export const reorderNodeCommand = (parentPath, fromIndex, toIndex) => {
	const parent = normalizePath(parentPath);
	return command('reorder-node', (project) => ({
		project: withRoot(project, reorderChild(project.root, parent, fromIndex, toIndex)),
		remapSelection: (path) => remapPathAfterReorder(path, parent, fromIndex, toIndex),
	}));
};

export const setPropertyCommand = (nodePath, propertyPath, value) => {
	const target = normalizePath(nodePath);
	const properties = Array.isArray(propertyPath) ? [...propertyPath] : propertyPath;
	const nextValue = cloneValue(value);
	return command('set-property', (project) => ({
		project: withRoot(project, setNodeProperty(project.root, target, properties, nextValue)),
	}));
};
