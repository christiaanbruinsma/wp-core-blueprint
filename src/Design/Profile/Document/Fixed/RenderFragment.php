<?php
declare(strict_types=1);

namespace CB\Core\Design\Profile\Document\Fixed;

defined( 'ABSPATH' ) || exit;

final readonly class RenderFragment {
	private const MAX_TEXT_BYTES = 65536;
	private const MAX_IMAGE_BYTES = 4194304;

	/**
	 * @param array{x:float,y:float,width:float,height:float} $frame
	 */
	private function __construct(
		private string $type,
		private array $frame,
		private string $payload
	) {}

	/**
	 * @param array<string,mixed> $frame
	 */
	public static function text( array $frame, string $text ): self {
		$normalized = self::normalize_frame( $frame );
		if ( strlen( $text ) > self::MAX_TEXT_BYTES ) {
			throw new \InvalidArgumentException( 'Fixed text fragment exceeds the supported size.' );
		}
		return new self( 'text', $normalized, $text );
	}

	/**
	 * @param array<string,mixed> $frame
	 */
	public static function image( array $frame, string $data_uri ): self {
		$normalized = self::normalize_frame( $frame );
		if ( 1 !== preg_match( '#^data:image/(png|jpeg);base64,([A-Za-z0-9+/]+={0,2})$#', $data_uri, $matches ) ) {
			throw new \InvalidArgumentException( 'Fixed image fragments require a local PNG or JPEG data URI.' );
		}
		$decoded = base64_decode( $matches[2], true );
		if ( false === $decoded || '' === $decoded || strlen( $decoded ) > self::MAX_IMAGE_BYTES ) {
			throw new \InvalidArgumentException( 'Fixed image fragment data is invalid or too large.' );
		}
		$mime = $matches[1];
		$valid_signature = 'png' === $mime
			? str_starts_with( $decoded, "\x89PNG\r\n\x1a\n" )
			: str_starts_with( $decoded, "\xFF\xD8" );
		if ( ! $valid_signature ) {
			throw new \InvalidArgumentException( 'Fixed image fragment MIME type does not match its binary signature.' );
		}
		return new self( 'image', $normalized, $data_uri );
	}

	public function type(): string {
		return $this->type;
	}

	/** @return array{x:float,y:float,width:float,height:float} */
	public function frame(): array {
		return $this->frame;
	}

	public function payload(): string {
		return $this->payload;
	}

	/**
	 * @param array<string,mixed> $frame
	 * @return array{x:float,y:float,width:float,height:float}
	 */
	private static function normalize_frame( array $frame ): array {
		$normalized = Geometry::frame( [ Contract::NODE_FRAME_KEY => $frame ] );
		if ( null === $normalized ) {
			throw new \InvalidArgumentException( 'Invalid Fixed render fragment frame.' );
		}
		foreach ( array_keys( $frame ) as $key ) {
			if ( ! is_string( $key ) || ! in_array( $key, Contract::FRAME_KEYS, true ) ) {
				throw new \InvalidArgumentException( 'Unknown Fixed render fragment frame key.' );
			}
		}
		return $normalized;
	}
}
