import assert from 'node:assert/strict';
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { pathToFileURL } from 'node:url';
import { after, before, test } from 'node:test';

const shellSource = new URL('../../assets/js/design/shell/index.js', import.meta.url);
const shellCss = new URL('../../assets/css/design/editor-shell.css', import.meta.url);
let tempDirectory;
let createDesignerShell;

class FakeClassList {
	constructor() {
		this.values = new Set();
	}
	add(...values) {
		values.forEach((value) => this.values.add(value));
	}
	remove(...values) {
		values.forEach((value) => this.values.delete(value));
	}
	contains(value) {
		return this.values.has(value);
	}
	toggle(value, force) {
		if (force === true) this.values.add(value);
		else if (force === false) this.values.delete(value);
		else if (this.values.has(value)) this.values.delete(value);
		else this.values.add(value);
		return this.values.has(value);
	}
}

class FakeElement extends EventTarget {
	constructor(ownerDocument = null, tagName = 'DIV') {
		super();
		this.ownerDocument = ownerDocument;
		this.tagName = tagName;
		this.dataset = {};
		this.attributes = new Map();
		this.classList = new FakeClassList();
		this.children = [];
		this.parentElement = null;
		this.hidden = false;
		this.disabled = false;
		this.isConnected = true;
		this.tabIndex = 0;
		this._focusables = null;
	}
	append(...children) {
		children.forEach((child) => {
			child.parentElement = this;
			this.children.push(child);
		});
	}
	setAttribute(name, value) {
		this.attributes.set(name, String(value));
		if (name === 'tabindex') this.tabIndex = Number(value);
	}
	getAttribute(name) {
		return this.attributes.has(name) ? this.attributes.get(name) : null;
	}
	hasAttribute(name) {
		return this.attributes.has(name);
	}
	removeAttribute(name) {
		this.attributes.delete(name);
	}
	focus() {
		if (this.ownerDocument) this.ownerDocument.activeElement = this;
	}
	contains(candidate) {
		if (candidate === this) return true;
		return this.children.some((child) => child.contains(candidate));
	}
	closest(selector) {
		if (selector !== '[hidden]') return null;
		let current = this;
		while (current) {
			if (current.hidden === true) return current;
			current = current.parentElement;
		}
		return null;
	}
	querySelector(selector) {
		return this.querySelectorAll(selector)[0] ?? null;
	}
	querySelectorAll(selector) {
		if (selector.includes('a[href]')) return this._focusables ?? [];
		const matches = [];
		const datasetKey = selector === '[data-cb-design-shell-undo]'
			? 'cbDesignShellUndo'
			: selector === '[data-cb-design-shell-redo]'
				? 'cbDesignShellRedo'
				: selector === '[data-cb-design-shell-fullscreen]'
					? 'cbDesignShellFullscreen'
					: selector === '[data-cb-design-shell-fullscreen-label]'
						? 'cbDesignShellFullscreenLabel'
						: selector === '[data-cb-design-shell-tab]'
							? 'cbDesignShellTab'
							: selector === '[data-cb-design-shell-panel]'
								? 'cbDesignShellPanel'
								: null;
		const visit = (node) => {
			if (selector === 'iframe' && node.tagName === 'IFRAME') matches.push(node);
			else if (datasetKey && Object.hasOwn(node.dataset, datasetKey)) matches.push(node);
			node.children.forEach(visit);
		};
		this.children.forEach(visit);
		return matches;
	}
}

class FakeButton extends FakeElement {
	constructor(ownerDocument = null) {
		super(ownerDocument, 'BUTTON');
	}
}

class FakeDocument extends EventTarget {
	constructor() {
		super();
		this.activeElement = null;
		this.documentElement = new FakeElement(this, 'HTML');
	}
}

class FakeFrame extends FakeElement {
	constructor(ownerDocument = null) {
		super(ownerDocument, 'IFRAME');
		this.contentDocument = new FakeDocument();
	}
}

class FakeCustomEvent extends Event {
	constructor(type, options = {}) {
		super(type, options);
		this.detail = options.detail;
	}
}

const keyEvent = (key, { shiftKey = false } = {}) => {
	const event = new Event('keydown', { cancelable: true });
	Object.defineProperties(event, {
		key: { value: key },
		shiftKey: { value: shiftKey },
	});
	return event;
};

