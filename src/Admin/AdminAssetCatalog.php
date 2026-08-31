<?php
declare(strict_types=1);
/**
 * Private catalog for Core Admin assets and providers.
 *
 * Catalog identifiers, WordPress handles and filenames are Base internals. The
 * public extension contract remains PageRegistry's semantic Foundations and
 * components; consumers must never depend on entries defined here.
 *
 * @package Core_Blueprint
 * @since   1.0.0-rc3.26
 */

namespace CB\Core\Admin;

defined( 'ABSPATH' ) || exit;

final class AdminAssetCatalog {

	public const E1_FULL_SET = 'e1.full-set';

	/**
	 * Enqueue one selected private asset/provider.
	 *
	 * Providers are deliberately invoked only after selection by the screen
	 * registry. Runtime-data providers added here in later E2 work must remain
	 * read/build-only: evaluating an asset may not migrate, repair or write state.
	 */
	public static function enqueue( string $asset_id, ScreenContext $context ): void {
		$definition = self::definition( $asset_id );
		if ( null === $definition ) {
			return;
		}

		if ( isset( $definition['provider'] ) && is_callable( $definition['provider'] ) ) {
			$definition['provider']( $context );
			return;
		}

		if ( 'style' === ( $definition['type'] ?? '' ) ) {
			wp_enqueue_style(
				(string) $definition['handle'],
				CB_CORE_URL . (string) $definition['src'],
				(array) ( $definition['deps'] ?? [] ),
				CB_CORE_VERSION
			);
		}
	}

	/**
	 * Minimal Core Admin shell ownership for later selective resolution.
	 *
	 * E1 records this boundary but does not enqueue these entries separately:
	 * the full-set provider below intentionally preserves rc3.25 enqueue order
	 * and effective output. E2 may resolve these entries directly.
	 *
	 * @return string[]
	 */
	public static function minimal_shell(): array {
		return [
			'shell.tokens',
			'shell.scrollbar',
			'shell.layout',
			'shell.buttons',
			'shell.form-controls',
			'shell.theme-canvas',
		];
	}

	/** @return array<string,mixed>|null */
	private static function definition( string $asset_id ): ?array {
		$definitions = [
			// E1 architecture adapter. The existing rc3.25 loader remains the
			// implementation behind one private provider so E1 changes orchestration
			// without changing which assets/data are produced. E2 will split it.
			self::E1_FULL_SET => [
				'type'     => 'provider',
				'provider' => static function ( ScreenContext $context ): void {
					Admin::enqueue_assets( $context->hook() );
				},
			],

			// Genuine minimal-shell candidates. These definitions are intentionally
			// private and dormant in E1 to preserve rc3.25 enqueue order exactly.
			'shell.tokens' => [
				'type'   => 'style',
				'handle' => 'cb-core-css-tokens',
				'src'    => 'assets/css/tokens.css',
				'deps'   => [],
			],
			'shell.scrollbar' => [
				'type'   => 'style',
				'handle' => 'cb-core-css-scrollbar',
				'src'    => 'assets/css/components/scrollbar.css',
				'deps'   => [ 'cb-core-css-tokens' ],
			],
			'shell.layout' => [
				'type'   => 'style',
				'handle' => 'cb-core-css-layout',
				'src'    => 'assets/css/layout.css',
				'deps'   => [ 'cb-core-css-tokens' ],
			],
			'shell.buttons' => [
				'type'   => 'style',
				'handle' => 'cb-core-css-buttons',
				'src'    => 'assets/css/components/buttons.css',
				'deps'   => [ 'cb-core-css-tokens' ],
			],
			'shell.form-controls' => [
				'type'   => 'style',
				'handle' => 'cb-core-css-form-controls',
				'src'    => 'assets/css/components/form-controls.css',
				'deps'   => [ 'cb-core-css-tokens' ],
			],
			'shell.theme-canvas' => [
				'type'   => 'style',
				'handle' => 'cb-core-css-theme-canvas',
				'src'    => 'assets/css/themes/canvas.css',
				'deps'   => [ 'cb-core-css-tokens' ],
			],
		];

		return $definitions[ $asset_id ] ?? null;
	}
}
