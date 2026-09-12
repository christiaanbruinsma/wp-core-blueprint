import assert from 'node:assert/strict';
import { readFile, readdir } from 'node:fs/promises';
import test from 'node:test';
import './design-editor-public.test.mjs';
import './design-mail-profile.test.mjs';
import './design-motion.test.mjs';

const sourceDirectory = new URL('../../assets/js/design/core/', import.meta.url);
const assetsBoundary = new URL('../../src/Design/Editor/Assets.php', import.meta.url);

test('shared editor core remains profile-neutral and free of document/PDF geometry dependencies', async () => {
	const entries = await readdir(sourceDirectory, { withFileTypes: true });
	const files = entries.filter((entry) => entry.isFile() && entry.name.endsWith('.js'));
	assert.ok(files.length >= 10);

	for (const file of files) {
		const source = await readFile(new URL(file.name, sourceDirectory), 'utf8');
		const imports = [...source.matchAll(/(?:import|export)\s+[\s\S]*?\sfrom\s+['"]([^'"]+)['"]/g)]
			.map((match) => match[1].toLowerCase());
		for (const specifier of imports) {
			assert.doesNotMatch(
				specifier,
				/(?:profile|document|pdf|fixed|flow|geometry|pagination|paper)/,
				`${file.name} must not import profile/document rendering concerns`
			);
		}
		assert.doesNotMatch(
			source,
			/\b(?:pageWidth|pageHeight|paperSize|pagination|pdfRenderer|fixedProfile|flowProfile)\b/i,
			`${file.name} must not own document-profile geometry`
		);
	}
});

test('public Designer entry assets use content revisions instead of the static RC version alone', async () => {
	const source = await readFile(assetsBoundary, 'utf8');
	assert.match(source, /hash_file\(\s*'sha256'\s*,\s*\$path\s*\)/);
	assert.match(source, /asset_version\(\s*'assets\/js\/design\/editor\.js'\s*\)/);
	assert.match(source, /asset_version\(\s*'assets\/js\/features\/designer-launch\.js'\s*\)/);
	assert.match(source, /asset_version\(\s*'assets\/css\/design\/editor-shell\.css'\s*\)/);
	assert.match(source, /CB_CORE_VERSION\s*\.\s*'-'\s*\.\s*substr\(\s*\$hash/);
});
