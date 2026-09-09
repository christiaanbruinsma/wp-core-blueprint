<?php
declare(strict_types=1);

namespace CB\Core\Design\Profile\Document\Fixed;

defined( 'ABSPATH' ) || exit;

final class HtmlRenderer {
	/**
	 * @param array<string,mixed> $page
	 * @param list<RenderFragment> $fragments
	 */
	public function render( array $page, array $fragments, string $locale ): string {
		$normalized_page = Geometry::page(
			[
				'mode'  => Contract::LAYOUT_MODE,
				'units' => Contract::UNITS,
				'page'  => $page,
			]
		);
		if ( null === $normalized_page ) {
			throw new \InvalidArgumentException( 'Invalid Fixed render page.' );
		}
		if ( 1 !== preg_match( '/^[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/', $locale ) ) {
			throw new \InvalidArgumentException( 'Fixed rendering requires an explicit valid locale.' );
		}

		foreach ( $fragments as $fragment ) {
			if ( ! $fragment instanceof RenderFragment ) {
				throw new \InvalidArgumentException( 'Fixed rendering accepts typed render fragments only.' );
			}
			if ( ! Geometry::within_page( $fragment->frame(), $normalized_page ) ) {
				throw new \InvalidArgumentException( 'Fixed render fragment falls outside the page.' );
			}
		}

		$lang = str_replace( '_', '-', $locale );
		$width = self::number( $normalized_page['width'] );
		$height = self::number( $normalized_page['height'] );

		$body = '';
		foreach ( $fragments as $fragment ) {
			$frame = $fragment->frame();
			$style = sprintf(
				'position:absolute;left:%smm;top:%smm;width:%smm;height:%smm;box-sizing:border-box;overflow:hidden;',
				self::number( $frame['x'] ),
				self::number( $frame['y'] ),
				self::number( $frame['width'] ),
				self::number( $frame['height'] )
			);
			if ( 'text' === $fragment->type() ) {
				$body .= '<div class="cb-fixed-fragment cb-fixed-text" style="' . $style . '">'
					. htmlspecialchars( $fragment->payload(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' )
					. '</div>';
				continue;
			}
			if ( 'image' === $fragment->type() ) {
				$body .= '<div class="cb-fixed-fragment cb-fixed-image" style="' . $style . '"><img alt="" src="'
					. htmlspecialchars( $fragment->payload(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' )
					. '" style="display:block;width:100%;height:100%;border:0;" /></div>';
				continue;
			}
			throw new \InvalidArgumentException( 'Unsupported Fixed render fragment type.' );
		}

		return '<!doctype html><html lang="' . htmlspecialchars( $lang, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '"><head><meta charset="utf-8"><style>'
			. '@page{margin:0;size:' . $width . 'mm ' . $height . 'mm;}'
			. 'html,body{margin:0;padding:0;width:' . $width . 'mm;height:' . $height . 'mm;}'
			. 'body{font-family:"DejaVu Sans",sans-serif;font-size:10pt;color:#111;}'
			. '.cb-fixed-page{position:relative;width:' . $width . 'mm;height:' . $height . 'mm;overflow:hidden;}'
			. '</style></head><body><div class="cb-fixed-page">'
			. $body
			. '</div></body></html>';
	}

	private static function number( float $value ): string {
		$formatted = rtrim( rtrim( number_format( $value, 4, '.', '' ), '0' ), '.' );
		return '' === $formatted ? '0' : $formatted;
	}
}
