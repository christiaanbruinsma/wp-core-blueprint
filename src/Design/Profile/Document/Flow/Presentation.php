<?php
declare(strict_types=1);

namespace CB\Core\Design\Profile\Document\Flow;

defined( 'ABSPATH' ) || exit;

/**
 * Bounded Flow presentation tokens.
 *
 * Presentation is deliberately not a generic style system. It exposes only
 * values the Flow renderer owns and validates itself, so consumers cannot
 * inject arbitrary CSS into canonical document output.
 */
final readonly class Presentation {
	private const DEFAULT_ACCENT = '#111111';

	private function __construct( private string $accent ) {}

	public static function defaults(): self {
		return new self( self::DEFAULT_ACCENT );
	}

	public static function from_accent( string $accent ): self {
		$accent = strtolower( trim( $accent ) );
		if ( 1 === preg_match( '/^#[0-9a-f]{3}$/', $accent ) ) {
			$accent = '#' . $accent[1] . $accent[1] . $accent[2] . $accent[2] . $accent[3] . $accent[3];
		}
		if ( 1 !== preg_match( '/^#[0-9a-f]{6}$/', $accent ) ) {
			throw new \InvalidArgumentException( 'Flow presentation accent must be a hexadecimal colour.' );
		}
		return new self( $accent );
	}

	public function accent(): string {
		return $this->accent;
	}
}
