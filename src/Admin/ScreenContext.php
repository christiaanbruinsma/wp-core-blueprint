<?php
declare(strict_types=1);
/**
 * Normalized snapshot of the current WordPress admin screen request.
 *
 * ScreenContext answers only "where am I?". It intentionally owns no asset
 * rules, capability policy, page definitions or feature/business logic.
 *
 * @package Core_Blueprint
 * @since   1.0.0-rc3.26
 */

namespace CB\Core\Admin;

defined( 'ABSPATH' ) || exit;

final class ScreenContext {

	private function __construct(
		private readonly string $hook,
		private readonly string $registered_slug,
		private readonly string $page,
		private readonly string $tab,
		private readonly string $view
	) {}

	/** Build one normalized, read-only snapshot for the current admin screen. */
	public static function from_request( string $hook ): self {
		$registered_slug = self::registered_slug_for_hook( $hook );
		$page            = self::request_key( 'page' );

		// WordPress does not need a page query arg for the top-level landing
		// callback. Give it the same canonical slug downstream manifests expect.
		if ( '' === $page && 'toplevel_page_' . CB_CORE_PARENT_MENU === $hook ) {
			$page = CB_CORE_PARENT_MENU;
		} elseif ( '' === $page && '' !== $registered_slug ) {
			$page = $registered_slug;
		}

		return new self(
			$hook,
			$registered_slug,
			$page,
			self::request_key( 'tab' ),
			self::request_key( 'view' )
		);
	}

	public function hook(): string {
		return $this->hook;
	}

	public function registered_slug(): string {
		return $this->registered_slug;
	}

	public function page(): string {
		return $this->page;
	}

	public function tab(): string {
		return $this->tab;
	}

	public function view(): string {
		return $this->view;
	}

	/**
	 * Read and normalize a routing key once.
	 *
	 * Values are request context only: no nonce is required because this method
	 * never authorizes or mutates anything. Unknown values remain sanitized
	 * strings; registries decide which canonical values they recognize.
	 */
	private static function request_key( string $key ): string {
		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing context.
			return '';
		}

		return sanitize_key( wp_unslash( (string) $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing context.
	}

	/** Resolve registry ownership without copying page definitions into context. */
	private static function registered_slug_for_hook( string $hook ): string {
		if ( 'toplevel_page_' . CB_CORE_PARENT_MENU === $hook ) {
			return CB_CORE_PARENT_MENU;
		}

		foreach ( PageRegistry::all() as $page ) {
			$slug = $page->slug();
			if ( $hook === PageRegistry::hook_suffix( $slug ) ) {
				return $slug;
			}
		}

		return '';
	}
}
