import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

test('Mail save adapter preserves native form fallback and saves in place when browser capabilities exist', async () => {
	const source = await readFile(new URL('../../assets/js/features/mail-designer-save.js', import.meta.url), 'utf8');
	assert.doesNotThrow(() => new Function(source));
	assert.match(source, /form && ajaxUrl && typeof fetch === 'function' && typeof FormData === 'function'/);
	assert.match(source, /form\.addEventListener\('submit'/);
	assert.match(source, /event\.preventDefault\(\)/);
	assert.match(source, /body:\s*new FormData\(form\)/);
	assert.match(source, /credentials:\s*'same-origin'/);
	assert.match(source, /cb:design-shell:savechange/);
	assert.match(source, /announceSaveState\('saving'\)/);
	assert.match(source, /announceSaveState\('saved'/);
	assert.match(source, /announceSaveState\('error'/);
	assert.doesNotMatch(source, /location\.(?:assign|replace|reload)/);
});

test('Mail save adapter makes rapid saves race-safe', async () => {
	const source = await readFile(new URL('../../assets/js/features/mail-designer-save.js', import.meta.url), 'utf8');
	assert.match(source, /saveController\?\.abort\(\)/);
	assert.match(source, /const controller = new AbortController\(\)/);
	assert.match(source, /signal:\s*controller\.signal/);
	assert.match(source, /if \(saveController === controller\) saveController = null/);
});

test('shared Designer chrome owns generic saving, saved and error presentation', async () => {
	const source = await readFile(new URL('../../assets/js/features/designer-launch.js', import.meta.url), 'utf8');
	assert.match(source, /SAVE_EVENT = 'cb:design-shell:savechange'/);
	assert.match(source, /save\.disabled = busy/);
	assert.match(source, /save\.setAttribute\('aria-busy', 'true'\)/);
	assert.match(source, /save\.removeAttribute\('aria-busy'\)/);
	assert.match(source, /status\.classList\.toggle\('is-error', state === 'error'\)/);
	assert.match(source, /cbDesignShellSaveState/);
	assert.doesNotMatch(source, /data-cb-mail-/);
});

test('Mail save transport reuses the canonical server persistence path', async () => {
	const actions = await readFile(new URL('../../src/Mail/Admin/TemplateActions.php', import.meta.url), 'utf8');
	const assets = await readFile(new URL('../../src/Mail/Admin/DesignerAssets.php', import.meta.url), 'utf8');
	assert.match(actions, /wp_ajax_cb_core_mail_template_save/);
	assert.match(actions, /function save_ajax\(\): void/);
	assert.match(actions, /check_ajax_referer\( 'cb_core_mail_template_save' \)/);
	assert.match(actions, /\$result = self::persist\(/);
	assert.equal((actions.match(/TemplateRepository::save\(/g) || []).length, 1);
	assert.match(assets, /SAVE_MODULE_ID/);
	assert.match(assets, /mail-designer-save\.js/);
	assert.match(assets, /\[ self::MODULE_ID \]/);
});
