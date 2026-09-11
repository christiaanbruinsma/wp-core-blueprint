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
		self::assertStringContainsString( "tablet.dataset.cbMailViewport = 'tablet';", $launch );
		self::assertStringContainsString( 'cb-core-design-shell__toolbar--designer', $launch );
		self::assertStringContainsString( 'grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);', $shell_css );
		self::assertStringContainsString( '.cb-core-mail-designer__preview-frame.is-tablet iframe', $mail_css );
		self::assertStringContainsString( 'max-width: 782px;', $mail_css );
	}

	public function test_tablet_label_uses_wordpress_platform_vocabulary(): void {
		$page = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Mail/Admin/Page.php' );

		self::assertStringContainsString( "'tabletLabel' => __( 'Tablet', 'default' )", $page );
		self::assertStringNotContainsString( "'Tablet', 'core-blueprint'", $page );
	}
}
