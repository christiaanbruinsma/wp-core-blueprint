<?php
declare(strict_types=1);

namespace CB\Core\Design\Profile\Document\Flow;

use CB\Core\Design\Profile\Document\Render\ImageDataUri;

defined( 'ABSPATH' ) || exit;

final readonly class RenderBlock {
	private const MAX_TEXT_BYTES = 65536;
	private const MAX_COLUMNS = 32;
	private const MAX_ROWS = 2000;

	/** @param mixed $payload @param array{space_before:float,space_after:float,break_before:bool,break_after:bool,keep_together:bool} $hints */
	private function __construct( private string $type, private mixed $payload, private array $hints ) {}

	/** @param array<string,mixed> $hints */
	public static function text( string $text, array $hints = [] ): self {
		if ( strlen( $text ) > self::MAX_TEXT_BYTES ) { throw new \InvalidArgumentException( 'Flow text block exceeds the supported size.' ); }
		return new self( 'text', $text, self::normalize_hints( $hints ) );
	}

	/** @param array<string,mixed> $hints */
	public static function image( string $data_uri, array $hints = [] ): self {
		return new self( 'image', ImageDataUri::assert_valid( $data_uri ), self::normalize_hints( $hints ) );
	}

	/** @param list<RenderBlock> $children @param array<string,mixed> $hints */
	public static function container( array $children, array $hints = [] ): self {
		if ( ! array_is_list( $children ) ) { throw new \InvalidArgumentException( 'Flow container children must be an ordered list.' ); }
		foreach ( $children as $child ) {
			if ( ! $child instanceof self ) { throw new \InvalidArgumentException( 'Flow containers accept typed render blocks only.' ); }
		}
		return new self( 'container', array_values( $children ), self::normalize_hints( $hints ) );
	}

	/** @param list<string> $headers @param list<list<string>> $rows @param array<string,mixed> $hints */
	public static function table( array $headers, array $rows, array $hints = [] ): self {
		if ( ! array_is_list( $headers ) || ! array_is_list( $rows ) ) { throw new \InvalidArgumentException( 'Flow table headers and rows must be ordered lists.' ); }
		if ( [] === $headers || count( $headers ) > self::MAX_COLUMNS ) { throw new \InvalidArgumentException( 'Flow tables require between 1 and 32 columns.' ); }
		foreach ( $headers as $header ) {
			if ( ! is_string( $header ) || strlen( $header ) > self::MAX_TEXT_BYTES ) { throw new \InvalidArgumentException( 'Flow table headers must be bounded strings.' ); }
		}
		if ( count( $rows ) > self::MAX_ROWS ) { throw new \InvalidArgumentException( 'Flow table exceeds the supported row count.' ); }
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! array_is_list( $row ) || count( $row ) !== count( $headers ) ) { throw new \InvalidArgumentException( 'Every Flow table row must match the declared column count.' ); }
			foreach ( $row as $cell ) {
				if ( ! is_string( $cell ) || strlen( $cell ) > self::MAX_TEXT_BYTES ) { throw new \InvalidArgumentException( 'Flow table cells must be bounded strings.' ); }
			}
		}
		return new self( 'table', [ 'headers' => array_values( $headers ), 'rows' => array_values( $rows ) ], self::normalize_hints( $hints ) );
	}

	/**
	 * Fixed per-page footer with consumer-owned escaped text and a renderer-owned
	 * CSS page counter. No layout/style options are accepted by consumers.
	 */
	public static function page_footer( string $left_text, string $page_label ): self {
		if ( strlen( $left_text ) > self::MAX_TEXT_BYTES || strlen( $page_label ) > self::MAX_TEXT_BYTES ) {
			throw new \InvalidArgumentException( 'Flow page footer text exceeds the supported size.' );
		}
		if ( '' === trim( $page_label ) ) {
			throw new \InvalidArgumentException( 'Flow page footer requires a page label.' );
		}
		return new self(
			'page_footer',
			[ 'left_text' => $left_text, 'page_label' => $page_label ],
			self::normalize_hints( [] )
		);
	}

	public function type(): string { return $this->type; }
	public function payload(): mixed { return $this->payload; }
	/** @return array{space_before:float,space_after:float,break_before:bool,break_after:bool,keep_together:bool} */
	public function hints(): array { return $this->hints; }

	/** @param array<string,mixed> $hints @return array{space_before:float,space_after:float,break_before:bool,break_after:bool,keep_together:bool} */
	private static function normalize_hints( array $hints ): array {
		$normalized = Hints::normalize( $hints );
		if ( null === $normalized ) { throw new \InvalidArgumentException( 'Invalid Flow render hints.' ); }
		return $normalized;
	}
}
