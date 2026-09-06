<?php
declare(strict_types=1);
/**
 * ConfigOperatorRecovery
 *
 * Narrow break-glass recovery for an existing CB Operator when trusted
 * server-side WP-CLI is unavailable but the site owner can edit wp-config.php.
 *
 * Recovery is explicitly scoped to one configured WordPress identity. It does
 * not assign WordPress roles, does not add cb_operator, and does not create a
 * general Core Blueprint security bypass. The configured user must still sign
 * in with normal WordPress credentials before re-approving the exact current
 * privileged fingerprint.
 *
 * Temporary wp-config.php authorization:
 *
 * define( 'CB_CORE_OPERATOR_RECOVERY', true );
 * define( 'CB_CORE_OPERATOR_RECOVERY_USER', 'admin@example.com' );
 *
 * Remove both constants immediately after recovery.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Permissions;

use CB\Core\Log\AuditLog;

defined( 'ABSPATH' ) || exit;

final class ConfigOperatorRecovery {

	private const PAGE_SLUG = 'cb-core-operator-recovery';
	private const ACTION    = 'cb_core_operator_config_recover';
	private const NONCE     = 'cb_core_operator_config_recover';

	private static bool $bootstrapped = false;

	public static function init(): void {
		if ( self::$bootstrapped ) {
			return;
		}
		self::$bootstrapped = true;

		add_action( 'admin_menu', [ __CLASS__, 'register_page' ], 99 );
		add_action( 'admin_notices', [ __CLASS__, 'admin_notice' ], 1 );
		add_action( 'admin_post_' . self::ACTION, [ __CLASS__, 'handle_recovery' ] );
	}

	/** Whether server-side recovery authorization is explicitly enabled. */
	public static function is_enabled(): bool {
		return defined( 'CB_CORE_OPERATOR_RECOVERY' )
			&& true === CB_CORE_OPERATOR_RECOVERY
			&& defined( 'CB_CORE_OPERATOR_RECOVERY_USER' )
			&& '' !== trim( (string) CB_CORE_OPERATOR_RECOVERY_USER );
	}

	/** Resolve the configured target by ID, email, or login. */
	public static function configured_user(): ?\WP_User {
		if ( ! self::is_enabled() ) {
			return null;
		}

		$ref = trim( (string) CB_CORE_OPERATOR_RECOVERY_USER );
		if ( ctype_digit( $ref ) ) {
			$user = get_userdata( (int) $ref );
			if ( $user instanceof \WP_User ) {
				return $user;
			}
		}
		if ( str_contains( $ref, '@' ) ) {
			$user = get_user_by( 'email', $ref );
			if ( $user instanceof \WP_User ) {
				return $user;
			}
		}

		$user = get_user_by( 'login', $ref );
		return $user instanceof \WP_User ? $user : null;
	}

	/** Whether this exact WordPress identity is the server-authorized target. */
	public static function matches_configured_user( \WP_User $user ): bool {
		$target = self::configured_user();
		return $target instanceof \WP_User
			&& (int) $target->ID > 0
			&& (int) $target->ID === (int) $user->ID;
	}

	/**
	 * Narrow capability escape hatch used only by PrivilegedAccessGuard.
	 *
	 * The server-authorized account must already be a CB Operator and must still
	 * have a normal WordPress base role. This deliberately cannot repair role
	 * loss or turn an arbitrary account into an Operator.
	 */
	public static function allows_temporary_operator_access( \WP_User $user ): bool {
		return self::matches_configured_user( $user )
			&& in_array( Roles::OPERATOR_ROLE, (array) $user->roles, true )
			&& '' !== UserRoleAssignments::base_role( $user );
	}

	/** Hidden recovery page, registered only for the configured signed-in user. */
	public static function register_page(): void {
		$user = wp_get_current_user();
		if ( ! ( $user instanceof \WP_User ) || ! self::matches_configured_user( $user ) ) {
			return;
		}

		add_submenu_page(
			null,
			__( 'Core Blueprint Operator Recovery', 'core-blueprint' ),
			__( 'Core Blueprint Operator Recovery', 'core-blueprint' ),
			'read',
			self::PAGE_SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	/** Persistent warning while wp-config.php recovery authorization exists. */
	public static function admin_notice(): void {
		$user = wp_get_current_user();
		if ( ! ( $user instanceof \WP_User ) || ! self::matches_configured_user( $user ) ) {
			return;
		}

		$approved = PrivilegedAccessRegistry::is_approved( $user );
		$operator = in_array( Roles::OPERATOR_ROLE, (array) $user->roles, true );
		$base     = UserRoleAssignments::base_role( $user );

		echo '<div class="notice notice-warning"><p><strong>';
		esc_html_e( 'Core Blueprint: server-authorized Operator recovery is active.', 'core-blueprint' );
		echo '</strong> ';

		if ( $approved ) {
			esc_html_e( 'This identity already has a valid signed approval. Remove the temporary recovery constants from wp-config.php now.', 'core-blueprint' );
		} elseif ( ! $operator ) {
			esc_html_e( 'The configured identity is not a CB Operator. Config recovery never assigns roles; use a trusted administrative recovery path instead.', 'core-blueprint' );
		} elseif ( '' === $base ) {
			esc_html_e( 'The configured identity has no normal WordPress base role. Config recovery cannot restore lost WordPress roles.', 'core-blueprint' );
		} else {
			echo esc_html__( 'This exact signed-in CB Operator may re-approve its current privileged fingerprint.', 'core-blueprint' );
			echo ' <a href="' . esc_url( self::page_url() ) . '">';
			esc_html_e( 'Open recovery', 'core-blueprint' );
			echo '</a>';
		}

		echo '</p></div>';
	}

	public static function render_page(): void {
		$user = wp_get_current_user();
		if ( ! ( $user instanceof \WP_User ) || ! self::matches_configured_user( $user ) || ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'This recovery page is not authorized for the current identity.', 'core-blueprint' ), '', [ 'response' => 403 ] );
		}

		$operator = in_array( Roles::OPERATOR_ROLE, (array) $user->roles, true );
		$base     = UserRoleAssignments::base_role( $user );
		$approved = PrivilegedAccessRegistry::is_approved( $user );
		$policy   = RolePolicySchema::inspect( false, 'config_operator_recovery_page' );
		$ready    = $operator && '' !== $base && (bool) $policy['canonical'] && PrivilegedAccessPolicy::is_privileged( $user );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Core Blueprint Operator Recovery', 'core-blueprint' ) . '</h1>';
		echo '<div class="notice notice-warning inline"><p>';
		esc_html_e( 'This page exists only because temporary server-side recovery constants are present in wp-config.php. Remove those constants immediately after recovery.', 'core-blueprint' );
		echo '</p></div>';
		echo '<table class="widefat striped" style="max-width:760px"><tbody>';
		self::row( __( 'User', 'core-blueprint' ), sprintf( '%s (#%d)', $user->user_login, $user->ID ) );
		self::row( __( 'Roles', 'core-blueprint' ), implode( ', ', (array) $user->roles ) );
		self::row( __( 'Base role', 'core-blueprint' ), '' !== $base ? $base : '-' );
		self::row( __( 'CB Operator', 'core-blueprint' ), $operator ? 'YES' : 'NO' );
		self::row( __( 'Role Policy canonical', 'core-blueprint' ), $policy['canonical'] ? 'YES' : 'NO' );
		self::row( __( 'Signed approval', 'core-blueprint' ), $approved ? 'VALID' : 'NOT VALID' );
		echo '</tbody></table>';

		if ( isset( $_GET['recovered'] ) && '1' === (string) $_GET['recovered'] && $approved ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- status-only redirect flag.
			echo '<div class="notice notice-success inline"><p><strong>';
			esc_html_e( 'CB Operator approval recovered successfully.', 'core-blueprint' );
			echo '</strong> ';
			esc_html_e( 'Remove CB_CORE_OPERATOR_RECOVERY and CB_CORE_OPERATOR_RECOVERY_USER from wp-config.php now.', 'core-blueprint' );
			echo '</p></div>';
		}

		if ( ! $approved && $ready ) {
			echo '<p>' . esc_html__( 'Recovery will sign only this Operator’s exact current privileged fingerprint. It will not add or replace WordPress roles.', 'core-blueprint' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
			wp_nonce_field( self::NONCE );
			submit_button( __( 'Re-approve CB Operator', 'core-blueprint' ), 'primary' );
			echo '</form>';
		} elseif ( ! $approved ) {
			echo '<div class="notice notice-error inline"><p>';
			esc_html_e( 'Recovery is blocked because this identity is not an existing CB Operator with a normal WordPress base role and canonical Role Policy.', 'core-blueprint' );
			echo '</p></div>';
		}

		echo '</div>';
	}

	/**
	 * Re-approve the exact current Operator fingerprint for the configured,
	 * currently authenticated identity. No role mutation is permitted here.
	 *
	 * @return true|\WP_Error
	 */
	public static function recover_current_user(): true|\WP_Error {
		$user = wp_get_current_user();
		if ( ! ( $user instanceof \WP_User ) || ! $user->ID || ! self::matches_configured_user( $user ) ) {
			return new \WP_Error( 'cb_core_config_recovery_identity', __( 'The signed-in identity does not match the wp-config.php recovery target.', 'core-blueprint' ) );
		}
		if ( ! in_array( Roles::OPERATOR_ROLE, (array) $user->roles, true ) ) {
			return new \WP_Error( 'cb_core_config_recovery_operator_missing', __( 'Config recovery requires an existing CB Operator assignment and will not add the role.', 'core-blueprint' ) );
		}

		$base = UserRoleAssignments::base_role( $user );
		if ( '' === $base ) {
			return new \WP_Error( 'cb_core_config_recovery_base_role_missing', __( 'Config recovery requires a normal WordPress base role and cannot restore a lost role.', 'core-blueprint' ) );
		}

		$policy = RolePolicySchema::inspect( false, 'config_operator_recover' );
		if ( ! $policy['canonical'] ) {
			return new \WP_Error( 'cb_core_config_recovery_role_policy', __( 'Config recovery cannot approve trust while the Core Blueprint Role Policy is non-canonical.', 'core-blueprint' ) );
		}
		if ( ! PrivilegedAccessPolicy::is_privileged( $user ) ) {
			return new \WP_Error( 'cb_core_config_recovery_not_privileged', __( 'The configured Operator is not classified as privileged.', 'core-blueprint' ) );
		}
		if ( PrivilegedAccessRegistry::is_approved( $user ) ) {
			return true;
		}

		$review = PrivilegedAccessRegistry::review_state( $user );
		if ( ! PrivilegedAccessRegistry::approve( $user, (int) $user->ID, 'config_operator_recover' ) || ! PrivilegedAccessRegistry::is_approved( $user ) ) {
			return new \WP_Error( 'cb_core_config_recovery_approval_failed', __( 'Core Blueprint could not create a valid signed approval for the current Operator fingerprint.', 'core-blueprint' ) );
		}

		AuditLog::log( 'permissions.operator.config.recovered', 'warning', [
			'user_id'                => (int) $user->ID,
			'user_login'             => (string) $user->user_login,
			'roles'                  => array_values( (array) $user->roles ),
			'base_role'              => $base,
			'previous_review_reason' => (string) ( $review['reason'] ?? '' ),
			'previous_review_source' => (string) ( $review['source'] ?? '' ),
			'by'                     => 'wp_config',
		] );

		return true;
	}

	public static function handle_recovery(): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'Authentication is required for Operator recovery.', 'core-blueprint' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( self::NONCE );

		$result = self::recover_current_user();
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), esc_html__( 'Core Blueprint Operator Recovery', 'core-blueprint' ), [ 'response' => 403 ] );
		}

		wp_safe_redirect( add_query_arg( 'recovered', '1', self::page_url() ) );
		exit;
	}

	private static function page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	private static function row( string $label, string $value ): void {
		echo '<tr><th scope="row" style="width:220px">' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
	}
}
