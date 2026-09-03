<?php
declare(strict_types=1);
/**
 * User Roles module master-switch state.
 *
 * Missing option means enabled so existing installations keep their current
 * behavior until an operator explicitly switches the module off.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Permissions;

use CB\Core\Log\AuditLog;

use CB\Core\Modules\ModuleStateInterface;
defined( 'ABSPATH' ) || exit;

final class UserRolesState implements ModuleStateInterface {
	private const OPTION = 'cb_core_user_roles_enabled';

	public static function is_enabled(): bool {
		return '0' !== (string) get_option( self::OPTION, '1' );
	}

	public static function set_enabled( bool $enabled, string $actor = 'unknown' ): void {
		$was = self::is_enabled();
		if ( $was === $enabled ) {
			return;
		}

		update_option( self::OPTION, $enabled ? '1' : '0', false );
		if ( self::is_enabled() !== $enabled ) {
			throw new \RuntimeException( __( 'User Roles state could not be persisted.', 'core-blueprint' ) );
		}

		if ( class_exists( AuditLog::class ) ) {
			AuditLog::log(
				$enabled ? 'user_roles_subsystem_enabled' : 'user_roles_subsystem_disabled',
				'notice',
				[ 'actor' => $actor ]
			);
		}
	}
}
