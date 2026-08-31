<?php
declare(strict_types=1);
/**
 * Settings
 *
 * Central settings management for Core Blueprint. The entire plugin
 * configuration lives in a single serialized option (CB_CORE_SETTINGS) so
 * that site mode changes, module toggles, and admin-only settings can be
 * handled atomically.
 *
 * Settings schema:
 * [
 *     'site_mode'      => 'hub' | 'production' | 'development',
 *     'shield_enabled' => bool,  // Core Shield master switch
 *     'modules'        => [ slug => [ 'enabled' => bool, 'features' => [ id => bool ] ] ],
 *     'login_shield'   => [ ... ],  // see LoginShield::default_config()
 *     'audit'          => [ 'email_alerts' => [ ... ] ],
 *     'integrity'      => [ 'schedule' => string, ... ],
 *     'schema_version' => int,
 * ]
 *
 * Feature defaults are determined by the module itself (via features()); this
 * class only handles persistence and site-mode-adjusted defaults.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core;

use CB\Core\Security\AccessMode;
use CB\Core\Log\AuditLog;
use CB\Core\Security\ModuleRegistry;

defined( 'ABSPATH' ) || exit;

final class Settings {

	/** Request-local merged settings cache. */
	private static ?array $cached = null;

	/** Current schema version - bumped when the settings array shape changes. */
	const SCHEMA_VERSION = 1;

	/** Valid site modes. */
	const SITE_MODES = [ 'hub', 'production', 'development' ];

	// ─── Public API ───────────────────────────────────────────────────────────

	/**
	 * Default settings used on first activation. Modules can extend this
	 * via the `cb_core_default_settings` filter when they register their
	 * own configuration keys.
	 */
	public static function defaults(): array {
		return [
			'site_mode'      => 'production',
			'shield_enabled' => true,
			'modules'        => [],
			'login_shield'   => \CB\Core\Security\LoginShield::default_config(),
			'audit'          => [
				'email_recipient' => '',
				'email_alerts'    => [
					'critical' => true,
					'warning'  => false,
					'notice'   => false,
					'info'     => false,
				],
			],
			'integrity'      => [
				'schedule'             => 'disabled',
				'email_recipient'      => '',
				'email_alerts'         => [
					'critical_anomaly' => true,
					'warning_anomaly'  => false,
					'resolved'         => true,
				],
				'plugin_checksums'     => true,
				'theme_checksums'      => true,
				'uploads_scan'         => true,
				'max_visible_findings' => 50,
				// Admin-toggle: when true, administrators inherit cb_manage_integrity
				// virtually (via Caps::filter_user_has_cap). Default false so that
				// only cb_operator users can run scans out of the box.
				'admin_can_run'        => false,
				// Distribution-locale handling (1.3.12-dev).
				//
				// `distribution_locale_mode` is the operator's intent:
				//   - 'auto'     → use whatever the detector found (default)
				//   - 'override' → use the explicitly-pinned locale below
				//   - 'fallback' → use get_locale() (legacy behaviour, set
				//                  when detection has not yet run)
				//
				// `distribution_locale_detected` holds the last successful
				// auto-detection result (e.g. 'en_US'). Empty string until
				// detection has run successfully at least once.
				//
				// `distribution_locale_override` holds the operator-chosen
				// locale when mode === 'override'. Ignored otherwise.
				//
				// `distribution_locale_meta` holds detection bookkeeping:
				// last_detected_at (mysql datetime), tried (array of
				// locale strings tried during detection), matched_file
				// (relative path of the discriminator file).
				'distribution_locale_mode'      => 'fallback',
				'distribution_locale_detected'  => '',
				'distribution_locale_override'  => '',
				'distribution_locale_meta'      => [
					'last_detected_at' => '',
					'tried'            => [],
					'matched_file'     => '',
					'cross_check'      => '',
				],
			],
			'notes'          => [
				// Note-modal defaults - surfaced as the initial state of the
				// Type / Status / Assigned-to controls when the user creates a
				// new note. No effect on existing notes.
				'default_type'          => 'General',
				'default_status'        => 'Backlog',
				'default_assigned_to'   => 0,
				// 'remember' restores the user's last details-panel state;
				// 'closed' / 'open' force a fixed state on every modal open.
				'details_initial_state' => 'remember',
				// List view layout - 'list' | 'grid-2' | 'grid-3'.
				'default_layout'        => 'list',
			],
			'reports'        => [
				// Admin-toggle: when true, administrators inherit cb_manage_reports
				// virtually (via Caps::filter_user_has_cap). Default false so that
				// only cb_operator users can trigger generation out of the box.
				'admin_can_generate'  => [
					'maintenance' => false,
				],
				// How long immutable report snapshots are retained before the
				// daily cleanup pass removes them. PDFs are rendered on demand
				// and are never stored permanently. 365 covers the
				// typical "show me last year's reports" use case.
				'retention_days'      => 365,
				// Optional recipient override for Reports generation-failure alerts.
				'email_recipient'     => '',
				'email_alerts'        => [
					'generation_failed' => true,
				],
				// Per-feature branding for the maintenance report header.
				// Site-wide / suite-wide whitelabel lives at the top-level
				// `branding.*` key (not seeded here - reserved for a future
				// feature that owns it explicitly).
				'branding'            => [
					'logo_attachment_id' => 0,
					'provider_name'       => '',
					'provider_contact'    => '',
					'accent_color'       => '#0064c8',
				],
			],
			'permissions'    => [
				// When true, the Permissions tab is hidden from administrators
				// who lack cb_manage_permissions. OperatorGuard auto-disables
				// this if the operator count drops to zero (lockout prevention).
				'hide_from_admins'        => false,
				'privileged_access_mode' => 'enforce',
				'email_recipient'         => '',
				'email_alerts'     => [
					'role_change'              => true,
					'operator_guard_triggered' => true,
					'privileged_review'        => true,
				],
			],
			'schema_version' => self::SCHEMA_VERSION,
		];
	}

	/**
	 * Get the full settings array with defaults merged in for any missing keys.
	 */
	public static function get(): array {
		if ( null !== self::$cached ) {
			return self::$cached;
		}

		$stored = get_option( CB_CORE_SETTINGS, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		self::$cached = self::deep_merge( self::defaults(), $stored );
		return self::$cached;
	}

	/**
	 * Get the current site mode with validation.
	 */
	public static function site_mode(): string {
		$mode = self::get()['site_mode'] ?? 'production';
		return in_array( $mode, self::SITE_MODES, true ) ? $mode : 'production';
	}

	/**
	 * Core Shield master switch. When off, all CB security features are
	 * treated as disabled regardless of individual toggle state - the shield
	 * is the single gate that decides whether Core Blueprint imposes any
	 * security behaviour at all.
	 */
	public static function shield_enabled(): bool {
		$val = self::get()['shield_enabled'] ?? true;
		return (bool) $val;
	}

	public static function set_shield_enabled( bool $enabled, string $actor = 'unknown' ): bool {
		return self::set_key( 'shield_enabled', $enabled, $actor );
	}

	/**
	 * Update a top-level key in the settings array.
	 *
	 * Audit-logs the change with before/after hints (not full payload -
	 * payloads may be large and contain mildly sensitive data). When the
	 * value is an array, a `changed` context field lists which subkey paths
	 * actually differ so operators can see which setting changed, not just
	 * that "something" inside the top-level key did.
	 *
	 * Roadmap: for high-assurance sites (zorg, gemeente) we'll extend the
	 * audit entry with actual before/after values per changed path, with
	 * a redact-allowlist for sensitive keys (bypass_token, future API
	 * keys). The diff_paths() helper below is the seam where that lands.
	 * See the "Parked for later" list in the suite roadmap.
	 */
	public static function set_key( string $key, $value, string $actor = 'unknown' ): bool {
		$settings = self::get();
		$before   = $settings[ $key ] ?? null;

		$before_hint      = self::hint( $before );
		$settings[ $key ] = $value;
		$after_hint       = self::hint( $value );

		$result = self::persist( $settings );

		if ( $result && class_exists( AuditLog::class ) ) {
			$entry = [
				'key'    => $key,
				'before' => $before_hint,
				'after'  => $after_hint,
				'actor'  => $actor,
			];

			// When both values are arrays, surface which subkey paths
			// changed. Scalar-valued keys (shield_enabled, site_mode) are
			// already unambiguous via `key` + `before`/`after` hints and
			// don't need this.
			if ( is_array( $before ) && is_array( $value ) ) {
				$paths = self::diff_paths( $before, $value );
				if ( ! empty( $paths ) ) {
					$entry['changed'] = implode( ', ', $paths );
				}
			}

			AuditLog::log( 'settings.changed', 'notice', $entry );
		}

		return $result;
	}

	/**
	 * Enable or disable an entire module.
	 */
	public static function set_module_enabled( string $module_slug, bool $enabled, string $actor = 'unknown' ): bool {
		$settings = self::get();
		$module_slug = sanitize_key( $module_slug );

		if ( empty( $module_slug ) ) {
			return false;
		}

		if ( ! isset( $settings['modules'][ $module_slug ] ) ) {
			$settings['modules'][ $module_slug ] = [ 'enabled' => false, 'features' => [] ];
		}

		$before = (bool) ( $settings['modules'][ $module_slug ]['enabled'] ?? false );
		$settings['modules'][ $module_slug ]['enabled'] = $enabled;

		$result = self::persist( $settings );

		if ( $result && $before !== $enabled && class_exists( AuditLog::class ) ) {
			AuditLog::log( 'settings.module_toggled', 'notice', [
				'module'  => $module_slug,
				'enabled' => $enabled,
				'actor'   => $actor,
			] );
		}

		return $result;
	}

	/**
	 * Enable or disable EVERY registered module in one atomic write.
	 *
	 * Exists because the individual set_module_enabled() performs a read-
	 * modify-write on the full settings option. If the admin UI fires N
	 * concurrent AJAX requests (one per module), later writes overwrite
	 * earlier ones - lost-update race. The master toggle calls this
	 * instead to sidestep the race entirely.
	 *
	 * Only touches the `modules.*.enabled` flag. Feature-level toggles
	 * are preserved.
	 *
	 * @return array<string,bool> map of slug => new state for modules whose
	 *                            state actually changed (for audit & UI sync).
	 */
	public static function set_all_modules_enabled( bool $enabled, string $actor = 'unknown' ): array {
		$settings = self::get();
		$changed  = [];

		// Collect every registered module slug. Prefer the live registry so
		// modules discovered via cb_core_modules but not yet persisted are
		// included; fall back to the modules key in $settings.
		$slugs = [];
		if ( class_exists( ModuleRegistry::class ) ) {
			foreach ( ModuleRegistry::all() as $module ) {
				$slugs[] = $module->slug();
			}
		}
		foreach ( array_keys( $settings['modules'] ?? [] ) as $slug ) {
			if ( ! in_array( $slug, $slugs, true ) ) {
				$slugs[] = $slug;
			}
		}

		if ( empty( $slugs ) ) {
			return [];
		}

		foreach ( $slugs as $slug ) {
			$slug = sanitize_key( $slug );
			if ( empty( $slug ) ) {
				continue;
			}
			if ( ! isset( $settings['modules'][ $slug ] ) ) {
				$settings['modules'][ $slug ] = [ 'enabled' => false, 'features' => [] ];
			}
			$before = (bool) ( $settings['modules'][ $slug ]['enabled'] ?? false );
			if ( $before === $enabled ) {
				continue;
			}
			$settings['modules'][ $slug ]['enabled'] = $enabled;
			$changed[ $slug ] = $enabled;
		}

		if ( empty( $changed ) ) {
			return [];
		}

		$result = self::persist( $settings );

		if ( $result && class_exists( AuditLog::class ) ) {
			AuditLog::log( 'settings.modules_bulk_toggled', 'notice', [
				'enabled' => $enabled,
				'count'   => count( $changed ),
				'slugs'   => array_keys( $changed ),
				'actor'   => $actor,
			] );
		}

		return $result ? $changed : [];
	}

	/**
	 * Enable or disable a specific feature within a module.
	 */
	public static function set_feature_enabled( string $module_slug, string $feature_id, bool $enabled, string $actor = 'unknown' ): bool {
		$settings = self::get();
		$module_slug = sanitize_key( $module_slug );
		$feature_id  = sanitize_key( $feature_id );

		if ( empty( $module_slug ) || empty( $feature_id ) ) {
			return false;
		}

		if ( ! isset( $settings['modules'][ $module_slug ] ) ) {
			$settings['modules'][ $module_slug ] = [ 'enabled' => true, 'features' => [] ];
		}

		$before = (bool) ( $settings['modules'][ $module_slug ]['features'][ $feature_id ] ?? false );
		$settings['modules'][ $module_slug ]['features'][ $feature_id ] = $enabled;

		$result = self::persist( $settings );

		if ( $result && $before !== $enabled && class_exists( AuditLog::class ) ) {
			AuditLog::log( 'settings.feature_toggled', 'notice', [
				'module'  => $module_slug,
				'feature' => $feature_id,
				'enabled' => $enabled,
				'actor'   => $actor,
			] );
		}

		return $result;
	}

	/**
	 * Apply site-mode-appropriate defaults to all registered modules. Called
	 * when the user clicks "Apply recommended configuration" in the UI.
	 *
	 * Takes the current modules registered with ModuleRegistry and
	 * sets each feature's enabled state according to its default + the
	 * current site mode's policy.
	 */
	public static function apply_recommended_defaults( string $actor = 'unknown' ): void {
		$settings = self::get();
		$shield   = self::shield_enabled();
		$mode     = self::effective_hardening_mode();

		foreach ( ModuleRegistry::all() as $module ) {
			$slug = $module->slug();
			if ( ! isset( $settings['modules'][ $slug ] ) ) {
				$settings['modules'][ $slug ] = [ 'enabled' => true, 'features' => [] ];
			}
			$settings['modules'][ $slug ]['enabled'] = $shield;

			foreach ( $module->features() as $feature ) {
				$feature_id = $feature['id'] ?? '';
				if ( empty( $feature_id ) ) {
					continue;
				}

				$should_enable = $shield ? self::should_enable_for_mode( $feature, $mode ) : false;
				$settings['modules'][ $slug ]['features'][ $feature_id ] = $should_enable;
			}
		}

		self::persist( $settings );

		if ( class_exists( AuditLog::class ) ) {
			AuditLog::log( 'settings.defaults_applied', 'notice', [
				'shield'    => $shield ? 'on' : 'off',
				'mode'      => $mode,
				'actor'     => $actor,
			] );
		}
	}

	/**
	 * Resolve the effective hardening profile from Access Mode state.
	 *
	 * When Shield is on, Core Blueprint derives its hardening level from how
	 * the site is exposed to the public: Admin-Only gets the strictest preset
	 * (all restrictive features on); Public, Coming Soon and Maintenance keep
	 * the balanced Production preset while Access Mode owns their HTTP policy.
	 *
	 * @return string 'hub' | 'production'
	 */
	public static function effective_hardening_mode(): string {
		if ( class_exists( AccessMode::class ) && AccessMode::is_admin_only() ) {
			return 'hub';
		}

		return 'production';
	}

	// ─── Mode policy ──────────────────────────────────────────────────────────

	/**
	 * Determine whether a given feature should be enabled by default for a
	 * given site mode. This is the "policy matrix" that implements the
	 * Hub / Production / Development site modes.
	 */
	private static function should_enable_for_mode( array $feature, string $mode ): bool {
		$default = (bool) ( $feature['default'] ?? false );
		$risk    = $feature['risk'] ?? 'low';

		// High-risk features never default-on, regardless of mode - they must
		// be explicitly opted into because they can cause site breakage.
		if ( 'high' === $risk ) {
			return false;
		}

		switch ( $mode ) {
			case 'hub':
				// Hub is strictest - enable all medium/low/none-risk features
				// that have default-on OR restrictive:true.
				if ( ! empty( $feature['restrictive'] ) ) {
					return true;
				}
				return $default;

			case 'production':
				// Production - enable defaults, skip restrictive medium-risk
				// features that might affect bezoekers.
				if ( 'medium' === $risk && ! empty( $feature['restrictive'] ) ) {
					return false;
				}
				return $default;

			case 'development':
				// Development - enable only none-risk defaults.
				if ( 'none' !== $risk ) {
					return false;
				}
				return $default;

			default:
				return $default;
		}
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Persist the full settings document and keep the request-local cache in
	 * lockstep. WordPress returns false both for a no-op and for a failed write;
	 * on false we only refresh the cache when the stored value already matches.
	 */
	private static function persist( array $settings ): bool {
		$result = update_option( CB_CORE_SETTINGS, $settings, true );
		if ( $result ) {
			self::$cached = self::deep_merge( self::defaults(), $settings );
			return true;
		}

		$stored = get_option( CB_CORE_SETTINGS, null );
		if ( is_array( $stored ) && $stored === $settings ) {
			self::$cached = self::deep_merge( self::defaults(), $settings );
		}
		return false;
	}


	/**
	 * Recursive array merge that preserves nested defaults. Used when reading
	 * settings so that missing keys from older installations get filled in
	 * from the current defaults schema.
	 */
	private static function deep_merge( array $defaults, array $override ): array {
		$merged = $defaults;
		foreach ( $override as $key => $value ) {
			if ( is_array( $value ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
				$merged[ $key ] = self::deep_merge( $merged[ $key ], $value );
			} else {
				$merged[ $key ] = $value;
			}
		}
		return $merged;
	}

	/**
	 * Build a short, audit-safe hint from an arbitrary setting value.
	 * Arrays are summarised; strings are truncated; booleans/ints are passed
	 * through. Used in settings.changed audit entries.
	 */
	private static function hint( $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}
		if ( is_string( $value ) ) {
			return substr( $value, 0, 80 );
		}
		if ( is_array( $value ) ) {
			return sprintf( 'array(%d keys)', count( $value ) );
		}
		if ( is_null( $value ) ) {
			return 'null';
		}
		return gettype( $value );
	}

	/**
	 * Recursively walk two arrays and return a flat list of dot-separated
	 * subkey paths that differ. Used to tell the operator which specific
	 * sub-settings changed without exposing the actual values.
	 *
	 * Example:
	 *   $old = ['retention_days' => 180, 'email_alerts' => ['critical' => true]]
	 *   $new = ['retention_days' => 180, 'email_alerts' => ['critical' => false]]
	 *   → ['email_alerts.critical']
	 *
	 * Safe for audit logs: only key names are returned, never values.
	 * Sub-key names themselves in the CB settings schema are non-sensitive
	 * (configuration identifiers, not secrets).
	 *
	 * @param array<mixed,mixed> $old
	 * @param array<mixed,mixed> $new
	 * @param string             $prefix Internal - path accumulator for recursion.
	 * @return string[]
	 */
	private static function diff_paths( array $old, array $new, string $prefix = '' ): array {
		$paths    = [];
		$all_keys = array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) );

		foreach ( $all_keys as $k ) {
			$path   = '' === $prefix ? (string) $k : $prefix . '.' . $k;
			$o_has  = array_key_exists( $k, $old );
			$n_has  = array_key_exists( $k, $new );
			$o_val  = $old[ $k ] ?? null;
			$n_val  = $new[ $k ] ?? null;

			if ( $o_has && $n_has && is_array( $o_val ) && is_array( $n_val ) ) {
				$paths = array_merge( $paths, self::diff_paths( $o_val, $n_val, $path ) );
				continue;
			}

			if ( ! $o_has || ! $n_has || $o_val !== $n_val ) {
				$paths[] = $path;
			}
		}

		return $paths;
	}
}
