<?php
declare(strict_types=1);
/**
 * Admin Theme adapter policy.
 *
 * WordPress Core is light-first, so Base only activates its WordPress admin
 * presentation adapters while the resolved admin mode is Dark. Core Blueprint
 * components remain token-driven in both modes; Light therefore stays as close
 * to native WordPress as possible instead of being re-skinned unnecessarily.
 *
 * This class also owns the small set of curated compatibility adapters that
 * Base deliberately supports. These adapters bridge third-party presentation
 * tokens or correct a handful of hardcoded surfaces; they never take ownership
 * of plugin layout or business UI.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\UI;

use CB\Core\Themes;

defined( 'ABSPATH' ) || exit;

final class AdminThemeAdapters {

	private const CORE_HANDLE = 'cb-core-css-admin-theme-core-screens';
	private const HAPPYFILES_HANDLE = 'cb-core-css-admin-theme-integration-happyfiles';

	/** @var array<string, true> */
	private const DARK_ADAPTER_HANDLES = [
		'cb-core-css-admin-theme' => true,
		self::CORE_HANDLE => true,
		self::HAPPYFILES_HANDLE => true,
	];

	private static bool $initialized = false;

	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		// AdminTheme enqueues the canonical adapter at priority 0. Add modular
		// WordPress Core / curated integration layers immediately afterwards.
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ], 1 );

		// The canonical adapter stylesheet is intentionally dark-only. Using the
		// media attribute avoids duplicating hundreds of selectors just to scope
		// every rule to data-cb-mode="dark", while remaining live-switchable.
		add_filter( 'style_loader_tag', [ self::class, 'filter_style_loader_tag' ], 10, 4 );
	}

	public static function enqueue( string $hook_suffix = '' ): void {
		if ( ! AdminTheme::applies( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			self::CORE_HANDLE,
			CB_CORE_URL . 'assets/css/admin-theme/core-screens.css',
			[ 'cb-core-css-admin-theme' ],
			CB_CORE_VERSION
		);

		// HappyFiles is a deliberate first curated bridge: it is frequently used
		// alongside Bricks and exposes useful --hf-* presentation variables. Do
		// not guess plugin paths; the public namespaced bootstrap class is enough.
		if ( class_exists( '\\HappyFiles\\Init' ) ) {
			wp_enqueue_style(
				self::HAPPYFILES_HANDLE,
				CB_CORE_URL . 'assets/css/admin-theme/integrations/happyfiles.css',
				[ 'cb-core-css-admin-theme' ],
				CB_CORE_VERSION
			);
		}
	}

	/**
	 * Give every WordPress/curated adapter the correct initial media boundary.
	 *
	 * Auto mode uses the browser media query before first paint. The browser API
	 * then normalises all adapter links to `all` / `not all` whenever the HUD or
	 * system preference changes the resolved data-cb-mode attribute.
	 */
	public static function filter_style_loader_tag( string $html, string $handle, string $href, string $media ): string {
		unset( $href, $media );

		if ( ! isset( self::DARK_ADAPTER_HANDLES[ $handle ] ) ) {
			return $html;
		}

		$target_media = self::initial_media();
		$escaped      = esc_attr( $target_media );

		if ( preg_match( '/\smedia=(["\']).*?\1/i', $html ) ) {
			return (string) preg_replace( '/\smedia=(["\']).*?\1/i', ' media="' . $escaped . '"', $html, 1 );
		}

		return str_replace( '<link ', '<link media="' . $escaped . '" ', $html );
	}

	private static function initial_media(): string {
		if ( Themes::is_auto_mode() ) {
			return '(prefers-color-scheme: dark)';
		}

		return 'dark' === AdminTheme::mode() ? 'all' : 'not all';
	}
}
