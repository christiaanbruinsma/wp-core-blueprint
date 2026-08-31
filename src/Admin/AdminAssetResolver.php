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

	/** Install the resolver ahead of Admin::init()'s historical callback. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ], 10 );
	}

	/** Resolve the current screen and enqueue its private Base requirements. */
	public static function enqueue( string $hook ): void {
		// Admin::init() still registers the historical callback during E1. Remove
		// that callback for this request so all asset work flows through exactly
		// one resolver entrypoint. The catalog provider calls it explicitly once
		// for owned Core Admin screens to preserve its effective rc3.25 output.
		remove_action( 'admin_enqueue_scripts', [ Admin::class, 'enqueue_assets' ], 10 );

		$context = ScreenContext::from_request( $hook );
		foreach ( ScreenAssetRegistry::requirements( $context ) as $asset_id ) {
			AdminAssetCatalog::enqueue( $asset_id, $context );
		}
	}
}
