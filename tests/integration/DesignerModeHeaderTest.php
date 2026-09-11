<?php
declare(strict_types=1);

final class CB_Designer_Mode_Header_Test extends WP_UnitTestCase {

	public function test_mail_designer_chrome_reuses_shared_fullscreen_runtime(): void {
		$root = dirname( __DIR__, 2 );
		$launch = (string) file_get_contents( $root . '/assets/js/features/designer-launch.js' );

		self::assertStringContainsString( "fullscreen.click()", $launch );
		self::assertStringContainsString( "shell.addEventListener('cb:design-shell:fullscreenchange'", $launch );
		self::assertStringNotContainsString( 'createDesignerShell', $launch );
		self::assertStringNotContainsString( 'requestFullscreen', $launch );
	}

	public function test_designer_header_preserves_existing_controls_and_adds_tablet_viewport(): void {
		$root = dirname( __DIR__, 2 );
		$launch = (string) file_get_contents( $root . '/assets/js/features/designer-launch.js' );
		$shell_css = (string) file_get_contents( $root . '/assets/css/design/editor-shell.css' );
		$mail_css = (string) file_get_contents( $root . '/assets/css/pages/mail-designer.css' );

		self::assertStringContainsString( "[data-cb-design-shell-undo]", $launch );
		self::assertStringContainsString( "[data-cb-design-shell-redo]", $launch );
		self::assertStringContainsString( "[data-cb-design-shell-fullscreen]", $launch );
		self::assertStringContainsString( "button[type=\"submit\"]", $launch );
		self::assertStringContainsString( "[data-cb-mail-viewport=\"tablet\"]", $launch );
		self::assertStringNotContainsString( 'setViewportState', $launch );
		self::assertStringContainsString( 'cb-core-design-shell__toolbar--designer', $launch );
		self::assertStringContainsString( 'grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);', $shell_css );
		self::assertStringContainsString( '.cb-core-mail-designer__preview-frame.is-tablet iframe', $mail_css );
		self::assertStringContainsString( 'max-width: 782px;', $mail_css );
	}

	public function test_designer_mode_is_launch_only_before_fullscreen_and_toolbar_actions_do_not_shrink(): void {
		$root = dirname( __DIR__, 2 );
		$launch = (string) file_get_contents( $root . '/assets/js/features/designer-launch.js' );
		$shell_css = (string) file_get_contents( $root . '/assets/css/design/editor-shell.css' );
		$mail_css = (string) file_get_contents( $root . '/assets/css/pages/mail-designer.css' );

		self::assertStringContainsString( "root.classList.toggle('is-designer-mode-active', active)", $launch );
		self::assertStringContainsString( ".cb-core-mail-designer__workspace {\n\tdisplay: none;", $mail_css );
		self::assertStringContainsString( '.cb-core-mail-designer.is-designer-mode-active .cb-core-mail-designer__workspace', $mail_css );
		self::assertStringContainsString( '.cb-core-design-shell__toolbar--designer .cb-core-design-shell__toolbar-group', $shell_css );
		self::assertStringContainsString( "\tflex: 0 0 auto;", $shell_css );
		self::assertStringContainsString( '.cb-core-design-shell__toolbar--designer .cb-core-design-shell__toolbar-status:empty', $shell_css );
	}

	public function test_tablet_viewport_is_owned_by_mail_and_uses_wordpress_platform_vocabulary(): void {
		$root = dirname( __DIR__, 2 );
		$template = (string) file_get_contents( $root . '/templates/mail-designer.php' );
		$feature = (string) file_get_contents( $root . '/assets/js/features/mail-designer.js' );

		self::assertStringContainsString( "\$tablet_label = __( 'Tablet', 'default' )", $template );
		self::assertStringContainsString( 'data-cb-mail-viewport="tablet"', $template );
		self::assertStringNotContainsString( "'Tablet', 'core-blueprint'", $template );
		self::assertStringContainsString( "value === 'tablet'", $feature );
		self::assertStringContainsString( "classList.toggle('is-tablet'", $feature );
	}

	public function test_designer_mode_and_hud_share_the_canonical_core_blueprint_mark(): void {
		$root = dirname( __DIR__, 2 );
		$page = (string) file_get_contents( $root . '/src/Mail/Admin/Page.php' );
		$hud = (string) file_get_contents( $root . '/src/HUD/Brand/CoreBlueprint.php' );
		$mark = (string) file_get_contents( $root . '/src/Brand/CoreBlueprintMark.php' );

		self::assertStringContainsString( 'CoreBlueprintMark::data_uri()', $page );
		self::assertStringContainsString( 'CoreBlueprintMark::svg()', $hud );
		self::assertStringContainsString( '#00FFDD', $mark );
		self::assertStringContainsString( '#0037FF', $mark );
		self::assertStringContainsString( '#131648', $mark );
		self::assertStringNotContainsString( 'assets/core-blueprint-icon.svg', $page );
	}
}