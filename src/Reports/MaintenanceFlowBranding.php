<?php
declare(strict_types=1);

namespace CB\Core\Reports;

use CB\Core\Design\Profile\Document\Render\ImageDataUri;
use CB\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Resolve render-time Maintenance branding for the typed Document Flow path.
 *
 * Admin preview branding remains unchanged. Canonical Flow rendering receives
 * only local PNG/JPEG data URIs and never receives a URL or filesystem path.
 */
final class MaintenanceFlowBranding {
	private const FALLBACK_TEXT = 'Core Blueprint';
	private const RASTER_DENSITY = 192;
	private const RASTER_MAX_W = 400;
	private const RASTER_MAX_H = 110;
	private const MAX_RASTER_BYTES = 4194304;

	/**
	 * @return array{logo_url:string,fallback_text:string,provider_name:string,provider_contact:string,accent_color:string,is_default:bool}
	 */
	public static function resolve(): array {
		$resolved = ReportBranding::current();
		$configured = Settings::get()['reports']['branding'] ?? [];
		$configured = is_array( $configured ) ? $configured : [];
		$logo_id = (int) ( $configured['logo_attachment_id'] ?? 0 );
		$logo = '';

		if ( ReportBranding::is_supported_logo_attachment( $logo_id ) ) {
			$logo = self::validated_document_image( ReportBranding::attachment_data_uri( $logo_id ) );
		}

		if ( '' === $logo ) {
			$logo = self::bundled_fallback_png();
		}

		return [
			'logo_url'          => $logo,
			'fallback_text'     => '' === $logo ? self::FALLBACK_TEXT : '',
			'provider_name'     => (string) ( $resolved['provider_name'] ?? '' ),
			'provider_contact'  => (string) ( $resolved['provider_contact'] ?? '' ),
			'accent_color'      => (string) ( $resolved['accent_color'] ?? ReportBranding::DEFAULT_ACCENT ),
			'is_default'        => (bool) ( $resolved['is_default'] ?? false ),
		];
	}

	private static function validated_document_image( string $data_uri ): string {
		if ( '' === $data_uri ) {
			return '';
		}
		try {
			return ImageDataUri::assert_valid( $data_uri );
		} catch ( \InvalidArgumentException $error ) {
			return '';
		}
	}

	/** Rasterize only the trusted bundled Base SVG; no arbitrary path is accepted. */
	private static function bundled_fallback_png(): string {
		if ( ! extension_loaded( 'imagick' ) || ! class_exists( '\\Imagick' ) || ! class_exists( '\\ImagickPixel' ) ) {
			return '';
		}
		if ( [] === \Imagick::queryFormats( 'SVG' ) || [] === \Imagick::queryFormats( 'PNG' ) ) {
			return '';
		}

		$path = CB_CORE_DIR . ReportBranding::FALLBACK_LOGO_REL;
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return '';
		}
		$svg = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $svg || '' === $svg || strlen( $svg ) > ReportBranding::MAX_LOGO_BYTES ) {
			return '';
		}

		try {
			$image = new \Imagick();
			try {
				$image->setBackgroundColor( new \ImagickPixel( 'transparent' ) );
				$image->setResolution( self::RASTER_DENSITY, self::RASTER_DENSITY );
				$image->readImageBlob( $svg );
				if ( $image->getNumberImages() < 1 ) {
					return '';
				}
				$image->setIteratorIndex( 0 );
				$image->setImageFormat( 'png32' );
				$image->thumbnailImage( self::RASTER_MAX_W, self::RASTER_MAX_H, true );
				$image->setImagePage( 0, 0, 0, 0 );
				$image->stripImage();
				$png = $image->getImageBlob();
				if ( '' === $png || strlen( $png ) > self::MAX_RASTER_BYTES ) {
					return '';
				}
				return self::validated_document_image( 'data:image/png;base64,' . base64_encode( $png ) );
			} finally {
				$image->clear();
			}
		} catch ( \Throwable $error ) {
			return '';
		}
	}

	private function __construct() {}
}
