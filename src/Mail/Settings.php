<?php
declare(strict_types=1);
/**
 * Mail module settings repository.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Mail;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION = 'cb_core_mail_settings';
	public const ENABLED_OPTION = 'cb_core_mail_enabled';
	public const PROVIDERS = [ 'brevo', 'smtp' ];
	public const ENCRYPTIONS = [ 'none', 'tls', 'ssl' ];
	public const RETENTION_DAYS = [ 7, 14, 30, 60, 90, 180, 365 ];

	public static function defaults(): array {
		return [
			'enabled'            => false,
			'provider'           => 'brevo',
			'from_email'         => sanitize_email( (string) get_option( 'admin_email', '' ) ),
			'from_name'          => sanitize_text_field( (string) get_bloginfo( 'name' ) ),
			'force_from_email'   => true,
			'force_from_name'    => true,
			'brevo_api_key'      => '',
			'smtp_host'          => '',
			'smtp_port'          => 587,
			'smtp_encryption'    => 'tls',
			'smtp_auth'          => true,
			'smtp_username'      => '',
			'smtp_password'      => '',
			'smtp_auto_tls'      => true,
			'retention_days'     => 14,
		];
	}


	public static function enabled(): bool {
		$enabled = get_option( self::ENABLED_OPTION, null );
		if ( null !== $enabled ) {
			return (bool) $enabled;
		}

		// One-time migration for installs where enabled lived only inside the
		// cold credential/configuration document.
		$stored  = get_option( self::OPTION, [] );
		$enabled = is_array( $stored ) && ! empty( $stored['enabled'] );
		add_option( self::ENABLED_OPTION, $enabled ? '1' : '0', '', true );
		return $enabled;
	}

	public static function all(): array {
		$stored = get_option( self::OPTION, [] );
		$stored = is_array( $stored ) ? $stored : [];
		return array_merge( self::defaults(), $stored );
	}

	public static function save( array $settings ): bool {
		$settings = array_merge( self::defaults(), $settings );
		$config_changed = update_option( self::OPTION, $settings, false );
		$state_changed  = update_option( self::ENABLED_OPTION, ! empty( $settings['enabled'] ) ? '1' : '0', true );
		return $config_changed || $state_changed;
	}

	/**
	 * Return a translation-free activation error code for runtime/bootstrap use.
	 *
	 * Mail runtime boots on `plugins_loaded` so transport conflict detection sees
	 * the complete active-plugin set. WordPress 6.7+ forbids resolving this
	 * plugin's textdomain before `init`, therefore early runtime validation must
	 * never construct translated presentation strings.
	 */
	public static function activation_error_code( ?array $settings = null ): string {
		$settings = is_array( $settings ) ? array_merge( self::defaults(), $settings ) : self::all();
		if ( ! is_email( (string) $settings['from_email'] ) ) {
			return 'invalid_from_email';
		}
		if ( 'brevo' === (string) $settings['provider'] && '' === Secrets::decrypt( (string) $settings['brevo_api_key'] ) ) {
			return 'missing_brevo_api_key';
		}
		if ( 'smtp' === (string) $settings['provider'] ) {
			if ( '' === trim( (string) $settings['smtp_host'] ) ) {
				return 'missing_smtp_host';
			}
			if ( ! empty( $settings['smtp_auth'] ) && ( '' === trim( (string) $settings['smtp_username'] ) || '' === Secrets::decrypt( (string) $settings['smtp_password'] ) ) ) {
				return 'missing_smtp_credentials';
			}
		}
		return '';
	}

	/**
	 * Return a human-readable reason why the supplied/current settings cannot
	 * activate Mail yet, or an empty string when activation is ready.
	 *
	 * Call from `init` or later; early runtime code must use
	 * activation_error_code() instead.
	 */
	public static function activation_error( ?array $settings = null ): string {
		return match ( self::activation_error_code( $settings ) ) {
			'invalid_from_email'        => __( 'A valid From Email is required before Mail can be enabled.', 'core-blueprint' ),
			'missing_brevo_api_key'     => __( 'A Brevo API key is required before the Brevo transport can be enabled.', 'core-blueprint' ),
			'missing_smtp_host'         => __( 'An SMTP host is required before the SMTP transport can be enabled.', 'core-blueprint' ),
			'missing_smtp_credentials'  => __( 'SMTP username and password are required when authentication is enabled.', 'core-blueprint' ),
			default                     => '',
		};
	}

	public static function provider(): string {
		$provider = sanitize_key( (string) ( self::all()['provider'] ?? 'brevo' ) );
		return in_array( $provider, self::PROVIDERS, true ) ? $provider : 'brevo';
	}

	public static function brevo_api_key(): string {
		return Secrets::decrypt( (string) ( self::all()['brevo_api_key'] ?? '' ) );
	}

	public static function smtp_password(): string {
		return Secrets::decrypt( (string) ( self::all()['smtp_password'] ?? '' ) );
	}

	public static function retention_days(): int {
		$days = (int) ( self::all()['retention_days'] ?? 14 );
		return in_array( $days, self::RETENTION_DAYS, true ) ? $days : 14;
	}

	public static function provider_label( ?string $provider = null ): string {
		$provider = $provider ?: self::provider();
		return 'smtp' === $provider ? __( 'Generic SMTP', 'core-blueprint' ) : __( 'Brevo', 'core-blueprint' );
	}
}
