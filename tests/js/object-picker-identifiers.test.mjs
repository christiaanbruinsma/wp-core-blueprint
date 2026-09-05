import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

const sourceUrl = new URL('../../assets/js/core/object-picker.js', import.meta.url);
const source = await readFile(sourceUrl, 'utf8');
const productionModule = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`);

const {
	addSelection,
	normalizeIdentifier,
	normalizePickerItem,
	normalizeSelection,
	removeSelection,
} = productionModule;

test('numeric and opaque identifiers normalize to exact strings', () => {
	assert.equal(normalizeIdentifier(13), '13');
	assert.equal(normalizeIdentifier('crm:organization:42'), 'crm:organization:42');
	assert.equal(normalizeIdentifier('  crm:contact:42  '), 'crm:contact:42');
});

test('full string identifiers remain distinct and deduplicate exactly', () => {
	const selected = normalizeSelection([
		{ id: 'crm:contact:42', label: 'Contact 42' },
		{ id: 'crm:organization:42', label: 'Organization 42' },
		{ id: 'crm:contact:42', label: 'Duplicate contact' },
	], true);

	assert.deepEqual(selected.map((item) => item.id), [
		'crm:contact:42',
		'crm:organization:42',
	]);
});

test('single selection keeps only the first valid item', () => {
	const selected = normalizeSelection([
		{ id: '', label: 'Invalid' },
		{ id: 'crm:contact:42', label: 'Contact 42' },
		{ id: 'crm:organization:42', label: 'Organization 42' },
	], false);

	assert.deepEqual(selected.map((item) => item.id), ['crm:contact:42']);
});

test('AJAX-result selection preserves opaque IDs and removal is exact', () => {
	let selected = normalizeSelection([
		{ id: 'crm:contact:42', label: 'Contact 42' },
	], true);

	selected = addSelection(
		selected,
		{ id: 'crm:organization:42', label: 'Organization 42', meta: 'Organization' },
		true
	);
	assert.deepEqual(selected.map((item) => item.id), [
		'crm:contact:42',
		'crm:organization:42',
	]);

	selected = removeSelection(selected, 'crm:contact:42');
	assert.deepEqual(selected.map((item) => item.id), ['crm:organization:42']);
});

test('invalid identifiers fail closed under the production normalizer', () => {
	assert.equal(normalizeIdentifier(''), '');
	assert.equal(normalizeIdentifier('   '), '');
	assert.equal(normalizeIdentifier(false), '');
	assert.equal(normalizeIdentifier(null), '');
	assert.equal(normalizeIdentifier({ id: 13 }), '');
	assert.equal(normalizeIdentifier('contains,comma'), '');
	assert.equal(normalizeIdentifier('a'.repeat(192)), '');
	assert.equal(normalizeIdentifier('é'.repeat(96)), '');
	assert.equal(normalizeIdentifier('a'.repeat(191)), 'a'.repeat(191));
	assert.equal(normalizePickerItem({ id: [] }), null);
});
