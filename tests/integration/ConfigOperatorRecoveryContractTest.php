<?php
declare(strict_types=1);

use CB\Core\CLI\Commands\Operator\Add as OperatorAdd;
use CB\Core\Permissions\ConfigOperatorRecovery;
use CB\Core\Permissions\PrivilegedAccessRegistry;
use CB\Core\Permissions\RolePolicySchema;
use CB\Core\Permissions\Roles;
use CB\Core\Permissions\UserRoleAssignments;

final class CB_Base_Config_Operator_Recovery_Contract_Test extends WP_UnitTestCase {

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_wp_config_recovery_is_bound_to_one_existing_operator_and_only_reapproves(): void {
        RolePolicySchema::repair();
        update_option( 'cb_core_privileged_guard_bootstrapped', time(), false );

        $user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        $add = ( new OperatorAdd() )->execute( [ 'user' => (string) $user_id ] );
        self::assertSame( 'success', $add->status );

        $user = get_userdata( $user_id );
        self::assertInstanceOf( WP_User::class, $user );
        self::assertContains( 'administrator', (array) $user->roles );
        self::assertContains( Roles::OPERATOR_ROLE, (array) $user->roles );
        self::assertTrue( PrivilegedAccessRegistry::is_approved( $user ) );

        // Invalidate the exact signed state while preserving both roles.
        $user->add_cap( 'cb_manage_snippets', true );
        $user = get_userdata( $user_id );
        self::assertInstanceOf( WP_User::class, $user );
        self::assertFalse( PrivilegedAccessRegistry::is_approved( $user ) );
        self::assertNotEmpty( PrivilegedAccessRegistry::review_state( $user ) );

        define( 'CB_CORE_OPERATOR_RECOVERY', true );
        define( 'CB_CORE_OPERATOR_RECOVERY_USER', (string) $user_id );
        wp_set_current_user( $user_id );

        $user = wp_get_current_user();
        self::assertTrue( ConfigOperatorRecovery::matches_configured_user( $user ) );
        self::assertTrue( ConfigOperatorRecovery::allows_temporary_operator_access( $user ) );
        self::assertTrue( current_user_can( 'manage_options' ), 'The exact configured Operator could not reach wp-admin recovery while unapproved.' );

        $result = ConfigOperatorRecovery::recover_current_user();
        self::assertTrue( $result );

        $user = get_userdata( $user_id );
        self::assertInstanceOf( WP_User::class, $user );
        self::assertContains( 'administrator', (array) $user->roles );
        self::assertContains( Roles::OPERATOR_ROLE, (array) $user->roles );
        self::assertSame( 'administrator', UserRoleAssignments::base_role( $user ) );
        self::assertTrue( PrivilegedAccessRegistry::is_approved( $user ) );
        self::assertSame( [], PrivilegedAccessRegistry::review_state( $user ) );

        // The server authorization is identity-bound; another signed-in user
        // cannot consume the configured recovery authority.
        $other_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
        wp_set_current_user( $other_id );
        $other_result = ConfigOperatorRecovery::recover_current_user();
        self::assertWPError( $other_result );
        self::assertSame( 'cb_core_config_recovery_identity', $other_result->get_error_code() );

        // Config recovery must never manufacture cb_operator when that role is
        // missing. Role recovery is intentionally a separate, stronger path.
        wp_set_current_user( $user_id );
        $user = get_userdata( $user_id );
        self::assertInstanceOf( WP_User::class, $user );
        $user->remove_role( Roles::OPERATOR_ROLE );

        $user = get_userdata( $user_id );
        self::assertInstanceOf( WP_User::class, $user );
        self::assertNotContains( Roles::OPERATOR_ROLE, (array) $user->roles );
        self::assertFalse( ConfigOperatorRecovery::allows_temporary_operator_access( $user ) );

        $missing_role_result = ConfigOperatorRecovery::recover_current_user();
        self::assertWPError( $missing_role_result );
        self::assertSame( 'cb_core_config_recovery_operator_missing', $missing_role_result->get_error_code() );
        self::assertNotContains( Roles::OPERATOR_ROLE, (array) get_userdata( $user_id )->roles );
    }
}
