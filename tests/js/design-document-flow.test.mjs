import test from 'node:test';
import assert from 'node:assert/strict';

import { reorderNodeCommand } from '../../assets/js/design/core/commands.js';
import { SelectionState } from '../../assets/js/design/core/selection.js';
import {
	buildFlowInspectorContext,
	normalizeFlowHints,
	normalizeFlowLayout,
	setFlowHintsCommand,
} from '../../assets/js/design/document/flow/index.js';

const node = (type, properties = {}, children = []) => ({ type, provider: 'fixture.flow', properties, children });
const project = () => ({
	schema_version: 0,
	design_type: 'fixture.flow.document',
	root: node('document', {
		layout: { mode: 'flow', units: 'mm', page: { width: 210, height: 297 }, margins: { top: 12, right: 12, bottom: 15, left: 12 } },
	}, [node('text', {}, []), node('table', {}, [])]),
});

test('Flow layout normalizes explicit page and margins', () => {
	const layout = normalizeFlowLayout(project().root.properties.layout);
	assert.equal(layout.page.width, 210);
	assert.equal(layout.margins.bottom, 15);
	assert.throws(() => normalizeFlowLayout({ ...project().root.properties.layout, rogue: true }), /contract/);
	assert.throws(() => normalizeFlowLayout({ ...project().root.properties.layout, margins: { top: 150, right: 12, bottom: 150, left: 12 } }), /content space/);
});

test('Flow hints are bounded and typed', () => {
	assert.deepEqual(normalizeFlowHints({ space_before: 4, keep_together: true }), {
		space_before: 4, space_after: 0, break_before: false, break_after: false, keep_together: true,
	});
	assert.throws(() => normalizeFlowHints({ break_before: 1 }), /boolean/);
	assert.throws(() => normalizeFlowHints({ frame: 1 }), /unsupported/);
});

test('Flow hint command reuses shared immutable property command', () => {
	const current = project();
	const result = setFlowHintsCommand([0], { space_after: 5, keep_together: true }).apply(current);
	assert.equal(current.root.children[0].properties.flow, undefined);
	assert.equal(result.project.root.children[0].properties.flow.space_after, 5);
	assert.equal(result.project.root.children[0].properties.flow.keep_together, true);
});

test('Flow ordering reuses the shared tree reorder command', () => {
	const current = project();
	const result = reorderNodeCommand([], 0, 1).apply(current);
	assert.equal(result.project.root.children[0].type, 'table');
	assert.equal(result.project.root.children[1].type, 'text');
});

test('Flow inspector is session-only selection driven', () => {
	const selection = new SelectionState();
	selection.select([0]);
	const current = project();
	current.root.children[0].properties.flow = { break_before: true };
	const context = buildFlowInspectorContext(current.root, selection);
	assert.equal(context.kind, 'single');
	assert.equal(context.hints.break_before, true);
});
