<?php
declare(strict_types=1);
/**
 * WP-native Form Controls Foundation for standalone Core Blueprint extensions.
 *
 * Consumers opt in explicitly and scope only extension-owned form surfaces.
 * WordPress remains the presentation authority for colours, focus behaviour,
 * browser affordances and admin chrome; Base normalizes reusable box geometry.
 *
 * @package Core_Blueprint
 * @since   1.0.0-rc3.39
 */

namespace CB\Core\UI;

defined( 'ABSPATH' ) || exit;

final class FormControls {
	public const SCOPE_CLASS = 'cb-core-native-form-scope';
	public const STYLE_HANDLE = 'cb-core-css-form-controls-native';

	/** Enqueue the standalone WP-native form-control geometry adapter. */
	public static function enqueue(): void {
		wp_enqueue_style(
			self::STYLE_HANDLE,
			CB_CORE_URL . 'assets/css/components/form-controls-native.css',
			[],
			CB_CORE_VERSION
		);
	}
}
