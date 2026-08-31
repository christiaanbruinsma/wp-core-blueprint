<?php
declare(strict_types=1);
/**
 * Core Admin screen asset resolver.
 *
 * This is the single orchestration entrypoint introduced by BASE-10E.1. The
 * existing Admin::enqueue_assets() implementation remains intact behind one
 * private catalog provider for E1 so effective rc3.25 output is preserved.
 *
 * @package Core_Blueprint
 * @since   1.0.0-rc3.26
 */

namespace CB\Core\Admin;

defined( 'ABSPATH' ) || exit;

final class AdminAssetResolver {

	private static bool $initialized = false;

	/**
	 * Install the resolver before Admin::init() registers the historical loader.
	 *
	 * The resolver itself keeps the historical priority 10 position. The old
	 * callback is removed on admin_init, before admin_enqueue_scripts begins, so
	 * WordPress never enters the old callback independently and the catalog can
	 * invoke it exactly once for an owned Core Admin screen.
	 */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ], 10 );
		add_action( 'admin_init', [ __CLASS__, 'prepare' ], 0 );
	}

	/** Remove the historical direct loader before enqueue dispatch starts. */
	public static function prepare(): void {
		remove_action( 'admin_enqueue_scripts', [ Admin::class, 'enqueue_assets' ], 10 );
	}

	/** Resolve the current screen and enqueue its private Base requirements. */
	public static function enqueue( string $hook ): void {
		$context = ScreenContext::from_request( $hook );
		foreach ( ScreenAssetRegistry::requirements( $context ) as $asset_id ) {
			AdminAssetCatalog::enqueue( $asset_id, $context );
		}
	}
}
