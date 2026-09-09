import assert from 'node:assert/strict';
import { cp, mkdtemp, readFile, readdir, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { after, before, test } from 'node:test';

const repositoryRoot = fileURLToPath(new URL('../..', import.meta.url));
let tempDirectory;
let fixed;

const copyAsModules = async (sourceDirectory, targetDirectory) => {
	await cp(sourceDirectory, targetDirectory, { recursive: true });
	const walk = async (directory) => {
		for (const entry of await readdir(directory, { withFileTypes: true })) {
			const path = join(directory, entry.name);
			if (entry.isDirectory()) {
				await walk(path);
				continue;
			}
			if (!entry.name.endsWith('.js')) continue;
			const source = await readFile(path, 'utf8');
			const esm = source.replaceAll(/(['"])([^'"]+)\.js\1/g, '$1$2.mjs$1');
			const nextPath = path.replace(/\.js$/, '.mjs');
			await writeFile(nextPath, esm);
		}
	};
	await walk(targetDirectory);
};

before(async () => {
	tempDirectory = await mkdtemp(join(tmpdir(), 'cb-design-fixed-'));
	await copyAsModules(join(repositoryRoot, 'assets/js/design/core'), join(tempDirectory, 'design/core'));
	await copyAsModules(join(repositoryRoot, 'assets/js/design/document/fixed'), join(tempDirectory, 'design/document/fixed'));
	fixed = await import(pathToFileURL(join(tempDirectory, 'design/document/fixed/index.mjs')).href);
});

after(async () => {
	if (tempDirectory) await rm(tempDirectory, { recursive: true, force: true });
});

const node = (type, frame = null, children = []) => ({
	type,
	provider: 'acme.fixed',
	properties: frame ? { frame } : {},
	children,
});

const project = (width = 210, height = 297) => ({
	schema_version: 0,
	design_type: 'acme.fixed.design',
	root: {
		type: 'document',
		provider: 'acme.fixed',
		properties: {
			layout: {
				mode: 'fixed',
				units: 'mm',
				page: { width, height },
			},
		},
		children: [
			node('text', { x: 10, y: 20, width: 80, height: 15 }),
			node('image', { x: 120, y: 30, width: 50, height: 50 }),
		],
	},
});

test('Fixed geometry accepts A4 and arbitrary positive millimetre page sizes', () => {
	assert.deepEqual(fixed.fixedPageFromRoot(project().root), { width: 210, height: 297, units: 'mm' });
	assert.deepEqual(fixed.fixedPageFromRoot(project(100, 150).root), { width: 100, height: 150, units: 'mm' });
	assert.throws(
		() => fixed.normalizePage({ mode: 'fixed', units: 'px', page: { width: 100, height: 150 } }),
		/millimetres/
	);
});

test('drag translation clamps the element frame to the root page', () => {
	const page = fixed.fixedPageFromRoot(project(100, 150).root);
	assert.deepEqual(
		fixed.translateFrame({ x: 80, y: 130, width: 20, height: 20 }, 50, 50, page),
		{ x: 80, y: 130, width: 20, height: 20 }
	);
	assert.deepEqual(
		fixed.translateFrame({ x: 10, y: 10, width: 20, height: 20 }, -50, -50, page),
		{ x: 0, y: 0, width: 20, height: 20 }
	);
});

test('resize clamps at page edges and respects minimum dimensions', () => {
	const page = fixed.fixedPageFromRoot(project(100, 150).root);
	assert.deepEqual(
		fixed.resizeFrame({ x: 70, y: 120, width: 20, height: 20 }, 50, 50, page),
		{ x: 70, y: 120, width: 30, height: 30 }
	);
	assert.deepEqual(
		fixed.resizeFrame({ x: 10, y: 10, width: 20, height: 20 }, -50, -50, page, { minWidth: 5, minHeight: 6 }),
		{ x: 10, y: 10, width: 5, height: 6 }
	);
});

test('Fixed commands are immutable, root-safe and page-bounded', () => {
	const original = project(100, 150);
	const moved = fixed.moveFixedNodeCommand([0], 15, 5).apply(original).project;
	assert.deepEqual(moved.root.children[0].properties.frame, { x: 20, y: 25, width: 80, height: 15 });
	assert.deepEqual(original.root.children[0].properties.frame, { x: 10, y: 20, width: 80, height: 15 });
	assert.throws(() => fixed.moveFixedNodeCommand([], 1, 1), /root is not an element frame/);
	assert.throws(
		() => fixed.setFixedFrameCommand([0], { x: 95, y: 10, width: 10, height: 10 }).apply(original),
		/remain within the page/
	);
});

test('Fixed inspector context layers geometry onto shared selection without persisting editor state', () => {
	const current = project();
	const selection = { paths: () => [[1]] };
	const context = fixed.buildFixedInspectorContext(current.root, selection);
	assert.equal(context.target.kind, 'single');
	assert.deepEqual(context.frames, [{ path: [1], frame: { x: 120, y: 30, width: 50, height: 50 } }]);
	assert.deepEqual(context.page, { width: 210, height: 297, units: 'mm' });
});

test('Fixed tree entries expose root page context and page-relative element frames', () => {
	const root = project().root;
	const rootEntry = fixed.fixedTreeEntry(root, []);
	assert.equal(rootEntry.frame, null);
	assert.deepEqual(rootEntry.page, { width: 210, height: 297, units: 'mm' });
	const child = fixed.fixedTreeEntry(root, [0]);
	assert.deepEqual(child.frame, { x: 10, y: 20, width: 80, height: 15 });
	assert.equal(child.withinPage, true);
});

test('Fixed geometry rejects extra frame keys and non-finite values', () => {
	assert.throws(
		() => fixed.normalizeFrame({ x: 0, y: 0, width: 10, height: 10, rotation: 5 }),
		/Fixed frames/
	);
	assert.throws(
		() => fixed.normalizeFrame({ x: 0, y: 0, width: Number.POSITIVE_INFINITY, height: 10 }),
		/Fixed frames/
	);
});
