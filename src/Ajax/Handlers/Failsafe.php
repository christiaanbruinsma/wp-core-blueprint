<?php
declare(strict_types=1);
/**
 * Failsafe - AJAX handlers for the Failsafe subsystem.
 *
 * Destructive actions (token rotation, panic bypass) require password
 * reconfirmation on top of the cap check. Deactivating a bypass does
 * not - you can always turn protections back on.
 *
 * @package Core_Blueprint
 */

namespace CB\Core\Ajax\Handlers;

use CB\Core\Admin\Admin;
use CB\Core\Ajax\Guards;
use CB\Core\Ajax\Request;
use CB\Core\Security\Failsafe as SecurityFailsafe;

defined( 'ABSPATH' ) || exit;

final class Failsafe {
	use Guards;

	public static function init(): void {
		add_action( 'wp_ajax_cb_core_rotate_token',     [ __CLASS__, 'rotate_token' ] );
		add_action( 'wp_ajax_cb_core_panic_activate',   [ __CLASS__, 'panic_activate' ] );
		add_action( 'wp_ajax_cb_core_panic_deactivate', [ __CLASS__, 'panic_deactivate' ] );
		add_action( 'wp_ajax_cb_core_close_window',     [ __CLASS__, 'close_bypass_window' ] );
	}

	public static function rotate_token(): void {
		Request::nonce( 'cb_core_admin' );
		self::require_admin();
		self::require_password_reconfirm();

		$token = SecurityFailsafe::rotate_token();

		// Flash the new token via a short-lived user-scoped transient so
		// the next page load can display it exactly once.
		set_transient( 'cb_core_new_token_' . get_current_user_id(), $token, 60 );

		wp_send_json_success( [
			'message' => __( 'Token rotated. Redirecting to the failsafe page to display it.', 'core-blueprint' ),
			'url'     => admin_url( 'admin.php?page=' . Admin::SAFEGUARDS_SLUG . '&tab=failsafe' ),
		] );
	}

	public static function panic_activate(): void {
		Request::nonce( 'cb_core_admin' );
		self::require_admin();
		self::require_password_reconfirm();

		// `reason` is optional - the audit-log entry expects null for "no
		// reason supplied", distinct from an explicit empty string. Request
		// helper returns '' when a field is absent, so normalise back to null.
		$reason = Request::text( 'reason' );
		$reason = '' === $reason ? null : $reason;

		$user = wp_get_current_user();
		SecurityFailsafe::activate_emergency_bypass( 'admin:' . $user->user_login, $reason );

		wp_send_json_success( [
			'message' => __( 'Emergency bypass activated. All restrictive features are now disabled.', 'core-blueprint' ),
		] );
	}

	public static function panic_deactivate(): void {
		Request::nonce( 'cb_core_admin' );
		self::require_admin();

		$user = wp_get_current_user();
		SecurityFailsafe::deactivate_emergency_bypass( 'admin:' . $user->user_login );

		wp_send_json_success( [
			'message' => __( 'Emergency bypass deactivated.', 'core-blueprint' ),
		] );
	}

	public static function close_bypass_window(): void {
		Request::nonce( 'cb_core_admin' );
		self::require_admin();

		SecurityFailsafe::close_bypass_window();

		wp_send_json_success( [
			'message' => __( 'Active bypass window closed.', 'core-blueprint' ),
		] );
	}
}
