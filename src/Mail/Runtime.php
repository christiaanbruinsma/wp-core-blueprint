<?php
declare(strict_types=1);
/**
 * Runtime activation boundary for Core Blueprint Mail.
 *
 * No transport hooks are registered unless the module is enabled and no known
 * competing SMTP/mail transport plugin is active.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Mail;

use CB\Core\Mail\Log\DeliveryLogger;
use CB\Core\Mail\Transport\BrevoTransport;
use CB\Core\Mail\Transport\SmtpTransport;

defined( 'ABSPATH' ) || exit;

final class Runtime {

	private static bool $active = false;

	public static function boot(): void {
		if ( ! State::is_enabled() || ConflictDetector::has_conflict() || '' !== Settings::activation_error_code() ) {
			return;
		}

		$transports = apply_filters( 'cb_core_mail_transports', [
			BrevoTransport::slug() => BrevoTransport::class,
			SmtpTransport::slug()  => SmtpTransport::class,
		] );
		$transports = is_array( $transports ) ? $transports : [];
		$transport  = $transports[ Settings::provider() ] ?? null;

		// Validate the selected provider before registering any mail-related
		// hooks. Unknown/invalid provider adapters therefore fail closed just
		// like the disabled/conflict states above.
		if ( ! is_string( $transport ) || ! is_subclass_of( $transport, \CB\Core\Mail\Transport\TransportInterface::class ) ) {
			return;
		}

		self::$active = true;
		DeliveryLogger::boot();

		$settings = Settings::all();
		if ( ! empty( $settings['force_from_email'] ) ) {
			add_filter( 'wp_mail_from', [ __CLASS__, 'force_from_email' ], PHP_INT_MAX );
		}
		if ( ! empty( $settings['force_from_name'] ) ) {
			add_filter( 'wp_mail_from_name', [ __CLASS__, 'force_from_name' ], PHP_INT_MAX );
		}

		$transport::boot();
	}

	public static function is_active(): bool {
		return self::$active;
	}

	public static function force_from_email( string $email ): string {
		$identity = SenderIdentityRegistry::current();
		if ( null !== $identity && is_email( $identity['email'] ) ) {
			return $identity['email'];
		}

		$configured = sanitize_email( (string) Settings::all()['from_email'] );
		return is_email( $configured ) ? $configured : $email;
	}

	public static function force_from_name( string $name ): string {
		$identity = SenderIdentityRegistry::current();
		if ( null !== $identity && '' !== $identity['name'] ) {
			return $identity['name'];
		}

		$configured = sanitize_text_field( (string) Settings::all()['from_name'] );
		return '' !== $configured ? $configured : $name;
	}
}
