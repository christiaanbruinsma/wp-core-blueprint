import assert from 'node:assert/strict';
import { readFile, readdir } from 'node:fs/promises';
import test from 'node:test';
import './design-editor-public.test.mjs';
import './design-mail-profile.test.mjs';
import './design-motion.test.mjs';

const sourceDirectory = new URL('../../assets/js/design/core/', import.meta.url);
const assetsBoundary = new URL('../../src/Design/Editor/Assets.php', import.meta.url);
const publicEditorBoundary = new URL('../../assets/js/design/editor.js', import.meta.url);

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

test('public Designer assets and Motion dependency use independent content revisions', async () => {
	const [assets, editor] = await Promise.all([
		readFile(assetsBoundary, 'utf8'),
		readFile(publicEditorBoundary, 'utf8'),
	]);
	assert.match(assets, /hash_file\(\s*'sha256'\s*,\s*\$path\s*\)/);
	assert.match(assets, /asset_version\(\s*'assets\/js\/design\/editor\.js'\s*\)/);
	assert.match(assets, /asset_version\(\s*'assets\/js\/design\/core\/motion\.js'\s*\)/);
	assert.match(assets, /asset_version\(\s*'assets\/js\/features\/designer-launch\.js'\s*\)/);
	assert.match(assets, /asset_version\(\s*'assets\/css\/design\/editor-shell\.css'\s*\)/);
	assert.match(assets, /wp_register_script_module\([\s\S]*self::MOTION_MODULE_ID[\s\S]*motion\.js/);
	assert.match(assets, /wp_enqueue_script_module\([\s\S]*self::MODULE_ID[\s\S]*\[ self::MOTION_MODULE_ID \]/);
	assert.match(editor, /from '@cb-core\/design-motion'/);
	assert.doesNotMatch(editor, /DESIGNER_MOTION_DEFAULTS,[\s\S]*from '\.\/core\/index\.js'/);
	assert.match(assets, /CB_CORE_VERSION\s*\.\s*'-'\s*\.\s*substr\(\s*\$hash/);
});
