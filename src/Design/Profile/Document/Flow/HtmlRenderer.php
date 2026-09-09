<?php
declare(strict_types=1);

namespace CB\Core\Design\Profile\Document\Flow;

defined( 'ABSPATH' ) || exit;

final class HtmlRenderer {
	/** @param array<string,mixed> $layout @param list<RenderBlock> $blocks */
	public function render( array $layout, array $blocks, string $locale, ?Presentation $presentation = null ): string {
		if ( ! Layout::matches_contract( $layout ) ) {
			throw new \InvalidArgumentException( 'Flow rendering requires the exact root-owned Flow layout contract.' );
		}
		$page = Layout::page( $layout );
		$margins = Layout::margins( $layout );
		if ( null === $page || null === $margins || ! Layout::has_content_area( $page, $margins ) ) {
			throw new \InvalidArgumentException( 'Invalid Flow render layout.' );
		}
		if ( 1 !== preg_match( '/^[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/', $locale ) ) {
			throw new \InvalidArgumentException( 'Flow rendering requires an explicit valid locale.' );
		}
		foreach ( $blocks as $block ) {
			if ( ! $block instanceof RenderBlock ) { throw new \InvalidArgumentException( 'Flow rendering accepts typed render blocks only.' ); }
		}

		$lang = str_replace( '_', '-', $locale );
		$body = implode( '', array_map( fn ( RenderBlock $block ): string => $this->block( $block ), $blocks ) );
		$page_size = self::number( $page['width'] ) . 'mm ' . self::number( $page['height'] ) . 'mm';
		$margin = implode( ' ', [ self::number( $margins['top'] ) . 'mm', self::number( $margins['right'] ) . 'mm', self::number( $margins['bottom'] ) . 'mm', self::number( $margins['left'] ) . 'mm' ] );
		$accent = ( $presentation ?? Presentation::defaults() )->accent();

		return '<!doctype html><html lang="' . self::escape( $lang ) . '"><head><meta charset="utf-8"><style>'
			. '@page{size:' . $page_size . ';margin:' . $margin . ';}'
			. 'html,body{margin:0;padding:0;}body{font-family:"DejaVu Sans",sans-serif;font-size:10pt;line-height:1.4;color:#111;}'
			. '.cb-flow-block{box-sizing:border-box;}.cb-flow-image img{display:block;max-width:100%;height:auto;border:0;}'
			. '.cb-flow-table{width:100%;border-collapse:collapse;}.cb-flow-table th,.cb-flow-table td{padding:4pt;border-bottom:1px solid #ddd;text-align:left;vertical-align:top;}'
			. '.cb-flow-table th{color:' . self::escape( $accent ) . ';border-bottom-color:' . self::escape( $accent ) . ';}'
			. '</style></head><body>' . $body . '</body></html>';
	}

	private function block( RenderBlock $block ): string {
		$style = $this->hints_style( $block->hints() );
		$open = '<div class="cb-flow-block cb-flow-' . self::escape( $block->type() ) . '" style="' . self::escape( $style ) . '">';
		if ( 'text' === $block->type() ) { return $open . nl2br( self::escape( (string) $block->payload() ), false ) . '</div>'; }
		if ( 'image' === $block->type() ) { return $open . '<img alt="" src="' . self::escape( (string) $block->payload() ) . '" /></div>'; }
		if ( 'container' === $block->type() ) {
			/** @var list<RenderBlock> $children */
			$children = $block->payload();
			return $open . implode( '', array_map( fn ( RenderBlock $child ): string => $this->block( $child ), $children ) ) . '</div>';
		}
		if ( 'table' === $block->type() ) {
			/** @var array{headers:list<string>,rows:list<list<string>>} $table */
			$table = $block->payload();
			$html = $open . '<table class="cb-flow-table"><thead><tr>';
			foreach ( $table['headers'] as $header ) { $html .= '<th>' . self::escape( $header ) . '</th>'; }
			$html .= '</tr></thead><tbody>';
			foreach ( $table['rows'] as $row ) {
				$html .= '<tr>';
				foreach ( $row as $cell ) { $html .= '<td>' . self::escape( $cell ) . '</td>'; }
				$html .= '</tr>';
			}
			return $html . '</tbody></table></div>';
		}
		throw new \InvalidArgumentException( 'Unsupported Flow render block type.' );
	}

	/** @param array{space_before:float,space_after:float,break_before:bool,break_after:bool,keep_together:bool} $hints */
	private function hints_style( array $hints ): string {
		$style = 'margin-top:' . self::number( $hints['space_before'] ) . 'mm;margin-bottom:' . self::number( $hints['space_after'] ) . 'mm;';
		if ( $hints['break_before'] ) { $style .= 'page-break-before:always;'; }
		if ( $hints['break_after'] ) { $style .= 'page-break-after:always;'; }
		if ( $hints['keep_together'] ) { $style .= 'page-break-inside:avoid;'; }
		return $style;
	}

	private static function escape( string $value ): string { return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ); }
	private static function number( float $value ): string {
		$formatted = rtrim( rtrim( number_format( $value, 4, '.', '' ), '0' ), '.' );
		return '' === $formatted ? '0' : $formatted;
	}
}
