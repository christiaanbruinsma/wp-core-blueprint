<?php
declare(strict_types=1);
/**
 * Core Admin screen asset resolver.
 *
 * BASE-10E.2 resolves Base screens through ScreenContext + private manifests.
 * Unregistered sibling-pattern screens and open extension-contributed tabs keep
 * the rc3.26 full-set provider until their contracts are reviewed in E3.
 *
 * @package Core_Blueprint
 * @since   1.0.0-rc3.26
 */

namespace CB\Core\Admin;

defined( 'ABSPATH' ) || exit;

final class AdminAssetResolver {

	private static bool $initialized = false;

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
		if ( ! ScreenAssetRegistry::owns( $context ) ) {
			return;
		}

		// Preserve exact rc3.26 ordering for compatibility-only surfaces. Do not
		// prepend the new shell because that would alter the historical cascade.
		if ( ScreenAssetRegistry::requires_full_set( $context ) ) {
			AdminAssetCatalog::enqueue( AdminAssetCatalog::E1_FULL_SET, $context );
			return;
		}

		$asset_ids = array_values( array_unique( array_merge(
			AdminAssetCatalog::minimal_shell(),
			ScreenAssetRegistry::requirements( $context )
		) ) );

		$weighted = [];
		foreach ( $asset_ids as $index => $asset_id ) {
			$weighted[] = [
				'id'       => $asset_id,
				'priority' => AdminAssetCatalog::priority( $asset_id ),
				'index'    => $index,
			];
		}
		usort( $weighted, static function ( array $a, array $b ): int {
			return $a['priority'] === $b['priority']
				? $a['index'] <=> $b['index']
				: $a['priority'] <=> $b['priority'];
		} );

		$public_requirements_enqueued = false;
		foreach ( $weighted as $asset ) {
			// Historically PageRegistry requirements were resolved immediately
			// after tokens/icons and before the rest of the central stylesheet
			// cascade. Keep that ordering for registered extension pages.
			if ( ! $public_requirements_enqueued && $asset['priority'] > 15 ) {
				PageRegistry::enqueue_requirements_for_hook( $hook );
				$public_requirements_enqueued = true;
			}
			AdminAssetCatalog::enqueue( $asset['id'], $context );
		}
		if ( ! $public_requirements_enqueued ) {
			PageRegistry::enqueue_requirements_for_hook( $hook );
		}
	}
}
