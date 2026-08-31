<?php
declare(strict_types=1);

namespace CB\Core\Snippets\Admin;

use CB\Core\Admin\PageRegistry;
use CB\Core\Snippets\Schema;
use CB\Core\UI\Assets as UiAssets;

defined( 'ABSPATH' ) || exit;

final class Assets {
	/**
	 * Called from the central CB\Core\Admin\Admin::enqueue_assets() surface.
	 */
	public static function enqueue( string $hook ): void {
		if ( PageRegistry::hook_suffix( Page::SLUG ) !== $hook ) {
			return;
		}

		UiAssets::enqueue_modals( UiAssets::MODAL_PRESENTATION_CORE );

		wp_enqueue_style(
			'cb-core-css-page-snippets',
			CB_CORE_URL . 'assets/css/pages/snippets.css',
			[ 'cb-core-css-tokens', 'cb-core-css-layout', 'cb-core-css-buttons', 'cb-core-css-form-controls', 'cb-core-css-field', 'cb-core-css-notices' ],
			CB_CORE_VERSION
		);

		$editor = wp_enqueue_code_editor( [ 'type' => 'text/x-php' ] );
		wp_enqueue_script(
			'cb-core-snippets-admin',
			CB_CORE_URL . 'assets/js/features/snippets.js',
			[ 'code-editor' ],
			CB_CORE_VERSION,
			true
		);

		$locations = [];
		foreach ( Schema::TYPES as $type ) {
			$locations[ $type ] = Schema::locations_for_type( $type );
		}
		wp_localize_script( 'cb-core-snippets-admin', 'cbCoreSnippetsData', [
			'editor'    => is_array( $editor ) ? $editor : [],
			'locations' => $locations,
			'i18n'      => [
				'deleteTitle' => __( 'Delete snippet?', 'core-blueprint' ),
				'deleteBody'  => __( 'This removes the managed snippet file and its metadata. This action cannot be undone.', 'core-blueprint' ),
				'delete'      => __( 'Delete snippet', 'core-blueprint' ),
				'cancel'      => __( 'Cancel', 'core-blueprint' ),
			],
		] );
	}
}
