#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def replace_once(relative: str, old: str, new: str) -> None:
    path = ROOT / relative
    source = path.read_text()
    count = source.count(old)
    if count == 0 and new in source:
        print(f"{relative}: already synchronized")
        return
    if count != 1:
        raise RuntimeError(f"{relative}: expected exactly one source snippet, found {count}")
    path.write_text(source.replace(old, new, 1))
    print(f"{relative}: synchronized")


def synchronize_brand_mark() -> None:
    hud_path = ROOT / 'src/HUD/Brand/CoreBlueprint.php'
    mark_path = ROOT / 'src/Brand/CoreBlueprintMark.php'
    hud_source = hud_path.read_text()

    if 'CoreBlueprintMark::svg()' in hud_source:
        if not mark_path.exists():
            raise RuntimeError('HUD delegates to CoreBlueprintMark but the shared mark source is missing')
        print('src/Brand/CoreBlueprintMark.php: already synchronized')
        print('src/HUD/Brand/CoreBlueprint.php: already synchronized')
        return

    method_token = "\tpublic function logo_svg(): string {"
    method_start = hud_source.find(method_token)
    if method_start < 0:
        raise RuntimeError('src/HUD/Brand/CoreBlueprint.php: canonical logo_svg() method not found')

    svg_start = hud_source.find('<svg', method_start)
    svg_end = hud_source.find('\nSVG;', svg_start)
    method_end_token = '\nSVG;\n\t}'
    method_end = hud_source.find(method_end_token, svg_start)
    if svg_start < 0 or svg_end < 0 or method_end < 0:
        raise RuntimeError('src/HUD/Brand/CoreBlueprint.php: canonical static SVG heredoc could not be extracted')
    method_end += len(method_end_token)

    svg = hud_source[svg_start:svg_end]
    if '#00FFDD' not in svg or '#0037FF' not in svg or '#131648' not in svg:
        raise RuntimeError('src/HUD/Brand/CoreBlueprint.php: extracted SVG does not match canonical Core Blueprint palette')

    mark_path.parent.mkdir(parents=True, exist_ok=True)
    mark_path.write_text(
        "<?php\n"
        "declare(strict_types=1);\n"
        "/**\n"
        " * Canonical Core Blueprint brand mark.\n"
        " *\n"
        " * Shared Base-owned source for the static gradient roundel used across\n"
        " * Core Blueprint product chrome. Consumers must reference this class\n"
        " * instead of carrying private copies of the SVG.\n"
        " *\n"
        " * @package Core_Blueprint\n"
        " * @since   1.0.0\n"
        " */\n\n"
        "namespace CB\\Core\\Brand;\n\n"
        "defined( 'ABSPATH' ) || exit;\n\n"
        "final class CoreBlueprintMark {\n\n"
        "\tpublic static function svg(): string {\n"
        "\t\treturn <<<'SVG'\n"
        f"{svg}\n"
        "SVG;\n"
        "\t}\n\n"
        "\t/**\n"
        "\t * Return an embeddable image source without duplicating inline SVG ids.\n"
        "\t */\n"
        "\tpublic static function data_uri(): string {\n"
        "\t\treturn 'data:image/svg+xml;base64,' . base64_encode( self::svg() );\n"
        "\t}\n"
        "}\n"
    )

    replacement = "\tpublic function logo_svg(): string {\n\t\treturn CoreBlueprintMark::svg();\n\t}"
    hud_source = hud_source[:method_start] + replacement + hud_source[method_end:]
    namespace_anchor = "namespace CB\\Core\\HUD\\Brand;\n\n"
    if namespace_anchor not in hud_source:
        raise RuntimeError('src/HUD/Brand/CoreBlueprint.php: namespace anchor not found')
    hud_source = hud_source.replace(
        namespace_anchor,
        namespace_anchor + "use CB\\Core\\Brand\\CoreBlueprintMark;\n\n",
        1,
    )
    hud_source = hud_source.replace(
        ' * Logo: a simple geometric mark referencing Core Blueprint\'s "blueprint\n'
        ' * grid + anchor" identity - three horizontal grid lines crossed by a\n'
        ' * vertical anchor, all in currentColor so the brand\'s palette accent\n'
        ' * applies. Compact 24x24 viewBox so it scales cleanly to any HUD button\n'
        ' * size.\n',
        ' * Logo: the canonical Core Blueprint gradient roundel supplied by the\n'
        ' * shared Base brand boundary. HUD remains a consumer of that source so\n'
        ' * product chrome never carries a private copy of the brand glyph.\n',
        1,
    )
    hud_path.write_text(hud_source)
    print('src/Brand/CoreBlueprintMark.php: synchronized')
    print('src/HUD/Brand/CoreBlueprint.php: synchronized')


