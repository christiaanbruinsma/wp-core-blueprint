<?php
declare(strict_types=1);
/**
 * Public asset boundary for the shared Design Foundation editor engine.
 *
 * Extensions opt into this capability semantically through PageRegistry or
 * SettingsRegistry. Base owns the module identifier, versioning and private
 * source layout behind the public `@cb-core/design-editor` boundary.
 *
 * @package Core_Blueprint
 */

namespace CB\Core\Design\Editor;

defined( 'ABSPATH' ) || exit;

final class Assets {

	public const MODULE_ID = '@cb-core/design-editor';

	public static function enqueue(): void {
		wp_enqueue_script_module(
			self::MODULE_ID,
			CB_CORE_URL . 'assets/js/design/editor.js',
			[],
			CB_CORE_VERSION
		);
	}

	private function __construct() {}
}
