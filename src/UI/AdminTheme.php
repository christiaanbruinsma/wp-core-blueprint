<?php
declare(strict_types=1);
/**
 * Admin Theme Engine.
 *
 * Core Blueprint Base owns one Light/Dark presentation state for the complete
 * WordPress admin. WordPress-native UI is adapted centrally by Base, while
 * extensions and third parties consume semantic tokens instead of detecting a
 * specific built-in theme slug.
 *
 * Public integration surface:
 *   - AdminTheme::theme() / ::mode()
 *   - AdminTheme::register_screen( $hook_suffix ) for declared compatibility
 *   - `cb_admin_themes` to register partner themes (owned by Themes)
 *   - `cb_admin_theme_apply` as a developer safety valve for incompatible apps
 *   - `cb_admin_theme_enqueue` for theme-aware extension assets
 *   - browser event `cb:admin-theme-change`
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\UI;

use CB\Core\Themes;

defined( 'ABSPATH' ) || exit;

final class AdminTheme {

	private static bool $initialized = false;

	/** @var array<string, true> */
	private static array $registered_screens = [];

	/** Boot the global wp-admin theme engine once per normal admin request. */
	public static function init(): void {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		// Core historically attached CB-screen-only DOM hooks directly from
		// Core::init_hooks(). Take ownership after plugin bootstrap so one global
		// presentation path remains authoritative without changing request boot.
		add_action( 'admin_init', [ self::class, 'take_theme_hook_ownership' ], 0 );

		add_action( 'admin_head', [ self::class, 'emit_prepaint_hooks' ], 0 );
		add_filter( 'admin_body_class', [ self::class, 'filter_admin_body_class' ], 5 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ], 0 );
	}

	/**
	 * Declare an admin screen as intentionally compatible with the public Core
	 * Blueprint theme contract. Registration is not required for theme state;
	 * it is a compatibility declaration and hook point for extension tooling.
	 */
	public static function register_screen( string $hook_suffix ): void {
		$hook_suffix = trim( $hook_suffix );
		if ( '' === $hook_suffix ) {
			return;
		}

		self::$registered_screens[ $hook_suffix ] = true;

		/**
		 * Fires when an extension declares an admin screen compatible with the
		 * Core Blueprint Admin Theme contract.
		 *
		 * @param string $hook_suffix WordPress admin hook suffix.
		 */
		do_action( 'cb_admin_theme_screen_registered', $hook_suffix );
	}

	/** Is the supplied/current admin screen explicitly registered? */
	public static function is_registered_screen( string $hook_suffix = '' ): bool {
		if ( '' === $hook_suffix ) {
			$hook_suffix = self::current_hook_suffix();
		}

		return '' !== $hook_suffix && isset( self::$registered_screens[ $hook_suffix ] );
	}

	/** Concrete active theme slug. */
	public static function theme(): string {
		return Themes::current();
	}

	/** Server-resolved active mode: dark, light, or custom. */
	public static function mode(): string {
		return Themes::current_mode();
	}

	/**
	 * Remove the previous Core-admin-only DOM hooks. AdminTheme owns the same
	 * concerns globally now; keeping both paths would duplicate pre-paint output.
	 */
	public static function take_theme_hook_ownership(): void {
		remove_action( 'admin_head', [ Themes::class, 'emit_prepaint_hooks' ], 1 );
		remove_filter( 'admin_body_class', [ Themes::class, 'filter_admin_body_class' ], 10 );
	}

	/** Enqueue semantic tokens, WordPress adapter CSS and the browser API. */
	public static function enqueue_assets( string $hook_suffix = '' ): void {
		if ( ! self::applies( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			'cb-core-css-tokens',
			CB_CORE_URL . 'assets/css/tokens.css',
			[],
			CB_CORE_VERSION
		);

		wp_enqueue_style(
			'cb-core-css-admin-theme',
			CB_CORE_URL . 'assets/css/admin-theme.css',
			[ 'cb-core-css-tokens' ],
			CB_CORE_VERSION
		);

		wp_enqueue_script(
			'cb-core-admin-theme',
			CB_CORE_URL . 'assets/js/core/admin-theme.js',
			[],
			CB_CORE_VERSION,
			true
		);

		$slug   = self::theme();
		$themes = Themes::all();
		$css    = isset( $themes[ $slug ]['css_url'] ) ? (string) $themes[ $slug ]['css_url'] : '';

		if ( '' !== $css ) {
			wp_enqueue_style(
				'cb-core-admin-theme-' . sanitize_key( $slug ),
				$css,
				[ 'cb-core-css-tokens', 'cb-core-css-admin-theme' ],
				CB_CORE_VERSION
			);
		}

		/**
		 * Fires after Base enqueues the canonical admin-theme assets.
		 *
		 * @param string $hook_suffix Current WordPress admin hook suffix.
		 * @param string $theme       Concrete active theme slug.
		 * @param string $mode        Server-resolved theme mode.
		 * @param bool   $registered  Whether the screen declared compatibility.
		 */
		do_action(
			'cb_admin_theme_enqueue',
			$hook_suffix,
			$slug,
			self::mode(),
			self::is_registered_screen( $hook_suffix )
		);
	}

	/**
	 * Apply theme state before body paint. Auto mode resolves against the
	 * browser preference and mirrors state onto html + body.
	 */
	public static function emit_prepaint_hooks(): void {
		if ( ! self::applies() ) {
			return;
		}

		$slug = self::theme();
		$mode = self::mode();
		$auto = Themes::is_auto_mode();
		?>
<script id="cb-core-admin-theme-prepaint">
(function(){
	var initialTheme = <?php echo wp_json_encode( $slug ); ?>;
	var initialMode = <?php echo wp_json_encode( $mode ); ?>;
	var autoMode = <?php echo $auto ? 'true' : 'false'; ?>;
	var darkTheme = <?php echo wp_json_encode( Themes::SLUG_CB_DARK ); ?>;
	var lightTheme = <?php echo wp_json_encode( Themes::SLUG_CB_LIGHT ); ?>;

	function resolvedTheme() {
		if (!autoMode) return initialTheme;
		if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) return lightTheme;
		return darkTheme;
	}

	function resolvedMode(theme) {
		if (theme === lightTheme) return 'light';
		if (theme === darkTheme) return 'dark';
		return initialMode;
	}

	function setState(target, theme, mode) {
		if (!target) return;
		target.setAttribute('data-cb-theme', theme);
		if (mode === 'dark' || mode === 'light' || mode === 'custom') {
			target.setAttribute('data-cb-mode', mode);
		} else {
			target.removeAttribute('data-cb-mode');
		}
	}

	function apply() {
		var theme = resolvedTheme();
		var mode = resolvedMode(theme);
		setState(document.documentElement, theme, mode);
		setState(document.body, theme, mode);
	}

	apply();
	if (!document.body) {
		document.addEventListener('DOMContentLoaded', apply, {once:true});
	}

	if (autoMode && window.matchMedia) {
		var query = window.matchMedia('(prefers-color-scheme: light)');
		try { query.addEventListener('change', apply); }
		catch (e) { if (query.addListener) query.addListener(apply); }
	}
})();
</script>
		<?php
	}

	/** Add one stable global scope class plus optional compatibility marker. */
	public static function filter_admin_body_class( string $classes ): string {
		if ( ! self::applies() ) {
			return $classes;
		}

		$classes .= ' cb-admin-theme';
		if ( self::is_registered_screen() ) {
			$classes .= ' cb-admin-theme-compatible';
		}

		/**
		 * Filter additional body classes for the Admin Theme presentation.
		 *
		 * @param string $classes Current body class string.
		 * @param string $theme   Active theme slug.
		 * @param string $mode    Server-resolved mode.
		 */
		$classes = (string) apply_filters( 'cb_admin_theme_body_classes', $classes, self::theme(), self::mode() );
		return trim( $classes );
	}

	/**
	 * Global by default. Developers of self-contained admin applications may
	 * return false for a known-incompatible screen without adding a user-facing
	 * presentation mode or fragmenting Core Blueprint theme state.
	 */
	public static function applies( string $hook_suffix = '' ): bool {
		if ( '' === $hook_suffix ) {
			$hook_suffix = self::current_hook_suffix();
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		/**
		 * Filter whether the Core Blueprint Admin Theme presentation applies.
		 *
		 * @param bool            $apply       True by default for all wp-admin screens.
		 * @param \WP_Screen|null $screen      Current screen when available.
		 * @param string          $hook_suffix Current WordPress admin hook suffix.
		 */
		return (bool) apply_filters( 'cb_admin_theme_apply', true, $screen, $hook_suffix );
	}

	private static function current_hook_suffix(): string {
		$hook_suffix = $GLOBALS['hook_suffix'] ?? '';
		return is_string( $hook_suffix ) ? $hook_suffix : '';
	}
}