replace_once(
    'templates/mail-designer.php',
    "/* translators: WordPress core owns this generic admin UI label in the default text domain. */\n$fullscreen_label = __( 'Fullscreen mode', 'default' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- intentional WordPress platform vocabulary.\n",
    "/* translators: WordPress core owns these generic editor UI labels in the default text domain. */\n$fullscreen_label = __( 'Fullscreen mode', 'default' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- intentional WordPress platform vocabulary.\n$tablet_label = __( 'Tablet', 'default' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- intentional WordPress platform vocabulary.\n",
)

replace_once(
    'templates/mail-designer.php',
    "\t\t\t\t\t\t\t\t<button type=\"button\" class=\"button cb-core-button is-active\" data-cb-mail-viewport=\"desktop\"><?php esc_html_e( 'Desktop', 'core-blueprint' ); ?></button>\n\t\t\t\t\t\t\t\t<button type=\"button\" class=\"button cb-core-button\" data-cb-mail-viewport=\"mobile\"><?php esc_html_e( 'Mobile', 'core-blueprint' ); ?></button>\n",
    "\t\t\t\t\t\t\t\t<button type=\"button\" class=\"button cb-core-button is-active\" data-cb-mail-viewport=\"desktop\" aria-pressed=\"true\"><?php esc_html_e( 'Desktop', 'core-blueprint' ); ?></button>\n\t\t\t\t\t\t\t\t<button type=\"button\" class=\"button cb-core-button\" data-cb-mail-viewport=\"tablet\" aria-pressed=\"false\"><?php echo esc_html( $tablet_label ); ?></button>\n\t\t\t\t\t\t\t\t<button type=\"button\" class=\"button cb-core-button\" data-cb-mail-viewport=\"mobile\" aria-pressed=\"false\"><?php esc_html_e( 'Mobile', 'core-blueprint' ); ?></button>\n",
)

replace_once(
    'assets/js/features/mail-designer.js',
    "\troot.querySelectorAll('[data-cb-mail-viewport]').forEach((button) => {\n\t\tbutton.addEventListener('click', () => {\n\t\t\tconst mobile = button.dataset.cbMailViewport === 'mobile';\n\t\t\tpreviewFrame?.classList.toggle('is-mobile', mobile);\n\t\t\troot.querySelectorAll('[data-cb-mail-viewport]').forEach((candidate) => candidate.classList.toggle('is-active', candidate === button));\n\t\t});\n\t});\n",
    "\troot.querySelectorAll('[data-cb-mail-viewport]').forEach((button) => {\n\t\tbutton.addEventListener('click', () => {\n\t\t\tconst value = String(button.dataset.cbMailViewport || 'desktop');\n\t\t\tpreviewFrame?.classList.toggle('is-mobile', value === 'mobile');\n\t\t\tpreviewFrame?.classList.toggle('is-tablet', value === 'tablet');\n\t\t\troot.querySelectorAll('[data-cb-mail-viewport]').forEach((candidate) => {\n\t\t\t\tconst active = candidate === button;\n\t\t\t\tcandidate.classList.toggle('is-active', active);\n\t\t\t\tcandidate.setAttribute('aria-pressed', active ? 'true' : 'false');\n\t\t\t});\n\t\t});\n\t});\n",
)

