<?php
declare(strict_types=1);
/**
 * Private Base-owned screen/view asset manifest.
 *
 * ScreenContext tells us where the request is. This registry decides whether
 * that context is a Core Admin surface and which private Base assets/providers
 * belong there. Public extension requirements continue to live in PageRegistry.
 *
 * @package Core_Blueprint
 * @since   1.0.0-rc3.26
 */

namespace CB\Core\Admin;

defined( 'ABSPATH' ) || exit;

final class ScreenAssetRegistry {

	/** Whether the normalized context belongs to a Core Admin screen. */
	public static function owns( ScreenContext $context ): bool {
		if ( '' !== $context->registered_slug() ) {
			return true;
		}

		$hook = $context->hook();
		return $hook === 'toplevel_page_' . CB_CORE_PARENT_MENU
			|| str_starts_with( $hook, CB_CORE_PARENT_MENU . '_page_cb-' )
			|| str_starts_with( $hook, CB_CORE_PARENT_MENU . '_page_core-blueprint-' );
	}

	/**
	 * Resolve Base-private requirements for a screen.
	 *
	 * E1 intentionally maps every Core Admin context to the existing full asset
	 * set. This preserves rc3.25 behavior while moving ownership/orchestration
	 * behind the declarative registry. E2 will replace this single requirement
	 * with canonical screen/tab/view manifests that consume context->tab() and
	 * context->view() without reading request globals again.
	 *
	 * @return string[]
	 */
	public static function requirements( ScreenContext $context ): array {
		if ( ! self::owns( $context ) ) {
			return [];
		}

		return [ AdminAssetCatalog::E1_FULL_SET ];
	}
}
