import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

import {
	MAIL_DESIGN_TYPE,
	MAIL_ROOT_TYPE,
	MAIL_WIDTH_DEFAULT,
	normalizeMailLayout,
	validateProject,
} from '../../assets/js/design/mail/index.js';

const project = () => ({
	schema_version: 0,
	design_type: MAIL_DESIGN_TYPE,
	root: {
		type: MAIL_ROOT_TYPE,
		provider: 'core',
		properties: {
			layout: {
				width: 600,
				background: '#f3f4f6',
				contentBackground: '#ffffff',
				fontFamily: 'Arial, Helvetica, sans-serif',
				textColor: '#1f2937',
				accentColor: '#2563eb',
			},
		},
		children: [{
			type: 'mail.section',
			provider: 'core',
			properties: { padding: 32 },
			children: [{
				type: 'mail.text',
				provider: 'core',
				properties: { text: 'Hello' },
				children: [],
			}],
		}],
	},
});

test('mail editor profile accepts canonical declarative mail projects', () => {
	assert.equal(validateProject(project()).design_type, MAIL_DESIGN_TYPE);
});

test('mail editor profile rejects a document project', () => {
	const candidate = project();
	candidate.design_type = 'fixture.flow.document';
	assert.throws(() => validateProject(candidate), /mail-template/);
});

test('mail layout normalizes bounded email width independently of document geometry', () => {
	assert.equal(normalizeMailLayout({}).width, MAIL_WIDTH_DEFAULT);
	assert.equal(normalizeMailLayout({ width: 1200 }).width, 800);
	assert.equal(normalizeMailLayout({ width: 200 }).width, 320);
});

test('public editor delegates validation to profile APIs and exposes Mail without profile-specific conditionals', async () => {
	const source = await readFile(new URL('../../assets/js/design/editor.js', import.meta.url), 'utf8');
	assert.match(source, /['"]mail['"]\s*:\s*Object\.freeze/);
	assert.match(source, /profile\.api\.validateProject/);
	assert.doesNotMatch(source, /profile\.id\s*===\s*['"]document-(?:fixed|flow)['"]/);
});

test('mail designer uses the canonical live preview as its editable canvas', async () => {
	const source = await readFile(new URL('../../assets/js/features/mail-designer.js', import.meta.url), 'utf8');
	assert.match(source, /\[data-cb-mail-editor-node\]/);
	assert.match(source, /session\.editorState\.selection\.select/);
	assert.match(source, /data-cb-mail-structure/);
	assert.match(source, /bindPreviewInteractions/);
	assert.doesNotMatch(source, /\[data-cb-mail-canvas\]/);
});

test('mail designer template exposes one visual canvas with Email, Structure and Inspector panels', async () => {
	const source = await readFile(new URL('../../templates/mail-designer.php', import.meta.url), 'utf8');
	assert.match(source, /data-cb-mail-preview-frame/);
	assert.match(source, /data-cb-mail-side-tab="email"/);
	assert.match(source, /data-cb-mail-side-tab="structure"/);
	assert.match(source, /data-cb-mail-side-tab="inspector"/);
	assert.match(source, /sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"/);
	assert.doesNotMatch(source, /cb-core-mail-designer__preview-section/);
	assert.doesNotMatch(source, /data-cb-mail-canvas/);
});
