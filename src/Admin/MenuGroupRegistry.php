<?php
declare(strict_types=1);
/**
 * MenuGroupRegistry - public registration boundary for extension-owned
 * top-level product menus.
 *
 * Extensions register one MenuGroup plus its Page implementations during the
 * existing cb_core_register_pages lifecycle. Base validates the declaration,
 * wires WordPress menus/hooks and resolves shared semantic UI requirements.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Admin;

defined( 'ABSPATH' ) || exit;

final class MenuGroupRegistry {

	/** @var array<string,MenuGroup> */
	private static array $groups = [];

	/** @var array<string,Page> */
	private static array $pages = [];

	/** @var array<string,string> page slug -> group slug */
	private static array $page_groups = [];

	/** @var array<string,array{foundations:string[],components:string[]}> */
	private static array $requirements = [];

	/** @var array<string,string> page slug -> primary WordPress hook suffix */
	private static array $hooks = [];

	/** @var array<string,string> top-level hook suffix -> group slug */
	private static array $landing_hooks = [];

	private static bool $finalizer_hooked = false;
	private static bool $enqueue_hooked = false;
	private static bool $finalized = false;

	/**
	 * Register a top-level product menu and all pages it owns.
	 *
	 * The page whose slug equals the group slug is the canonical landing page.
	 * Base may render the first accessible child instead when the current user
	 * can see the product group but cannot access that landing page.
	 *
	 * @param Page[] $pages
	 * @param array<string,array{foundations?:string[],components?:string[]}> $requirements
	 */
	public static function register( MenuGroup $group, array $pages, array $requirements = [] ): bool {
		if ( self::$finalized ) {
			self::diagnostic( 'Top-level product menus must be registered during cb_core_register_pages before menu wiring is finalized.' );
			return false;
		}

		if ( ! self::valid_group( $group ) ) {
			return false;
		}

		$group_slug = $group->slug();
		if ( isset( self::$groups[ $group_slug ] ) ) {
			self::diagnostic( "Top-level product menu '{$group_slug}' is already registered; duplicates are rejected." );
			return false;
		}

		if ( [] === $pages ) {
			self::diagnostic( "Top-level product menu '{$group_slug}' must register at least one page." );
			return false;
		}

		$normalized_pages = [];
		$normalized_requirements = [];
		$seen = [];

		foreach ( $pages as $page ) {
			if ( ! $page instanceof Page ) {
				self::diagnostic( "Top-level product menu '{$group_slug}' received a value that does not implement the Core Admin Page contract." );
				return false;
			}

			$slug = $page->slug();
			if ( ! self::valid_slug( $slug ) ) {
				self::diagnostic( "Top-level product page slug '{$slug}' is invalid. Use lower-case kebab-case." );
				return false;
			}
			if ( isset( $seen[ $slug ] ) || isset( self::$pages[ $slug ] ) ) {
				self::diagnostic( "Top-level product page '{$slug}' is already registered; duplicates are rejected." );
				return false;
			}
			if ( '' === $page->capability() || sanitize_key( $page->capability() ) !== $page->capability() ) {
				self::diagnostic( "Top-level product page '{$slug}' has an invalid capability." );
				return false;
			}
			if ( null !== $page->position() && $page->position() < 0 ) {
				self::diagnostic( "Top-level product page '{$slug}' cannot use a negative position." );
				return false;
			}

			$normalized = PageRegistry::normalize_semantic_requirements( $requirements[ $slug ] ?? [], $slug );
			if ( null === $normalized ) {
				return false;
			}

			$seen[ $slug ] = true;
			$normalized_pages[ $slug ] = $page;
			$normalized_requirements[ $slug ] = $normalized;
		}

		if ( ! isset( $normalized_pages[ $group_slug ] ) ) {
			self::diagnostic( "Top-level product menu '{$group_slug}' must include a landing page with the same slug." );
			return false;
		}

		$unknown_requirement_pages = array_diff( array_keys( $requirements ), array_keys( $normalized_pages ) );
		if ( [] !== $unknown_requirement_pages ) {
			self::diagnostic( "Top-level product menu '{$group_slug}' contains requirements for an unknown page." );
			return false;
		}

		self::$groups[ $group_slug ] = $group;
		foreach ( $normalized_pages as $slug => $page ) {
			self::$pages[ $slug ] = $page;
			self::$page_groups[ $slug ] = $group_slug;
			self::$requirements[ $slug ] = $normalized_requirements[ $slug ];
		}

		self::ensure_hooks();
		return true;
	}

	public static function get( string $slug ): ?Page {
		return self::$pages[ $slug ] ?? null;
	}

	public static function group( string $slug ): ?MenuGroup {
		return self::$groups[ $slug ] ?? null;
	}

	public static function hook_suffix( string $page_slug ): string {
		return self::$hooks[ $page_slug ] ?? '';
	}

	/**
	 * Finalize WordPress menu wiring after every cb_core_register_pages callback.
	 *
	 * @internal
	 */
	public static function finalize(): void {
		if ( self::$finalized ) {
			return;
		}
		self::$finalized = true;

		foreach ( array_keys( self::$pages ) as $slug ) {
			if ( null !== PageRegistry::get( $slug ) ) {
				self::diagnostic( "Admin page slug '{$slug}' is registered in both PageRegistry and MenuGroupRegistry. The top-level product menu was not wired." );
				return;
			}
		}

		foreach ( self::$groups as $group_slug => $group ) {
			$landing_hook = add_menu_page(
				$group->title(),
				$group->menu_title(),
				$group->capability(),
				$group_slug,
				static function () use ( $group_slug ): void {
					self::render_landing( $group_slug );
				},
				$group->icon(),
				$group->position()
			);

			if ( $landing_hook ) {
				self::$hooks[ $group_slug ] = $landing_hook;
				self::$landing_hooks[ $landing_hook ] = $group_slug;
			}

			foreach ( self::pages_for_group( $group_slug ) as $page ) {
				$is_landing = $page->slug() === $group_slug;
				$suffix = add_submenu_page(
					$group_slug,
					$page->title(),
					$page->menu_title(),
					$page->capability(),
					$page->slug(),
					$is_landing ? '' : [ $page, 'render' ],
					$page->position()
				);
				if ( $suffix && ! $is_landing ) {
					self::$hooks[ $page->slug() ] = $suffix;
				}
			}
		}
	}

	/** Enqueue semantic requirements for a top-level product page hook. */
	public static function enqueue_requirements_for_hook( string $hook ): void {
		if ( isset( self::$landing_hooks[ $hook ] ) ) {
			$page = self::accessible_landing_page( self::$landing_hooks[ $hook ] );
			if ( null !== $page ) {
				self::enqueue_page_requirements( $page->slug(), $hook );
			}
			return;
		}

		$slug = array_search( $hook, self::$hooks, true );
		if ( false !== $slug ) {
			self::enqueue_page_requirements( $slug, $hook );
		}
	}

	/** Reset registry state - tests only. */
	public static function _reset_for_testing(): void {
		self::$groups = [];
		self::$pages = [];
		self::$page_groups = [];
		self::$requirements = [];
		self::$hooks = [];
		self::$landing_hooks = [];
		self::$finalized = false;
	}

	private static function ensure_hooks(): void {
		if ( ! self::$finalizer_hooked ) {
			self::$finalizer_hooked = true;
			add_action( 'cb_core_register_pages', [ self::class, 'finalize' ], PHP_INT_MAX );
		}
		if ( ! self::$enqueue_hooked ) {
			self::$enqueue_hooked = true;
			add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_requirements_for_hook' ], 20 );
		}
	}

	private static function render_landing( string $group_slug ): void {
		$page = self::accessible_landing_page( $group_slug );
		if ( null === $page ) {
			wp_die(
				esc_html__( 'You do not have permission to access this product area.', 'core-blueprint' ),
				esc_html__( 'Forbidden', 'core-blueprint' ),
				[ 'response' => 403 ]
			);
		}
		$page->render();
	}

	private static function accessible_landing_page( string $group_slug ): ?Page {
		foreach ( self::pages_for_group( $group_slug ) as $page ) {
			if ( current_user_can( $page->capability() ) ) {
				return $page;
			}
		}
		return null;
	}

	/** @return Page[] */
	private static function pages_for_group( string $group_slug ): array {
		$pages = [];
		foreach ( self::$pages as $slug => $page ) {
			if ( ( self::$page_groups[ $slug ] ?? '' ) === $group_slug ) {
				$pages[] = $page;
			}
		}
		usort( $pages, static function ( Page $a, Page $b ): int {
			$pa = $a->position();
			$pb = $b->position();
			if ( null === $pa && null === $pb ) {
				return strcmp( $a->slug(), $b->slug() );
			}
			if ( null === $pa ) {
				return 1;
			}
			if ( null === $pb ) {
				return -1;
			}
			if ( $pa === $pb ) {
				return strcmp( $a->slug(), $b->slug() );
			}
			return $pa <=> $pb;
		} );
		return $pages;
	}

	private static function enqueue_page_requirements( string $slug, string $hook ): void {
		if ( isset( self::$requirements[ $slug ] ) ) {
			PageRegistry::enqueue_semantic_requirements( self::$requirements[ $slug ], $hook );
		}
	}

	private static function valid_group( MenuGroup $group ): bool {
		$slug = $group->slug();
		if ( ! self::valid_slug( $slug ) ) {
			self::diagnostic( "Top-level product menu slug '{$slug}' is invalid. Use lower-case kebab-case." );
			return false;
		}
		if ( '' === trim( $group->title() ) || '' === trim( $group->menu_title() ) ) {
			self::diagnostic( "Top-level product menu '{$slug}' requires non-empty translated labels." );
			return false;
		}
		if ( '' === $group->capability() || sanitize_key( $group->capability() ) !== $group->capability() ) {
			self::diagnostic( "Top-level product menu '{$slug}' has an invalid capability." );
			return false;
		}
		if ( null !== $group->position() && $group->position() < 0 ) {
			self::diagnostic( "Top-level product menu '{$slug}' cannot use a negative position." );
			return false;
		}
		return true;
	}

	private static function valid_slug( string $slug ): bool {
		return 1 === preg_match( '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $slug );
	}

	private static function diagnostic( string $message ): void {
		_doing_it_wrong( self::class, $message, CB_CORE_VERSION );
	}

	private function __construct() {}
}
