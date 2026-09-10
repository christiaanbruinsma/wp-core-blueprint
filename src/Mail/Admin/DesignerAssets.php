<?php
declare(strict_types=1);
/**
 * Lazy admin assets for the Base-owned Mail Designer surface.
 *
 * @package Core_Blueprint
 */

namespace CB\Core\Mail\Admin;

use CB\Core\Admin\PageRegistry;
use CB\Core\Design\Editor\Assets as DesignEditorAssets;

defined( 'ABSPATH' ) || exit;

final class DesignerAssets {
	public const MODULE_ID = '@cb-core/mail-designer';
	public const STYLE_HANDLE = 'cb-core-mail-designer';

	public static function enqueue( string $hook ): void {
		if ( $hook !== PageRegistry::hook_suffix( Page::SLUG ) ) {
			return;
		}
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only presentation routing.
		if ( 'templates' !== $tab ) {
			return;
		}

		DesignEditorAssets::enqueue();
		wp_enqueue_style(
			self::STYLE_HANDLE,
			CB_CORE_URL . 'assets/css/pages/mail-designer.css',
			[ 'cb-core-css-page-mail' ],
			CB_CORE_VERSION
		);
		wp_enqueue_script_module(
			self::MODULE_ID,
			CB_CORE_URL . 'assets/js/features/mail-designer.js',
			[ DesignEditorAssets::MODULE_ID ],
			CB_CORE_VERSION
		);
	}

	private function __construct() {}
}
