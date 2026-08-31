<?php
declare(strict_types=1);
/**
 * Branding - AJAX handlers for the Preferences → Report Branding tab.
 *
 * Two endpoints:
 *
 *   wp_ajax_cb_core_save_report_branding
 *     POST. Validates and persists logo_attachment_id, provider_name,
 *     provider_contact, and accent_color into reports.branding.* in one
 *     atomic write. Returns the resolved logo URL so the client can
 *     refresh both the picker thumbnail and the live preview without
 *     a second request.
 *
 *   wp_ajax_cb_core_reset_report_branding
 *     POST. Restores the raw Reports branding settings defaults. Existing
 *     report content remains immutable; branding is applied at PDF render time.
 *
 * Capability gate: cb_manage_branding (operator-only - no admin-toggle
 * for branding, by design).
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Ajax\Handlers;

use CB\Core\Ajax\Guards;
use CB\Core\Ajax\Request;
use CB\Core\Log\AuditLog;
use CB\Core\Reports\ReportBranding;
use CB\Core\Settings;

defined( 'ABSPATH' ) || exit;

final class Branding {

	use Guards;

	public static function init(): void {
		add_action( 'wp_ajax_cb_core_save_report_branding',  [ __CLASS__, 'save' ] );
		add_action( 'wp_ajax_cb_core_reset_report_branding', [ __CLASS__, 'reset' ] );
	}

	// ─── Save ─────────────────────────────────────────────────────────────────

	public static function save(): void {
		Request::nonce( 'cb_core_admin' );
		self::require_manage_branding();

		// Resolve attachment ID - accept 0 to mean "no logo".
		$logo_id = Request::int( 'logo_attachment_id', 0 );
		if ( $logo_id < 0 ) {
			$logo_id = 0;
		}

		// PDF branding accepts only bounded local JPEG, PNG or SVG attachments.
		if ( $logo_id > 0 ) {
			$post = get_post( $logo_id );
			if ( ! $post || 'attachment' !== $post->post_type || ! ReportBranding::is_supported_logo_attachment( $logo_id ) ) {
				wp_send_json_error( [
					'message' => __( 'Logo must be a local JPEG, PNG or SVG image no larger than 2 MB. Raster logos may be at most 4096 x 4096 pixels.', 'core-blueprint' ),
				], 400 );
			}
		}

		$provider_name    = sanitize_text_field( Request::text( 'provider_name', '' ) );
		$provider_contact = sanitize_text_field( Request::text( 'provider_contact', '' ) );

		// Length bounds match the maxlength attributes on the form. Clamp
		// rather than reject so paste-from-typo isn't a hard error.
		if ( mb_strlen( $provider_name ) > 120 ) {
			$provider_name = mb_substr( $provider_name, 0, 120 );
		}
		if ( mb_strlen( $provider_contact ) > 200 ) {
			$provider_contact = mb_substr( $provider_contact, 0, 200 );
		}

		// Hex colour: accept #RRGGBB only. Falls back to default on
		// invalid input rather than rejecting - the colour input element
		// in the form already constrains the picker; this is a belt-and-
		// braces guard against direct API hits.
		$accent_color = sanitize_hex_color( Request::text( 'accent_color', '' ) );
		if ( null === $accent_color || '' === $accent_color ) {
			$accent_color = ReportBranding::DEFAULT_ACCENT;
		}

		// Read full settings, mutate the nested branding block, write the
		// whole 'reports' top-level back. Settings::set_key works on top-
		// level keys only - passing 'reports.branding' as the key would
		// create a literal "reports.branding" entry alongside the real
		// nested structure rather than updating it.
		$settings = Settings::get();
		$reports  = is_array( $settings['reports'] ?? null ) ? $settings['reports'] : [];

		$reports['branding'] = [
			'logo_attachment_id' => $logo_id,
			'provider_name'       => $provider_name,
			'provider_contact'    => $provider_contact,
			'accent_color'       => $accent_color,
		];

		Settings::set_key( 'reports', $reports, 'preferences.report_branding' );

		AuditLog::log( 'reports.branding_updated', 'notice', [
			'logo_attachment_id' => $logo_id,
			'has_provider_name'   => '' !== $provider_name,
			'has_provider_contact' => '' !== $provider_contact,
			'accent_color'       => $accent_color,
			'by'                 => get_current_user_id(),
		] );

		// Return the resolved logo URL so the client can refresh its
		// preview without an additional REST/AJAX round-trip. Uses the
		// size-fallback helper for robust resolution against attachments
		// that lack a 'medium' size variant.
		$logo_url = $logo_id > 0
			? ReportBranding::attachment_url( $logo_id, 'medium' )
			: '';

		wp_send_json_success( [
			'logo_attachment_id' => $logo_id,
			'logo_url'           => $logo_url,
			'provider_name'       => $provider_name,
			'provider_contact'    => $provider_contact,
			'accent_color'       => $accent_color,
		] );
	}

	// ─── Reset ────────────────────────────────────────────────────────────────

	public static function reset(): void {
		Request::nonce( 'cb_core_admin' );
		self::require_manage_branding();

		$defaults = ReportBranding::settings_defaults();

		$settings            = Settings::get();
		$reports             = is_array( $settings['reports'] ?? null ) ? $settings['reports'] : [];
		$reports['branding'] = $defaults;
		Settings::set_key( 'reports', $reports, 'preferences.report_branding' );

		AuditLog::log( 'reports.branding_reset', 'notice', [
			'by' => get_current_user_id(),
		] );

		wp_send_json_success( [
			'logo_attachment_id' => (int) ( $defaults['logo_attachment_id'] ?? 0 ),
			'logo_url'           => '',
			'provider_name'       => '',
			'provider_contact'    => '',
			'accent_color'       => (string) ( $defaults['accent_color'] ?? '#0064c8' ),
		] );
	}

	// ─── Internals ────────────────────────────────────────────────────────────

	private static function require_manage_branding(): void {
		if ( ! current_user_can( 'cb_manage_branding' ) ) {
			wp_send_json_error( [
				'message' => __( 'Only Core Blueprint operators may change report branding.', 'core-blueprint' ),
			], 403 );
		}
	}
}