const buildShell = ({ withControl = true, withFrame = false } = {}) => {
	const root = new FakeElement(document);
	let fullscreen = null;
	let label = null;
	const secondary = new FakeButton(document);
	const frame = withFrame ? new FakeFrame(document) : null;

	if (withControl) {
		fullscreen = new FakeButton(document);
		fullscreen.dataset.cbDesignShellFullscreen = '';
		fullscreen.dataset.cbDesignShellFullscreenEnterLabel = 'Enter focus mode';
		fullscreen.dataset.cbDesignShellFullscreenExitLabel = 'Exit focus mode';
		label = new FakeElement(document);
		label.dataset.cbDesignShellFullscreenLabel = '';
		label.textContent = 'Enter focus mode';
		fullscreen.append(label);
		root.append(fullscreen, secondary);
		if (frame) root.append(frame);
		root._focusables = frame ? [fullscreen, secondary, frame] : [fullscreen, secondary];
	} else {
		root.append(secondary);
		if (frame) root.append(frame);
		root._focusables = frame ? [frame] : [];
	}

	return { root, fullscreen, label, secondary, frame };
};

before(async () => {
	tempDirectory = await mkdtemp(join(tmpdir(), 'cb-design-shell-fullscreen-'));
	const source = await readFile(shellSource, 'utf8');
	const modulePath = join(tempDirectory, 'shell.mjs');
	await writeFile(modulePath, source);

	globalThis.document = new FakeDocument();
	globalThis.Element = FakeElement;
	globalThis.HTMLButtonElement = FakeButton;
	globalThis.CustomEvent = FakeCustomEvent;

	({ createDesignerShell } = await import(pathToFileURL(modulePath).href));
});

after(async () => {
	delete globalThis.document;
	delete globalThis.Element;
	delete globalThis.HTMLButtonElement;
	delete globalThis.CustomEvent;
	if (tempDirectory) await rm(tempDirectory, { recursive: true, force: true });
});

test('shared shell exposes transient fullscreen controller state without persistence', () => {
	const outside = new FakeButton(document);
	outside.focus();
	const { root, fullscreen, label } = buildShell();
	const changes = [];
	root.addEventListener('cb:design-shell:fullscreenchange', (event) => changes.push(event.detail.fullscreen));
	const shell = createDesignerShell(root);

	assert.equal(typeof shell.isFullscreen, 'function');
	assert.equal(typeof shell.enterFullscreen, 'function');
	assert.equal(typeof shell.exitFullscreen, 'function');
	assert.equal(typeof shell.toggleFullscreen, 'function');
	assert.equal(shell.isFullscreen(), false);
	assert.equal(fullscreen.getAttribute('aria-pressed'), 'false');
	assert.equal(fullscreen.getAttribute('aria-label'), 'Enter focus mode');

	assert.equal(shell.enterFullscreen(), true);
	assert.equal(shell.enterFullscreen(), false);
	assert.equal(shell.isFullscreen(), true);
	assert.equal(root.classList.contains('is-fullscreen'), true);
	assert.equal(document.documentElement.classList.contains('cb-core-design-shell-focus-mode'), true);
	assert.equal(fullscreen.getAttribute('aria-pressed'), 'true');
	assert.equal(fullscreen.getAttribute('aria-label'), 'Exit focus mode');
	assert.equal(label.textContent, 'Exit focus mode');
	assert.equal(document.activeElement, fullscreen);
	assert.deepEqual(changes, [true]);

	const escape = keyEvent('Escape');
	document.dispatchEvent(escape);
	assert.equal(escape.defaultPrevented, true);
	assert.equal(shell.isFullscreen(), false);
	assert.equal(root.classList.contains('is-fullscreen'), false);
	assert.equal(document.documentElement.classList.contains('cb-core-design-shell-focus-mode'), false);
	assert.equal(fullscreen.getAttribute('aria-pressed'), 'false');
	assert.equal(fullscreen.getAttribute('aria-label'), 'Enter focus mode');
	assert.equal(label.textContent, 'Enter focus mode');
	assert.equal(document.activeElement, outside);
	assert.deepEqual(changes, [true, false]);
});

