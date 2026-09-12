import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const root = new URL('../../', import.meta.url);
const read = (path) => readFile(new URL(path, root), 'utf8');

test('Designer Mode keeps collapse controls inside canonical sidebar headers', async () => {
	const [styles, launch] = await Promise.all([
		read('assets/css/design/designer-mode.css'),
		read('assets/js/features/designer-launch.js'),
	]);

	assert.match(launch, /cb-core-design-shell__panel-toggle/);
	assert.match(launch, /cbDesignShellPanelToggle/);
	assert.match(styles, /content:\s*attr\(aria-label\)/);
	assert.match(styles, /--cb-design-sidebar-header-height:\s*44px/);
	assert.match(styles, /grid-template-columns:\s*[\s\S]*var\(--cb-design-left-track\)[\s\S]*minmax\(0,\s*1fr\)[\s\S]*var\(--cb-design-right-track\)/);
	assert.match(styles, /\.cb-core-design-shell__panel-toggle--left[\s\S]*grid-column:\s*1/);
	assert.match(styles, /\.cb-core-design-shell__panel-toggle--right[\s\S]*grid-column:\s*3/);
	assert.match(styles, /--cb-design-left-track:\s*var\(--cb-design-sidebar-header-height\)/);
	assert.match(styles, /--cb-design-right-track:\s*var\(--cb-design-sidebar-header-height\)/);
	assert.doesNotMatch(styles, /\b32px\b/);
	assert.doesNotMatch(styles, /--cb-design-(?:left|right)-track:\s*0px/);
});