replace_once(
    'assets/js/features/designer-launch.js',
    "\t\tconst desktop = viewportGroup.querySelector('[data-cb-mail-viewport=\"desktop\"]');\n\t\tconst mobile = viewportGroup.querySelector('[data-cb-mail-viewport=\"mobile\"]');\n\t\tlet tablet = viewportGroup.querySelector('[data-cb-mail-viewport=\"tablet\"]');\n\t\tif (!tablet) {\n\t\t\ttablet = document.createElement('button');\n\t\t\ttablet.type = 'button';\n\t\t\ttablet.className = 'button cb-core-button';\n\t\t\ttablet.dataset.cbMailViewport = 'tablet';\n\t\t\ttablet.textContent = String(config.tabletLabel || 'Tablet');\n\t\t}\n\n\t\tconst desktopLabel = String(desktop?.textContent || 'Desktop').trim();\n\t\tconst mobileLabel = String(mobile?.textContent || 'Mobile').trim();\n\t\tconst tabletLabel = String(config.tabletLabel || tablet.textContent || 'Tablet').trim();\n\t\ticonize(mobile, 'mobile', mobileLabel);\n\t\ticonize(tablet, 'tablet', tabletLabel);\n\t\ticonize(desktop, 'desktop', desktopLabel);\n\t\tviewportGroup.replaceChildren(...[mobile, tablet, desktop].filter(Boolean));\n\n\t\tconst viewportButtons = Array.from(viewportGroup.querySelectorAll('[data-cb-mail-viewport]'));\n\t\tconst setViewportState = (value, activeButton) => {\n\t\t\tpreviewFrame?.classList.toggle('is-mobile', value === 'mobile');\n\t\t\tpreviewFrame?.classList.toggle('is-tablet', value === 'tablet');\n\t\t\tviewportButtons.forEach((button) => {\n\t\t\t\tconst active = button === activeButton;\n\t\t\t\tbutton.classList.toggle('is-active', active);\n\t\t\t\tbutton.setAttribute('aria-pressed', active ? 'true' : 'false');\n\t\t\t});\n\t\t};\n\t\tviewportButtons.forEach((button) => {\n\t\t\tbutton.addEventListener('click', () => {\n\t\t\t\tsetViewportState(String(button.dataset.cbMailViewport || 'desktop'), button);\n\t\t\t});\n\t\t});\n\t\tconst activeViewport = viewportButtons.find((button) => button.classList.contains('is-active')) || desktop || viewportButtons.at(-1);\n\t\tif (activeViewport) setViewportState(String(activeViewport.dataset.cbMailViewport || 'desktop'), activeViewport);\n",
    "\t\tconst desktop = viewportGroup.querySelector('[data-cb-mail-viewport=\"desktop\"]');\n\t\tconst tablet = viewportGroup.querySelector('[data-cb-mail-viewport=\"tablet\"]');\n\t\tconst mobile = viewportGroup.querySelector('[data-cb-mail-viewport=\"mobile\"]');\n\t\tif (!desktop || !tablet || !mobile) return;\n\n\t\tconst desktopLabel = String(desktop.textContent || 'Desktop').trim();\n\t\tconst tabletLabel = String(tablet.textContent || 'Tablet').trim();\n\t\tconst mobileLabel = String(mobile.textContent || 'Mobile').trim();\n\t\ticonize(mobile, 'mobile', mobileLabel);\n\t\ticonize(tablet, 'tablet', tabletLabel);\n\t\ticonize(desktop, 'desktop', desktopLabel);\n\t\tviewportGroup.replaceChildren(mobile, tablet, desktop);\n",
)

replace_once(
    'assets/js/features/designer-launch.js',
    "\t\tconst previewFrame = root.querySelector('[data-cb-mail-preview-frame]');\n\t\tif (!historyGroup || !viewportGroup || !fullscreen || !save) return;\n",
    "\t\tif (!historyGroup || !viewportGroup || !fullscreen || !save) return;\n",
)

replace_once(
    'src/Mail/Admin/Page.php',
    "use CB\\Core\\Admin\\TabNav;\n",
    "use CB\\Core\\Admin\\TabNav;\nuse CB\\Core\\Brand\\CoreBlueprintMark;\n",
)

replace_once(
    'src/Mail/Admin/Page.php',
    "\t\t\t\t'label'       => __( 'Design with Core Blueprint', 'core-blueprint' ),\n\t\t\t\t'ariaLabel'   => __( 'Open Designer Mode', 'core-blueprint' ),\n\t\t\t\t'tabletLabel' => __( 'Tablet', 'default' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- intentional WordPress platform vocabulary.\n\t\t\t\t'iconUrl'     => CB_CORE_URL . 'assets/core-blueprint-icon.svg',\n",
    "\t\t\t\t'label'     => __( 'Design with Core Blueprint', 'core-blueprint' ),\n\t\t\t\t'ariaLabel' => __( 'Open Designer Mode', 'core-blueprint' ),\n\t\t\t\t'iconUrl'   => CoreBlueprintMark::data_uri(),\n",
)