test('fullscreen control toggles state and keyboard focus stays inside the visible shell', () => {
	const { root, fullscreen, secondary } = buildShell();
	const shell = createDesignerShell(root);

	fullscreen.dispatchEvent(new Event('click'));
	assert.equal(shell.isFullscreen(), true);
	secondary.focus();
	const tab = keyEvent('Tab');
	document.dispatchEvent(tab);
	assert.equal(tab.defaultPrevented, true);
	assert.equal(document.activeElement, fullscreen);

	const shiftTab = keyEvent('Tab', { shiftKey: true });
	document.dispatchEvent(shiftTab);
	assert.equal(shiftTab.defaultPrevented, true);
	assert.equal(document.activeElement, secondary);

	assert.equal(shell.toggleFullscreen(), true);
	assert.equal(shell.isFullscreen(), false);
});

test('fullscreen redirects focus that moves outside the visible shell', () => {
	const outside = new FakeButton(document);
	const { root, fullscreen } = buildShell();
	const shell = createDesignerShell(root);

	fullscreen.focus();
	assert.equal(shell.enterFullscreen(), true);
	outside.focus();
	document.dispatchEvent(new Event('focusin'));
	assert.equal(document.activeElement, fullscreen);
	assert.equal(shell.exitFullscreen(), true);
});

test('Escape inside a same-origin descendant iframe exits shared fullscreen state', () => {
	const { root, fullscreen, frame } = buildShell({ withFrame: true });
	const shell = createDesignerShell(root);

	fullscreen.focus();
	assert.equal(shell.enterFullscreen(), true);
	const escape = keyEvent('Escape');
	frame.contentDocument.dispatchEvent(escape);
	assert.equal(escape.defaultPrevented, true);
	assert.equal(shell.isFullscreen(), false);
	assert.equal(document.documentElement.classList.contains('cb-core-design-shell-focus-mode'), false);
	assert.equal(document.activeElement, fullscreen);
});

test('only one Designer Shell owns fullscreen state and document scroll lock at a time', () => {
	const first = buildShell();
	const second = buildShell();
	const firstShell = createDesignerShell(first.root);
	const secondShell = createDesignerShell(second.root);
	const firstChanges = [];
	first.root.addEventListener('cb:design-shell:fullscreenchange', (event) => firstChanges.push(event.detail.fullscreen));

	assert.equal(firstShell.enterFullscreen(), true);
	assert.equal(secondShell.enterFullscreen(), true);
	assert.equal(firstShell.isFullscreen(), false);
	assert.equal(secondShell.isFullscreen(), true);
	assert.deepEqual(firstChanges, [true, false]);
	assert.equal(document.documentElement.classList.contains('cb-core-design-shell-focus-mode'), true);

	secondShell.exitFullscreen();
	assert.equal(document.documentElement.classList.contains('cb-core-design-shell-focus-mode'), false);
});

test('programmatic fullscreen works without a toolbar control and restores temporary focusability', () => {
	const outside = new FakeButton(document);
	outside.focus();
	const { root } = buildShell({ withControl: false });
	const shell = createDesignerShell(root);

	assert.equal(root.hasAttribute('tabindex'), false);
	assert.equal(shell.enterFullscreen(), true);
	assert.equal(root.hasAttribute('tabindex'), true);
	assert.equal(root.getAttribute('tabindex'), '-1');
	assert.equal(document.activeElement, root);
	assert.equal(shell.exitFullscreen(), true);
	assert.equal(root.hasAttribute('tabindex'), false);
	assert.equal(document.activeElement, outside);
});

test('shared fullscreen CSS owns fixed viewport composition and document scroll lock', async () => {
	const css = await readFile(shellCss, 'utf8');
	assert.match(css, /html\.cb-core-design-shell-focus-mode[\s\S]*overflow:\s*hidden/);
	assert.match(css, /\.cb-core-design-shell\.is-fullscreen\s*\{[\s\S]*position:\s*fixed/);
	assert.match(css, /\.cb-core-design-shell\.is-fullscreen\s*\{[\s\S]*inset:\s*0/);
	assert.match(css, /\.cb-core-design-shell\.is-fullscreen\s*\{[\s\S]*height:\s*100dvh/);
	assert.match(css, /\.cb-core-design-shell\.is-fullscreen\s*>\s*\.cb-core-design-shell__workspace/);
	assert.match(css, /palette--tabbed\s*>\s*\.cb-core-design-shell__panel:not\(\[hidden\]\)[\s\S]*overflow:\s*auto/);
});
