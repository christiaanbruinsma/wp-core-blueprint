<?php
declare(strict_types=1);

namespace CB\Core\Design\Profile\Document\Render;

defined( 'ABSPATH' ) || exit;

final class ImageDataUri {
	private const MAX_IMAGE_BYTES = 4194304;

	public static function assert_valid( string $data_uri ): string {
		if ( 1 !== preg_match( '#^data:image/(png|jpeg);base64,([A-Za-z0-9+/]+={0,2})$#', $data_uri, $matches ) ) {
			throw new \InvalidArgumentException( 'Document image rendering requires a local PNG or JPEG data URI.' );
		}
		$decoded = base64_decode( $matches[2], true );
		if ( false === $decoded || '' === $decoded || strlen( $decoded ) > self::MAX_IMAGE_BYTES ) {
			throw new \InvalidArgumentException( 'Document image render data is invalid or too large.' );
		}
		$valid_signature = 'png' === $matches[1]
			? str_starts_with( $decoded, "\x89PNG\r\n\x1a\n" )
			: str_starts_with( $decoded, "\xFF\xD8" );
		if ( ! $valid_signature ) {
			throw new \InvalidArgumentException( 'Document image MIME type does not match its binary signature.' );
		}
		return $data_uri;
	}

	private function __construct() {}
}