replace_once(
    'assets/css/design/editor-shell.css',
    ".cb-core-design-shell__brand-mark {\n\tdisplay: inline-flex;\n\talign-items: center;\n\tjustify-content: center;\n\tflex: 0 0 30px;\n\twidth: 30px;\n\theight: 30px;\n\tborder-radius: var(--cb-radius-sm);\n\tbackground: var(--cb-accent);\n}\n\n.cb-core-design-shell__brand-mark img {\n\tdisplay: block;\n\twidth: 19px;\n\theight: 19px;\n\tobject-fit: contain;\n}\n",
    ".cb-core-design-shell__brand-mark {\n\tdisplay: inline-flex;\n\talign-items: center;\n\tjustify-content: center;\n\tflex: 0 0 30px;\n\twidth: 30px;\n\theight: 30px;\n\tborder-radius: 50%;\n\toverflow: hidden;\n}\n\n.cb-core-design-shell__brand-mark img {\n\tdisplay: block;\n\twidth: 30px;\n\theight: 30px;\n\tobject-fit: contain;\n}\n",
)

replace_once(
    'tests/integration/DesignerModeHeaderTest.php',
    "\t\tself::assertStringContainsString( \"tablet.dataset.cbMailViewport = 'tablet';\", $launch );\n",
    "\t\tself::assertStringContainsString( \"[data-cb-mail-viewport=\\\"tablet\\\"]\", $launch );\n\t\tself::assertStringNotContainsString( 'setViewportState', $launch );\n",
)

replace_once(
    'tests/integration/DesignerModeHeaderTest.php',
    "\tpublic function test_tablet_label_uses_wordpress_platform_vocabulary(): void {\n\t\t$page = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Mail/Admin/Page.php' );\n\n\t\tself::assertStringContainsString( \"'tabletLabel' => __( 'Tablet', 'default' )\", $page );\n\t\tself::assertStringNotContainsString( \"'Tablet', 'core-blueprint'\", $page );\n\t}\n",
    "\tpublic function test_tablet_viewport_is_owned_by_mail_and_uses_wordpress_platform_vocabulary(): void {\n\t\t$root = dirname( __DIR__, 2 );\n\t\t$template = (string) file_get_contents( $root . '/templates/mail-designer.php' );\n\t\t$feature = (string) file_get_contents( $root . '/assets/js/features/mail-designer.js' );\n\n\t\tself::assertStringContainsString( \"\\$tablet_label = __( 'Tablet', 'default' )\", $template );\n\t\tself::assertStringContainsString( 'data-cb-mail-viewport=\"tablet\"', $template );\n\t\tself::assertStringNotContainsString( \"'Tablet', 'core-blueprint'\", $template );\n\t\tself::assertStringContainsString( \"value === 'tablet'\", $feature );\n\t\tself::assertStringContainsString( \"classList.toggle('is-tablet'\", $feature );\n\t}\n",
)

replace_once(
    'tests/integration/DesignerModeHeaderTest.php',
    "\n}\n",
    "\n\tpublic function test_designer_mode_and_hud_share_the_canonical_core_blueprint_mark(): void {\n\t\t$root = dirname( __DIR__, 2 );\n\t\t$page = (string) file_get_contents( $root . '/src/Mail/Admin/Page.php' );\n\t\t$hud = (string) file_get_contents( $root . '/src/HUD/Brand/CoreBlueprint.php' );\n\t\t$mark = (string) file_get_contents( $root . '/src/Brand/CoreBlueprintMark.php' );\n\n\t\tself::assertStringContainsString( 'CoreBlueprintMark::data_uri()', $page );\n\t\tself::assertStringContainsString( 'CoreBlueprintMark::svg()', $hud );\n\t\tself::assertStringContainsString( '#00FFDD', $mark );\n\t\tself::assertStringContainsString( '#0037FF', $mark );\n\t\tself::assertStringContainsString( '#131648', $mark );\n\t\tself::assertStringNotContainsString( 'assets/core-blueprint-icon.svg', $page );\n\t}\n}\n",
)

replace_once(
    'tests/js/design-mail-profile.test.mjs',
    "\tassert.match(source, /cbMailViewport\\s*=\\s*['\"]tablet['\"]/);\n\tassert.match(source, /cb-core-design-shell__toolbar--designer/);\n",
    "\tassert.match(source, /\\[data-cb-mail-viewport=\"tablet\"\\]/);\n\tassert.match(source, /cb-core-design-shell__toolbar--designer/);\n\tassert.doesNotMatch(source, /setViewportState/);\n",
)

synchronize_brand_mark()

print('PASS: Designer viewport ownership and canonical brand synchronized.')
