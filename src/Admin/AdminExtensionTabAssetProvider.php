<?php
declare(strict_types=1);
/**
 * Private compatibility provider for registered Logs/Reports addon tabs.
 *
 * These public extension surfaces predate semantic per-tab asset declarations.
 * Until that contract grows an explicit requirements field, unknown registered
 * tabs receive the complete private Base asset inventory from the canonical
 * catalogs. No unregistered admin screen is eligible for this provider.
 *
 * @package Core_Blueprint
 */

namespace CB\Core\Admin;

defined( 'ABSPATH' ) || exit;

final class AdminExtensionTabAssetProvider {

	public static function enqueue( ScreenContext $context ): void {
		foreach ( AdminAssetCatalog::extension_tab_full_set() as $asset_id ) {
			AdminAssetCatalog::enqueue( $asset_id, $context );
		}

		PageRegistry::enqueue_requirements_for_hook( $context->hook() );

		foreach ( AdminModuleCatalog::ids() as $module_id ) {
			AdminModuleCatalog::enqueue( $module_id, $context );
		}
	}
}
