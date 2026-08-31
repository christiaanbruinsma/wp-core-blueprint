<?php
declare(strict_types=1);
/**
 * Permissions\RepairRolePolicy - explicit role-policy recovery.
 *
 * Server WP-CLI is the break-glass path and does not depend on a WordPress
 * user's current role state. The browser Console is separately gated by both
 * cb_use_cli and cb_manage_roles in Console\Registry/RunController.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\CLI\Commands\Permissions;

use CB\Core\Console\CommandInterface;
use CB\Core\Console\Result;
use CB\Core\Permissions\RolePolicySchema;

defined( 'ABSPATH' ) || exit;

final class RepairRolePolicy implements CommandInterface {

	public function execute( array $args ): Result {
		$is_wp_cli = defined( 'WP_CLI' ) && WP_CLI;
		if ( ! $is_wp_cli && ! current_user_can( 'cb_manage_roles' ) ) {
			return Result::error( __( 'You do not have permission to repair the Core Blueprint role policy.', 'core-blueprint' ) );
		}

		$result = RolePolicySchema::repair();
		if ( ! $result['canonical'] ) {
			return Result::error(
				__( 'Role policy repair could not restore a canonical state. Review the reported issues before continuing.', 'core-blueprint' ),
				array_map( static fn ( string $issue ): string => '  - ' . $issue, $result['issues_after'] ),
				$result
			);
		}
		if ( ! $result['changed'] ) {
			return Result::success(
				__( 'Role policy is already canonical; no role or capability changes were required.', 'core-blueprint' ),
				[ 'Role Policy: already canonical.' ],
				$result
			);
		}

		return Result::success(
			__( 'Canonical Core Blueprint role definitions and capabilities were repaired.', 'core-blueprint' ),
			[
				'Role Policy: repaired.',
				'User role assignments: unchanged.',
				'Privileged approvals / Needs Review / Trust Schema: unchanged by this command.',
			],
			$result
		);
	}

	public function args_schema(): array {
		return [];
	}

	public function side_effects(): string {
		return 'destructive';
	}

	/**
	 * Repair the canonical Core Blueprint role/capability policy.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb permissions repair-role-policy
	 *
	 * @when after_wp_load
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$result = $this->execute( $assoc_args );
		foreach ( $result->lines as $line ) {
			\WP_CLI::line( $line );
		}
		if ( 'error' === $result->status ) {
			\WP_CLI::error( $result->message );
		}
		if ( '' !== $result->message ) {
			\WP_CLI::success( $result->message );
		}
	}
}
