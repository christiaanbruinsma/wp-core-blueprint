<?php
declare(strict_types=1);
/**
 * Public integration facade for Core Blueprint PDF rendering.
 *
 * External Core Blueprint plugins should use this class instead of constructing
 * the renderer directly. Dompdf remains an internal implementation detail of
 * Core Blueprint Base.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\PDF\Api;

use CB\Core\PDF\Renderer;
use CB\Core\PDF\RendererException;

defined( 'ABSPATH' ) || exit;

final class PdfApi {

	/**
	 * Whether the bundled PDF renderer is available in the current runtime.
	 */
	public static function is_available(): bool {
		return Renderer::is_available();
	}

	/**
	 * Render a complete HTML document to an in-memory PDF binary.
	 *
	 * Supported renderer options currently include:
	 * - paper_size: e.g. A4 or Letter.
	 * - orientation: portrait or landscape.
	 * - default_font: default font family.
	 * - is_html5_parser: whether Dompdf's HTML5 parser is enabled.
	 *
	 * Permanent storage, filenames, downloads and caller-specific logging are
	 * intentionally outside this API. Security-sensitive Dompdf options such as
	 * remote resources, embedded PHP and JavaScript remain hard-disabled by the
	 * underlying Renderer and cannot be enabled by callers.
	 *
	 * @param string               $html    Complete HTML document.
	 * @param array<string,mixed>  $options Renderer options.
	 * @return string PDF binary beginning with the %PDF- signature.
	 *
	 * @throws RendererException When the renderer is unavailable or rendering fails.
	 */
	public static function render( string $html, array $options = [] ): string {
		return ( new Renderer() )->render( $html, $options );
	}
}
