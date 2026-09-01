<?php
declare(strict_types=1);
/**
 * Request-local sender identity context for Core Blueprint Mail.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Mail;

defined( 'ABSPATH' ) || exit;

final class SenderContext {

	/** @var string[] */
	private static array $stack = [];

	public static function push( string $identity_id ): void {
		self::$stack[] = sanitize_key( $identity_id );
	}

	public static function pop(): void {
		array_pop( self::$stack );
	}

	public static function current(): string {
		if ( empty( self::$stack ) ) {
			return '';
		}
		$current = end( self::$stack );
		return is_string( $current ) ? $current : '';
	}
}
