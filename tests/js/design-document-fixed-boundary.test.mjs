import assert from 'node:assert/strict';
import { readFile, readdir } from 'node:fs/promises';
import test from 'node:test';

const fixedDirectory = new URL('../../assets/js/design/document/fixed/', import.meta.url);
const coreDirectory = new URL('../../assets/js/design/core/', import.meta.url);

test('Fixed editor modules may depend on shared core but not Flow or PDF/rendering backends', async () => {
	for (const entry of await readdir(fixedDirectory, { withFileTypes: true })) {
		if (!entry.isFile() || !entry.name.endsWith('.js')) continue;
		const source = await readFile(new URL(entry.name, fixedDirectory), 'utf8');
		assert.doesNotMatch(source, /\b(?:flow|pagination|dompdf|pdfapi|pdfrenderer|renderer_revision)\b/i);
		const imports = [...source.matchAll(/(?:import|export)\s+[\s\S]*?\sfrom\s+['"]([^'"]+)['"]/g)]
			.map((match) => match[1]);
		for (const specifier of imports) {
			assert.ok(
				specifier.startsWith('./') || specifier.startsWith('../../core/'),
				`${entry.name} may only import sibling Fixed modules or shared Design core`
			);
		}
	}
});

test('shared editor core still has no imports from Document Fixed', async () => {
	for (const entry of await readdir(coreDirectory, { withFileTypes: true })) {
		if (!entry.isFile() || !entry.name.endsWith('.js')) continue;
		const source = await readFile(new URL(entry.name, coreDirectory), 'utf8');
		assert.doesNotMatch(source, /design\/document|profile\/document|document\/fixed/i);
	}
});
