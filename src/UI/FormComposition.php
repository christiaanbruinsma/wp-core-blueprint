<?php
declare(strict_types=1);
/**
 * Shared form-composition presentation boundary.
 *
 * Core Admin already receives Stack through the minimal shell and may opt into
 * Field through PageRegistry. Standalone WordPress admin screens can use this
 * helper to reuse the same semantic markup while keeping native WordPress
 * controls, colours and chrome.
 *
 * @package Core_Blueprint
 * @since   1.0.0-rc3.36
 */

namespace CB\Core\UI;

defined( 'ABSPATH' ) || exit;

final class FormComposition {

	public const PRESENTATION_CORE = 'core';
	public const PRESENTATION_WP_NATIVE = 'wp-native';

	/**
	 * Enqueue shared Field + Stack composition styling.
	 *
	 * Standalone wp-admin consumers should normally keep the default
	 * `wp-native` presentation. Core Admin pages should prefer PageRegistry's
	 * semantic `fields` requirement; Stack is already part of the minimal shell.
	 * The explicit `core` option exists for controlled Base-owned composition
	 * contexts and does not change the caller's screen identity.
	 *
	 * @param string $presentation `wp-native` (default) or `core`.
	 */
	public static function enqueue( string $presentation = self::PRESENTATION_WP_NATIVE ): void {
		if ( ! in_array( $presentation, [ self::PRESENTATION_WP_NATIVE, self::PRESENTATION_CORE ], true ) ) {
			$presentation = self::PRESENTATION_WP_NATIVE;
		}

		if ( self::PRESENTATION_WP_NATIVE === $presentation ) {
			wp_enqueue_style(
				'cb-core-css-form-composition-native',
				CB_CORE_URL . 'assets/css/components/form-composition-native.css',
				[],
				CB_CORE_VERSION
			);
			return;
		}

		if ( ! wp_style_is( 'cb-core-css-tokens', 'enqueued' ) ) {
			wp_enqueue_style(
				'cb-core-css-tokens',
				CB_CORE_URL . 'assets/css/tokens.css',
				[],
				CB_CORE_VERSION
			);
		}
		if ( ! wp_style_is( 'cb-core-css-layout', 'enqueued' ) ) {
			wp_enqueue_style(
				'cb-core-css-layout',
				CB_CORE_URL . 'assets/css/layout.css',
				[ 'cb-core-css-tokens' ],
				CB_CORE_VERSION
			);
		}
		if ( ! wp_style_is( 'cb-core-css-field', 'enqueued' ) ) {
			wp_enqueue_style(
				'cb-core-css-field',
				CB_CORE_URL . 'assets/css/components/field.css',
				[ 'cb-core-css-tokens' ],
				CB_CORE_VERSION
			);
		}
	}
}
