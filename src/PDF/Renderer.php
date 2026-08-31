<?php
declare(strict_types=1);
/**
 * PDF Renderer
 *
 * Thin, security-hardened wrapper around the vendored Dompdf library. Renders
 * HTML to an in-memory PDF binary; permanent file storage is intentionally not
 * part of this API.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\PDF;

defined( 'ABSPATH' ) || exit;

final class Renderer {

	const VENDOR_AUTOLOAD = '/src/PDF/lib/dompdf/autoload.inc.php';

	private const EXPECTED_DOMPDF_VERSION = '3.1.6';
	private const REQUIRED_EXTENSIONS      = [ 'dom', 'mbstring' ];

	/*
	 * Third-party PDF dependencies are vendored verbatim. Do not apply Core
	 * Blueprint strict-types, formatting or hardening transforms inside vendor/.
	 * Security policy belongs in this wrapper and in validated report inputs.
	 */

	/** @var array<string,mixed> */
	private array $defaults;

	/**
	 * @param array $options {
	 *     @type string $paper_size        Paper size, e.g. A4 or Letter.
	 *     @type string $orientation       portrait|landscape.
	 *     @type string $default_font      Default font family.
	 *     @type bool   $is_html5_parser   Use Dompdf's HTML5 parser.
	 * }
	 */
	public function __construct( array $options = [] ) {
		$this->defaults = array_merge(
			[
				'paper_size'      => 'A4',
				'orientation'     => 'portrait',
				'default_font'    => 'DejaVu Sans',
				'is_html5_parser' => true,
			],
			$options
		);
	}

	public static function is_available(): bool {
		if ( ! file_exists( CB_CORE_DIR . ltrim( self::VENDOR_AUTOLOAD, '/' ) ) ) {
			return false;
		}

		$version_file = CB_CORE_DIR . 'src/PDF/lib/dompdf/vendor/dompdf/dompdf/VERSION';
		$version      = is_readable( $version_file ) ? trim( (string) file_get_contents( $version_file ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( self::EXPECTED_DOMPDF_VERSION !== $version ) {
			return false;
		}

		foreach ( self::REQUIRED_EXTENSIONS as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				return false;
			}
		}

		if ( class_exists( '\Dompdf\Dompdf', false ) && ! self::is_own_dompdf_loaded() ) {
			return false;
		}

		return true;
	}

	/**
	 * Render a complete HTML document to a PDF binary.
	 *
	 * Remote resources, embedded PHP and embedded JavaScript are always disabled
	 * and cannot be re-enabled through caller options.
	 *
	 * @throws RendererException When rendering fails.
	 */
	public function render( string $html, array $options = [] ): string {
		$this->require_engine();
		$opts = array_merge( $this->defaults, $options );

		try {
			$dompdf = new \Dompdf\Dompdf( $this->build_dompdf_options( $opts ) );
			$dompdf->setPaper( (string) $opts['paper_size'], (string) $opts['orientation'] );
			$dompdf->loadHtml( $html );
			$dompdf->render();
			$output = $dompdf->output();
		} catch ( \Throwable $e ) {
			throw new RendererException(
				'PDF rendering failed: ' . $e->getMessage(),
				(int) $e->getCode(),
				$e
			);
		}

		if ( ! is_string( $output ) || '' === $output || ! str_starts_with( $output, '%PDF-' ) ) {
			throw new RendererException( 'PDF rendering produced invalid output.' );
		}

		return $output;
	}

	private function require_engine(): void {
		$missing_extensions = array_values( array_filter(
			self::REQUIRED_EXTENSIONS,
			static fn ( string $extension ): bool => ! extension_loaded( $extension )
		) );
		if ( ! empty( $missing_extensions ) ) {
			throw new RendererException(
				'Dompdf requires the following PHP extensions: ' . implode( ', ', $missing_extensions )
			);
		}

		if ( class_exists( '\Dompdf\Dompdf', false ) ) {
			if ( ! self::is_own_dompdf_loaded() ) {
				throw new RendererException( 'A different Dompdf installation is already loaded in this request.' );
			}
			return;
		}

		if ( ! self::is_available() ) {
			throw new RendererException(
				'Dompdf vendor library is missing. Expected at: '
				. CB_CORE_DIR . ltrim( self::VENDOR_AUTOLOAD, '/' )
			);
		}

		require_once CB_CORE_DIR . ltrim( self::VENDOR_AUTOLOAD, '/' );

		if ( ! class_exists( '\Dompdf\Dompdf', true ) ) {
			throw new RendererException(
				'Dompdf autoloader was loaded but \Dompdf\Dompdf is unavailable.'
			);
		}

		if ( ! self::is_own_dompdf_loaded() ) {
			throw new RendererException( 'Dompdf loaded from an unexpected location.' );
		}
	}

	private static function is_own_dompdf_loaded(): bool {
		try {
			$reflection = new \ReflectionClass( '\Dompdf\Dompdf' );
			$file       = $reflection->getFileName();
			$own_root   = realpath( CB_CORE_DIR . 'src/PDF/lib/dompdf/vendor/dompdf/dompdf' );
			$loaded     = is_string( $file ) ? realpath( $file ) : false;
		} catch ( \ReflectionException $e ) {
			return false;
		}

		if ( false === $own_root || false === $loaded ) {
			return false;
		}

		$prefix = rtrim( $own_root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
		return 0 === strncmp( $loaded, $prefix, strlen( $prefix ) );
	}

	private function build_dompdf_options( array $opts ): \Dompdf\Options {
		$dompdf_options = new \Dompdf\Options();
		$dompdf_options->setDefaultFont( (string) $opts['default_font'] );
		$dompdf_options->setIsHtml5ParserEnabled( (bool) $opts['is_html5_parser'] );

		// Hard security boundary: report HTML is self-contained. Callers cannot
		// opt back into network requests, embedded PHP or PDF JavaScript.
		$dompdf_options->setIsRemoteEnabled( false );
		$dompdf_options->setIsPhpEnabled( false );
		$dompdf_options->setIsJavascriptEnabled( false );

		// Dompdf 3.1.6 adds an estimated decoded-image memory limit. Keep this
		// conservative; report logos are separately bounded before embedding.
		if ( method_exists( $dompdf_options, 'setImageByteSizeLimit' ) ) {
			$dompdf_options->setImageByteSizeLimit( '32M' );
		}

		return $dompdf_options;
	}
}
