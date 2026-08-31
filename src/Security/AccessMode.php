<?php
declare(strict_types=1);
/**
 * AccessMode
 *
 * Core Blueprint's public-access policy. Four mutually exclusive modes are
 * supported:
 *
 * - Public: normal WordPress front-end behaviour.
 * - Coming Soon: one published WordPress page remains available with HTTP 200;
 *   other public front-end URLs redirect to it with HTTP 302.
 * - Maintenance: the selected page is rendered at the originally requested URL
 *   while the response remains HTTP 503 Service Unavailable. An optional
 *   Retry-After header can advertise the expected return time.
 * - Admin-Only: anonymous public front-end requests return HTTP 403.
 *
 * Logged-in users, recovery paths and machine/infrastructure requests can bypass
 * enforcement. Extensions may register an additional request-level bypass via
 * register_bypass() without having to duplicate Access Mode semantics.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Security;

use CB\Core\Admin\Admin;
use CB\Core\Ajax\Request;
use CB\Core\Log\AuditLog;

use DateTimeImmutable;
use DateTimeZone;
use WP_Admin_Bar;
use WP_Post;
use WP_Query;

use function absint;
use function add_action;
use function add_filter;
use function admin_url;
use function apply_filters;
use function basename;
use function class_exists;
use function current_user_can;
use function define;
use function defined;
use function do_action;
use function esc_html__;
use function get_option;
use function get_permalink;
use function get_post;
use function get_queried_object_id;
use function get_status_header_desc;
use function header;
use function in_array;
use function is_admin;
use function is_array;
use function is_user_logged_in;
use function nocache_headers;
use function sanitize_key;
use function sanitize_text_field;
use function status_header;
use function time;
use function update_option;
use function wp_doing_ajax;
use function wp_doing_cron;
use function wp_get_current_user;
use function wp_json_encode;
use function wp_safe_redirect;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_timezone;
use function wp_unslash;

use const ABSPATH;

use Throwable;


defined( 'ABSPATH' ) || exit;

final class AccessMode {

	public const OPTION_KEY        = 'cb_core_access_mode';
	public const CONFIG_OPTION_KEY = 'cb_core_access_mode_config';
	public const CONFIG_SCHEMA     = 1;

	public const MODE_PUBLIC       = 'public';
	public const MODE_COMING_SOON  = 'coming_soon';
	public const MODE_MAINTENANCE  = 'maintenance';
	public const MODE_ADMIN_ONLY   = 'admin_only';

	/** @var array<string,callable(string):bool> */
	private static array $bypass_callbacks = [];

	/** True once the maintenance request's main query was switched to its page. */
	private static bool $maintenance_query_prepared = false;

	public static function boot(): void {
		// Management endpoints are only relevant on an actual admin-ajax.php
		// request. `is_admin()` alone is insufficient because WordPress also
		// reports AJAX requests as admin.
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			add_action( 'wp_ajax_cb_core_set_access_mode', [ __CLASS__, 'ajax_set_mode' ] );
			add_action( 'wp_ajax_cb_core_access_mode_search_pages', [ __CLASS__, 'ajax_search_pages' ] );
			return;
		}

		// Public mode has no enforcement work. Configuration remains reachable
		// through the AJAX boundary above so the mode can always be enabled again.
		if ( self::is_public() ) {
			return;
		}

		add_action( 'pre_get_posts', [ __CLASS__, 'maybe_prepare_maintenance_query' ], 0 );
		add_filter( 'redirect_canonical', [ __CLASS__, 'maybe_disable_maintenance_canonical' ], 10, 2 );
		add_action( 'template_redirect', [ __CLASS__, 'maybe_enforce' ], 0 );
		add_action( 'admin_bar_menu', [ __CLASS__, 'admin_bar_notice' ], 100 );
	}

	/**
	 * Register a request-level bypass callback.
	 *
	 * The callback receives the effective access mode and must return true only
	 * for the current request that should remain reachable. This is intended for
	 * webhook or machine endpoints that do not run through the REST API.
	 *
	 * @param string               $id       Stable extension-owned identifier.
	 * @param callable(string):bool $callback Request predicate.
	 */
	public static function register_bypass( string $id, callable $callback ): void {
		$id = sanitize_key( $id );
		if ( '' === $id ) {
			return;
		}
		self::$bypass_callbacks[ $id ] = $callback;
	}

	/** @return string[] */
	public static function modes(): array {
		return [
			self::MODE_PUBLIC,
			self::MODE_COMING_SOON,
			self::MODE_MAINTENANCE,
			self::MODE_ADMIN_ONLY,
		];
	}


	// ─── Current mode / configuration ─────────────────────────────────────

	public static function current(): string {
		$mode = (string) get_option( self::OPTION_KEY, self::MODE_PUBLIC );
		return in_array( $mode, self::modes(), true ) ? $mode : self::MODE_PUBLIC;
	}

	public static function is_public(): bool {
		return self::MODE_PUBLIC === self::current();
	}

	public static function is_coming_soon(): bool {
		return self::MODE_COMING_SOON === self::current();
	}

	public static function is_maintenance(): bool {
		return self::MODE_MAINTENANCE === self::current();
	}

	public static function is_admin_only(): bool {
		return self::MODE_ADMIN_ONLY === self::current();
	}

	/** @return array{schema_version:int,coming_soon_page_id:int,coming_soon_indexable:bool,maintenance_page_id:int,maintenance_until_date:string,maintenance_until_time:string} */
	public static function config(): array {
		$defaults = self::default_config();
		$stored   = get_option( self::CONFIG_OPTION_KEY, [] );
		if ( ! is_array( $stored ) ) {
			return $defaults;
		}

		$config = array_merge( $defaults, $stored );
		$config['schema_version']            = self::CONFIG_SCHEMA;
		$config['coming_soon_page_id']       = absint( $config['coming_soon_page_id'] ?? 0 );
		$config['coming_soon_indexable']     = ! empty( $config['coming_soon_indexable'] );
		$config['maintenance_page_id']       = absint( $config['maintenance_page_id'] ?? 0 );
		$config['maintenance_until_date']    = self::sanitize_date( (string) ( $config['maintenance_until_date'] ?? '' ) );
		$config['maintenance_until_time']    = self::sanitize_time( (string) ( $config['maintenance_until_time'] ?? '' ) );
		return $config;
	}

	/** @return array{schema_version:int,coming_soon_page_id:int,coming_soon_indexable:bool,maintenance_page_id:int,maintenance_until_date:string,maintenance_until_time:string} */
	private static function default_config(): array {
		return [
			'schema_version'         => self::CONFIG_SCHEMA,
			'coming_soon_page_id'    => 0,
			'coming_soon_indexable'  => true,
			'maintenance_page_id'    => 0,
			'maintenance_until_date' => '',
			'maintenance_until_time' => '',
		];
	}

	public static function mode_label( ?string $mode = null ): string {
		$mode = $mode ?? self::current();
		return match ( $mode ) {
			self::MODE_COMING_SOON => __( 'Coming Soon', 'core-blueprint' ),
			self::MODE_MAINTENANCE => __( 'Maintenance', 'core-blueprint' ),
			self::MODE_ADMIN_ONLY  => __( 'Admin-Only', 'core-blueprint' ),
			default                => __( 'Public', 'core-blueprint' ),
		};
	}

	public static function status_label( ?string $mode = null ): string {
		$mode = $mode ?? self::current();
		return match ( $mode ) {
			self::MODE_COMING_SOON => __( 'Coming Soon - pre-launch page active', 'core-blueprint' ),
			self::MODE_MAINTENANCE => __( 'Maintenance - site temporarily unavailable', 'core-blueprint' ),
			self::MODE_ADMIN_ONLY  => __( 'Admin-Only Mode - site locked', 'core-blueprint' ),
			default                => __( 'Public Mode - site live', 'core-blueprint' ),
		};
	}

	/**
	 * Return the selected page in ObjectPicker's public value shape.
	 *
	 * @return array<int,array{id:int,label:string,meta:string}>
	 */
	public static function picker_selected_page( int $page_id ): array {
		$page = self::valid_landing_page( $page_id );
		if ( ! $page ) {
			return [];
		}

		return [ [
			'id'    => (int) $page->ID,
			'label' => (string) get_the_title( $page ),
			'meta'  => __( 'Published page', 'core-blueprint' ),
		] ];
	}

	// ─── Request bypass boundary ──────────────────────────────────────────

	public static function should_bypass_request( ?string $mode = null ): bool {
		$mode = $mode ?? self::current();

		if ( self::MODE_PUBLIC === $mode ) {
			return true;
		}
		if ( is_user_logged_in() || is_admin() ) {
			return true;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return true;
		}
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return true;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return true;
		}
		// WooCommerce gateway/webhook callbacks commonly use wc-api/wc-ajax
		// front-controller query arguments instead of REST/AJAX entrypoints.
		// Treat those standard machine routes as infrastructure so activating
		// Maintenance cannot interrupt payment-provider callbacks.
		if ( isset( $_GET['wc-api'] ) || isset( $_GET['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing context.
			return true;
		}
		if ( class_exists( Failsafe::class ) && Failsafe::is_bypassed() ) {
			return true;
		}

		$script = isset( $_SERVER['SCRIPT_NAME'] )
			? basename( (string) $_SERVER['SCRIPT_NAME'] )
			: '';
		if ( 'wp-login.php' === $script ) {
			return true;
		}

		foreach ( self::$bypass_callbacks as $callback ) {
			try {
				if ( true === $callback( $mode ) ) {
					return true;
				}
			} catch ( Throwable $e ) {
				// A faulty extension bypass must never disable Access Mode globally.
				continue;
			}
		}

		/**
		 * Filters whether Access Mode should stand down for the current request.
		 *
		 * Prefer register_bypass() for new integrations; this filter remains a
		 * lightweight compatibility/advanced-policy boundary.
		 *
		 * @param bool   $bypass Current decision.
		 * @param string $mode   Effective Access Mode.
		 */
		return (bool) apply_filters( 'cb_core_access_mode_bypass_request', false, $mode );
	}

	// ─── Front-end enforcement ────────────────────────────────────────────

	/**
	 * In Maintenance mode, render the configured WordPress page through the
	 * normal theme/template stack while preserving the originally requested URL.
	 */
	public static function maybe_prepare_maintenance_query( WP_Query $query ): void {
		if ( ! $query->is_main_query() || is_admin() ) {
			return;
		}

		$mode = self::current();
		if ( self::MODE_MAINTENANCE !== $mode || self::should_bypass_request( $mode ) ) {
			return;
		}

		$config = self::config();
		$page   = self::valid_landing_page( (int) $config['maintenance_page_id'] );
		if ( ! $page ) {
			return;
		}

		$query->set( 'page_id', (int) $page->ID );
		$query->set( 'p', 0 );
		$query->set( 'name', '' );
		$query->set( 'pagename', '' );
		$query->set( 'post_type', 'page' );
		$query->set( 'error', '' );

		// Query flags were derived from the original URL before pre_get_posts.
		// Reset the relevant public-view flags so template selection follows the
		// configured maintenance page rather than the original route.
		$query->is_404               = false;
		$query->is_home              = false;
		$query->is_archive           = false;
		$query->is_search            = false;
		$query->is_feed              = false;
		$query->is_date              = false;
		$query->is_year              = false;
		$query->is_month             = false;
		$query->is_day               = false;
		$query->is_time              = false;
		$query->is_author            = false;
		$query->is_category          = false;
		$query->is_tag               = false;
		$query->is_tax               = false;
		$query->is_post_type_archive = false;
		$query->is_attachment        = false;
		$query->is_privacy_policy    = false;
		$query->is_singular          = true;
		$query->is_page              = true;
		$query->is_single            = false;
		$query->queried_object       = null;
		$query->queried_object_id    = 0;

		self::$maintenance_query_prepared = true;
	}

	/** Prevent WordPress from canonical-redirecting the maintenance page URL. */
	public static function maybe_disable_maintenance_canonical( $redirect_url, $requested_url ) {
		$mode = self::current();
		if ( self::MODE_MAINTENANCE === $mode && ! self::should_bypass_request( $mode ) ) {
			return false;
		}
		return $redirect_url;
	}

	public static function maybe_enforce(): void {
		$mode = self::current();
		if ( self::MODE_PUBLIC === $mode || self::should_bypass_request( $mode ) ) {
			return;
		}

		self::mark_request_uncacheable();

		switch ( $mode ) {
			case self::MODE_COMING_SOON:
				self::enforce_coming_soon();
				return;

			case self::MODE_MAINTENANCE:
				self::enforce_maintenance();
				return;

			case self::MODE_ADMIN_ONLY:
				self::enforce_admin_only();
				return;
		}
	}

	private static function enforce_coming_soon(): void {
		$config = self::config();
		$page   = self::valid_landing_page( (int) $config['coming_soon_page_id'] );
		if ( ! $page ) {
			self::render_service_unavailable_fallback( __( 'Coming soon.', 'core-blueprint' ) );
		}

		if ( (int) get_queried_object_id() === (int) $page->ID ) {
			status_header( 200 );
			if ( empty( $config['coming_soon_indexable'] ) ) {
				header( 'X-Robots-Tag: noindex, follow' );
			}
			return;
		}

		$url = get_permalink( $page );
		if ( ! is_string( $url ) || '' === $url ) {
			self::render_service_unavailable_fallback( __( 'Coming soon.', 'core-blueprint' ) );
		}

		wp_safe_redirect( $url, 302, 'Core Blueprint Access Mode' );
		exit;
	}

	private static function enforce_maintenance(): void {
		$config = self::config();
		$page   = self::valid_landing_page( (int) $config['maintenance_page_id'] );
		if ( ! $page || ! self::$maintenance_query_prepared ) {
			self::render_service_unavailable_fallback( __( 'Temporarily unavailable for maintenance.', 'core-blueprint' ) );
		}

		status_header( 503 );
		$retry_after = self::retry_after_header( $config );
		if ( '' !== $retry_after ) {
			header( 'Retry-After: ' . $retry_after );
		}
	}

	private static function enforce_admin_only(): void {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		echo "Forbidden.\n";
		exit;
	}

	private static function render_service_unavailable_fallback( string $message ): void {
		status_header( 503 );
		header( 'Content-Type: text/html; charset=UTF-8' );
		$title = __( 'Service unavailable', 'core-blueprint' );
		echo '<!doctype html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $title ) . '</title></head><body><main><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p></main></body></html>';
		exit;
	}

	private static function mark_request_uncacheable(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
	}

	/**
	 * @param array<string,mixed>|null $config
	 */
	private static function retry_after_header( ?array $config = null ): string {
		$config = $config ?? self::config();
		$date   = (string) ( $config['maintenance_until_date'] ?? '' );
		$time   = (string) ( $config['maintenance_until_time'] ?? '' );
		$at     = self::maintenance_until_datetime( $date, $time );
		if ( ! $at || $at->getTimestamp() <= time() ) {
			return '';
		}

		return $at->setTimezone( new DateTimeZone( 'GMT' ) )->format( 'D, d M Y H:i:s \G\M\T' );
	}

	// ─── Admin bar notice ─────────────────────────────────────────────────

	public static function admin_bar_notice( WP_Admin_Bar $bar ): void {
		$mode = self::current();
		if ( self::MODE_PUBLIC === $mode || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$title = match ( $mode ) {
			self::MODE_COMING_SOON => '🚀 ' . esc_html__( 'Coming Soon', 'core-blueprint' ),
			self::MODE_MAINTENANCE => '🛠 ' . esc_html__( 'Maintenance', 'core-blueprint' ),
			default                => '🔒 ' . esc_html__( 'Admin-Only Mode', 'core-blueprint' ),
		};
		$hint = match ( $mode ) {
			self::MODE_COMING_SOON => __( 'Pre-launch page is active. Click to manage.', 'core-blueprint' ),
			self::MODE_MAINTENANCE => __( 'Maintenance response is active. Click to manage.', 'core-blueprint' ),
			default                => __( 'Front-end is locked. Click to manage.', 'core-blueprint' ),
		};

		$bar->add_node( [
			'id'    => 'cb-core-access-mode-notice',
			'title' => $title,
			'href'  => admin_url( 'admin.php?page=' . Admin::SAFEGUARDS_SLUG . '&tab=access-mode' ),
			'meta'  => [
				'class' => 'cb-core-access-mode-bar-notice',
				'title' => $hint,
			],
		] );
	}

	// ─── AJAX configuration ───────────────────────────────────────────────

	public static function ajax_set_mode(): void {
		Request::nonce( 'cb_core_admin' );
		Request::cap( 'manage_options' );

		$requested = Request::sanitize_key( 'mode', self::modes() );
		$previous  = self::current();
		$before    = self::config();

		$config = [
			'schema_version'         => self::CONFIG_SCHEMA,
			'coming_soon_page_id'    => Request::int( 'coming_soon_page_id', (int) $before['coming_soon_page_id'] ),
			'coming_soon_indexable'  => isset( $_POST['coming_soon_indexable'] )
				? filter_var( wp_unslash( $_POST['coming_soon_indexable'] ), FILTER_VALIDATE_BOOLEAN )
				: (bool) $before['coming_soon_indexable'], // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
			'maintenance_page_id'    => Request::int( 'maintenance_page_id', (int) $before['maintenance_page_id'] ),
			'maintenance_until_date' => self::sanitize_date( Request::text( 'maintenance_until_date', (string) $before['maintenance_until_date'] ) ),
			'maintenance_until_time' => self::sanitize_time( Request::text( 'maintenance_until_time', (string) $before['maintenance_until_time'] ) ),
		];
		$config['coming_soon_page_id'] = absint( $config['coming_soon_page_id'] );
		$config['maintenance_page_id'] = absint( $config['maintenance_page_id'] );

		$error = self::validate_config_for_mode( $requested, $config );
		if ( '' !== $error ) {
			wp_send_json_error( [ 'message' => $error ], 400 );
		}

		if ( ! self::persist_option( self::CONFIG_OPTION_KEY, $config ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not save Access Mode settings.', 'core-blueprint' ) ], 500 );
		}
		if ( ! self::persist_option( self::OPTION_KEY, $requested ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not activate the selected Access Mode.', 'core-blueprint' ) ], 500 );
		}

		$config_changed = $before !== $config;
		$mode_changed   = $previous !== $requested;

		if ( ( $mode_changed || $config_changed ) && class_exists( AuditLog::class ) ) {
			$user = wp_get_current_user();
			AuditLog::log(
				'access_mode.changed',
				'notice',
				[
					'from'                     => $previous,
					'to'                       => $requested,
					'configuration_changed'    => $config_changed,
					'coming_soon_page_id'      => (int) $config['coming_soon_page_id'],
					'coming_soon_indexable'    => (bool) $config['coming_soon_indexable'],
					'maintenance_page_id'      => (int) $config['maintenance_page_id'],
					'retry_after_configured'   => '' !== $config['maintenance_until_date'] && '' !== $config['maintenance_until_time'],
					'actor'                    => 'admin:' . ( $user ? $user->user_login : 'unknown' ),
				]
			);
		}

		if ( $mode_changed ) {
			/**
			 * Fires after the effective Access Mode changes.
			 *
			 * @param string $new      New mode.
			 * @param string $previous Previous mode.
			 */
			do_action( 'cb_core_access_mode_changed', $requested, $previous );
		}
		if ( $config_changed ) {
			/**
			 * Fires after Access Mode supporting configuration changes.
			 *
			 * @param array<string,mixed> $config New normalized configuration.
			 * @param array<string,mixed> $before Previous configuration.
			 */
			do_action( 'cb_core_access_mode_settings_changed', $config, $before );
		}

		wp_send_json_success( [
			'mode'    => $requested,
			'message' => self::activation_message( $requested ),
			'status'  => self::status_label( $requested ),
		] );
	}

	public static function ajax_search_pages(): void {
		Request::nonce( 'cb_core_admin', '_ajax_nonce' );
		Request::cap( 'manage_options' );
		$search = Request::text( 'search' );
		if ( strlen( $search ) < 2 ) {
			wp_send_json_success( [ 'items' => [] ] );
		}

		$query = new WP_Query( [
			'post_type'           => 'page',
			'post_status'         => 'publish',
			'has_password'        => false,
			's'                   => $search,
			'posts_per_page'      => 20,
			'orderby'             => 'title',
			'order'               => 'ASC',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		] );

		$items = [];
		foreach ( $query->posts as $page ) {
			if ( ! $page instanceof WP_Post ) {
				continue;
			}
			$items[] = [
				'id'    => (int) $page->ID,
				'label' => (string) get_the_title( $page ),
				'meta'  => __( 'Published page', 'core-blueprint' ),
			];
		}

		wp_send_json_success( [ 'items' => $items ] );
	}

	/** @param array<string,mixed> $config */
	private static function validate_config_for_mode( string $mode, array $config ): string {
		if ( self::MODE_COMING_SOON === $mode && ! self::valid_landing_page( (int) $config['coming_soon_page_id'] ) ) {
			return __( 'Choose a published, non-password-protected page before activating Coming Soon.', 'core-blueprint' );
		}
		if ( self::MODE_MAINTENANCE === $mode && ! self::valid_landing_page( (int) $config['maintenance_page_id'] ) ) {
			return __( 'Choose a published, non-password-protected page before activating Maintenance.', 'core-blueprint' );
		}

		$date = (string) ( $config['maintenance_until_date'] ?? '' );
		$time = (string) ( $config['maintenance_until_time'] ?? '' );
		if ( ( '' === $date ) !== ( '' === $time ) ) {
			return __( 'Expected back online needs both a date and a time, or neither.', 'core-blueprint' );
		}
		if ( '' !== $date && ! self::maintenance_until_datetime( $date, $time ) ) {
			return __( 'Enter a valid expected return date and time.', 'core-blueprint' );
		}
		if ( self::MODE_MAINTENANCE === $mode && '' !== $date ) {
			$at = self::maintenance_until_datetime( $date, $time );
			if ( ! $at || $at->getTimestamp() <= time() ) {
				return __( 'Expected back online must be in the future when Maintenance is activated.', 'core-blueprint' );
			}
		}

		return '';
	}

	private static function valid_landing_page( int $page_id ): ?WP_Post {
		if ( $page_id <= 0 ) {
			return null;
		}
		$page = get_post( $page_id );
		if ( ! $page instanceof WP_Post || 'page' !== $page->post_type || 'publish' !== $page->post_status || '' !== (string) $page->post_password ) {
			return null;
		}
		return $page;
	}

	private static function persist_option( string $key, $value ): bool {
		$current = get_option( $key, null );
		if ( $current === $value ) {
			return true;
		}
		if ( update_option( $key, $value, false ) ) {
			return true;
		}
		return get_option( $key, null ) === $value;
	}

	private static function sanitize_date( string $value ): string {
		$value = sanitize_text_field( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}
		return $value;
	}

	private static function sanitize_time( string $value ): string {
		$value = sanitize_text_field( $value );
		if ( '' === $value ) {
			return '';
		}
		return 1 === preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : '';
	}

	private static function maintenance_until_datetime( string $date, string $time ): ?DateTimeImmutable {
		if ( '' === $date || '' === $time ) {
			return null;
		}
		$timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
		$at       = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, $timezone );
		if ( ! $at || $at->format( 'Y-m-d H:i' ) !== $date . ' ' . $time ) {
			return null;
		}
		return $at;
	}

	private static function activation_message( string $mode ): string {
		return match ( $mode ) {
			self::MODE_COMING_SOON => __( 'Coming Soon activated. Public URLs now lead visitors to the selected pre-launch page.', 'core-blueprint' ),
			self::MODE_MAINTENANCE => __( 'Maintenance activated. Public requests now return 503 while rendering the selected maintenance page.', 'core-blueprint' ),
			self::MODE_ADMIN_ONLY  => __( 'Admin-Only Mode enabled. Public visitors now receive 403.', 'core-blueprint' ),
			default                => __( 'Public Mode enabled. The site is live.', 'core-blueprint' ),
		};
	}
}
