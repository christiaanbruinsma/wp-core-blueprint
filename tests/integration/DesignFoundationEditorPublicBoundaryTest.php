<?php
declare(strict_types=1);

use CB\Core\Admin\PageRegistry;
use CB\Core\Design\Editor\Assets as DesignEditorAssets;

final class CB_Design_Foundation_Editor_Public_Boundary_Test extends WP_UnitTestCase {

	public function test_design_editor_is_a_public_semantic_foundation_requirement(): void {
		$normalized = PageRegistry::normalize_semantic_requirements(
			[ 'foundations' => [ 'design-editor' ] ],
			'fixture:design-editor'
		);

		self::assertSame(
			[ 'foundations' => [ 'design-editor' ], 'components' => [] ],
			$normalized
		);
	}

	public function test_public_module_identifier_and_asset_path_are_base_owned(): void {
		self::assertSame( '@cb-core/design-editor', DesignEditorAssets::MODULE_ID );

		$path = dirname( __DIR__, 2 ) . '/src/Design/Editor/Assets.php';
		$source = (string) file_get_contents( $path );
		self::assertStringContainsString( "CB_CORE_URL . 'assets/js/design/editor.js'", $source );
		self::assertStringContainsString( 'wp_enqueue_script_module(', $source );
	}

	public function test_public_facade_hides_private_editor_file_layout_from_semantic_consumers(): void {
		$page_registry = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/Admin/PageRegistry.php' );
		self::assertStringContainsString( "'design-editor'", $page_registry );
		self::assertStringContainsString( 'DesignEditorAssets::enqueue();', $page_registry );
		self::assertStringNotContainsString( 'assets/js/design/core/', $page_registry );
		self::assertStringNotContainsString( 'assets/js/design/document/', $page_registry );

		$facade = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/design/editor.js' );
		self::assertStringContainsString( 'window.cbCore.designEditor = publicApi;', $facade );
		self::assertStringContainsString( "'document-flow'", $facade );
		self::assertStringContainsString( "'document-fixed'", $facade );
		self::assertStringContainsString( 'allowCommand', $facade );
		self::assertStringContainsString( 'onChange', $facade );
	}
}
