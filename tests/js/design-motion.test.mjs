import assert from 'node:assert/strict';
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { after, before, test } from 'node:test';

const motionSource = new URL('../../assets/js/design/core/motion.js', import.meta.url);
const publicEditorSource = new URL('../../assets/js/design/editor.js', import.meta.url);
let tempDirectory;
let motion;

class FakeMotionNode {
	constructor(key, left, top) {
		this.dataset = { cbDesignMotionKey: key };
		this.left = left;
		this.top = top;
		this.animations = [];
	}

	getAttribute(name) {
		return name === 'data-cb-design-motion-key' ? this.dataset.cbDesignMotionKey : null;
	}

	getBoundingClientRect() {
		return { left: this.left, top: this.top };
	}

	animate(keyframes, options) {
		const animation = {
			keyframes,
			options,
			cancelled: false,
			cancel() {
				this.cancelled = true;
			},
			finished: new Promise(() => {}),
		};
		this.animations.push(animation);
		return animation;
	}
}

class FakeMotionRoot {
	constructor(nodes) {
		this.nodes = nodes;
	}

	querySelectorAll() {
		return this.nodes;
	}
}

before(async () => {
	tempDirectory = await mkdtemp(join(tmpdir(), 'cb-design-motion-'));
	const source = await readFile(motionSource, 'utf8');
	const modulePath = join(tempDirectory, 'motion.mjs');
	await writeFile(modulePath, source);
	motion = await import(pathToFileURL(modulePath).href);
});

after(async () => {
	if (tempDirectory) await rm(tempDirectory, { recursive: true, force: true });
});

test('Designer Motion animates stable layout displacement without owning consumer semantics', () => {
	const first = new FakeMotionNode('action:first', 10, 20);
	const second = new FakeMotionNode('action:second', 10, 120);
	const root = new FakeMotionRoot([first, second]);
	const result = motion.animateLayoutChange(root, () => {
		first.top = 120;
		second.top = 20;
		return 'consumer-result';
	});

	assert.equal(result, 'consumer-result');
	assert.equal(first.animations.length, 1);
	assert.equal(second.animations.length, 1);
	assert.deepEqual(first.animations[0].keyframes, [
		{ translate: '0px -100px' },
		{ translate: '0px 0px' },
	]);
	assert.deepEqual(second.animations[0].keyframes, [
		{ translate: '0px 100px' },
		{ translate: '0px 0px' },
	]);
	assert.equal(first.animations[0].options.duration, 170);
	assert.equal(first.animations[0].options.easing, 'cubic-bezier(.2, .8, .2, 1)');
	assert.equal(first.animations[0].options.fill, 'none');
});

test('Designer Motion honors reduced motion while applying the consumer mutation immediately', () => {
	const previousWindow = globalThis.window;
	globalThis.window = { matchMedia: () => ({ matches: true }) };
	try {
		const item = new FakeMotionNode('state:one', 0, 0);
		const root = new FakeMotionRoot([item]);
		motion.animateLayoutChange(root, () => {
			item.top = 50;
		});
		assert.equal(item.top, 50);
		assert.equal(item.animations.length, 0);
	} finally {
		if (previousWindow === undefined) delete globalThis.window;
		else globalThis.window = previousWindow;
	}
});

test('Designer Motion fails closed on duplicate stable keys before mutating consumer state', () => {
	const root = new FakeMotionRoot([
		new FakeMotionNode('duplicate', 0, 0),
		new FakeMotionNode('duplicate', 0, 50),
	]);
	let mutated = false;
	assert.throws(
		() => motion.animateLayoutChange(root, () => { mutated = true; }),
		/unique stable keys/
	);
	assert.equal(mutated, false);
});

test('Designer Motion cancels an active transition before measuring a new command', () => {
	const item = new FakeMotionNode('condition:one', 0, 0);
	const root = new FakeMotionRoot([item]);
	motion.animateLayoutChange(root, () => { item.top = 40; });
	const firstAnimation = item.animations[0];
	motion.animateLayoutChange(root, () => { item.top = 80; });
	assert.equal(firstAnimation.cancelled, true);
	assert.equal(item.animations.length, 2);
});

test('public Design Editor facade exports Designer Motion as named and window API contracts', async () => {
	const source = await readFile(publicEditorSource, 'utf8');
	assert.match(source, /animateLayoutChange/);
	assert.match(source, /const motion = Object\.freeze\(\{/);
	assert.match(source, /motion,\s*\n\tshell:/);
	assert.match(source, /keyAttribute:\s*DESIGNER_MOTION_KEY_ATTRIBUTE/);
});
