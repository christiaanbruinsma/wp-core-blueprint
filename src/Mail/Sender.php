<?php
declare(strict_types=1);
/**
 * Public scoped send API for registered Core Blueprint mail identities.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Mail;

defined( 'ABSPATH' ) || exit;

final class Sender {

	/**
	 * Send a WordPress email through a registered Core Blueprint sender identity.
	 *
	 * Unknown identities fail safely to the configured default sender.
	 *
	 * @param string|string[] $to
	 * @param string|string[] $headers
	 * @param string|string[] $attachments
	 */
	public static function send(
		string $identity_id,
		string|array $to,
		string $subject,
		string $message,
		string|array $headers = [],
		string|array $attachments = []
	): bool {
		$identity = SenderIdentityRegistry::get( $identity_id );
		$scoped_id = null !== $identity ? sanitize_key( $identity_id ) : '';
		$identity = $identity ?? SenderIdentityRegistry::default_identity();
		$headers  = self::with_from_header( $headers, $identity );

		SenderContext::push( $scoped_id );
		try {
			return wp_mail( $to, $subject, $message, $headers, $attachments );
		} finally {
			SenderContext::pop();
		}
	}

	/**
	 * Replace any caller-provided From header with the Base-resolved identity.
	 *
	 * @param string|string[] $headers
	 * @param array{email:string,name:string} $identity
	 * @return string[]
	 */
	private static function with_from_header( string|array $headers, array $identity ): array {
		if ( is_string( $headers ) ) {
			$headers = '' === trim( $headers ) ? [] : ( preg_split( '/\r?\n/', trim( $headers ) ) ?: [] );
		}

		$out = [];
		foreach ( $headers as $key => $line ) {
			if ( is_string( $key ) && is_string( $line ) && ! str_contains( $line, ':' ) ) {
				$line = $key . ': ' . $line;
			}
			if ( ! is_string( $line ) ) {
				continue;
			}
			if ( 1 === preg_match( '/^\s*from\s*:/i', $line ) ) {
				continue;
			}
			$out[] = $line;
		}

		$email = sanitize_email( (string) ( $identity['email'] ?? '' ) );
		$name  = sanitize_text_field( (string) ( $identity['name'] ?? '' ) );
		if ( is_email( $email ) ) {
			$out[] = '' !== $name
				? sprintf( 'From: %s <%s>', $name, $email )
				: 'From: ' . $email;
		}

		return $out;
	}
}
