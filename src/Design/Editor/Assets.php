<?php
declare(strict_types=1);
/**
 * Public asset boundary for the shared Design Foundation editor engine.
 *
 * Extensions opt into this capability semantically through PageRegistry or
 * SettingsRegistry. Base owns module/script identifiers, versioning and private
 * source layout behind these public Design Foundation asset methods.
 *
 * @package Core_Blueprint
 */

namespace CB\Core\Design\Editor;

use CB\Core\Brand\CoreBlueprintMark;

defined( 'ABSPATH' ) || exit;

final class Assets {

	public const MODULE_ID = '@cb-core/design-editor';
	public const SHELL_STYLE = 'cb-core-design-editor-shell';
	public const DESIGNER_MODE_SCRIPT = 'cb-core-designer-mode';

	public static function enqueue(): void {
		wp_enqueue_style(
			self::SHELL_STYLE,
			CB_CORE_URL . 'assets/css/design/editor-shell.css',
			[ 'cb-core-css-tokens' ],
			self::asset_version( 'assets/css/design/editor-shell.css' )
		);

		wp_enqueue_script_module(
			self::MODULE_ID,
			CB_CORE_URL . 'assets/js/design/editor.js',
			[],
			self::asset_version( 'assets/js/design/editor.js' )
		);
	}

	/**
	 * Enqueue the canonical Core Blueprint Designer Mode around a consumer shell.
	 *
	 * Consumers provide only declarative `data-cb-design-*` shell contracts and
	 * domain callbacks. Base owns launch/focus chrome, brand, shared labels and
	 * the private Designer Mode source path.
	 */
	public static function enqueue_designer_mode(): void {
		self::enqueue();

		wp_enqueue_script(
			self::DESIGNER_MODE_SCRIPT,
			CB_CORE_URL . 'assets/js/features/designer-launch.js',
			[],
			self::asset_version( 'assets/js/features/designer-launch.js' ),
			true
		);
		wp_localize_script(
			self::DESIGNER_MODE_SCRIPT,
			'cbCoreDesignerLaunch',
			[
				'label'         => __( 'Design with Core Blueprint', 'core-blueprint' ),
				'ariaLabel'     => __( 'Open Designer Mode', 'core-blueprint' ),
				'iconUrl'       => CoreBlueprintMark::data_uri(),
				'sidebarLabels' => [
					'inspector' => __( 'Inspector', 'core-blueprint' ),
					// WordPress editor vocabulary intentionally uses the default text domain.
					'layers'    => __( 'Layers', 'default' ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- intentional WordPress platform vocabulary.
					'settings'  => __( 'Settings', 'core-blueprint' ),
				],
			]
		);
	}

	/**
	 * Return a stable content revision for a public Design Foundation entry asset.
	 *
	 * Base can evolve during one RC without forcing the plugin version to change
	 * for every internal build. Content fingerprints make the browser request the
	 * current public entry asset after an update while retaining the plugin version
	 * as a safe fallback when the file cannot be read.
	 */
	private static function asset_version( string $relative_path ): string {
		$path = CB_CORE_DIR . $relative_path;
		if ( ! is_readable( $path ) ) {
			return CB_CORE_VERSION;
		}

		$hash = hash_file( 'sha256', $path );
		if ( ! is_string( $hash ) || '' === $hash ) {
			return CB_CORE_VERSION;
		}

		return CB_CORE_VERSION . '-' . substr( $hash, 0, 12 );
	}

	private function __construct() {}
}
