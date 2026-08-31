<?php
declare(strict_types=1);
/**
 * Mail module master-switch state.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Mail;

use CB\Core\Log\AuditLog;

use CB\Core\Modules\ModuleStateInterface;

defined( 'ABSPATH' ) || exit;

final class State implements ModuleStateInterface {
	public static function is_enabled(): bool {
		return Settings::enabled();
	}

	/**
	 * Persist the Mail module state. Enabling makes the functional Mail page
	 * available; Runtime remains fail-closed until provider configuration is
	 * complete and no competing mail transport is active.
	 */
	public static function set_enabled( bool $enabled, string $actor = 'unknown' ): void {
		$current = Settings::all();
		$was     = ! empty( $current['enabled'] );
		if ( $was === $enabled ) {
			return;
		}


		$current['enabled'] = $enabled;
		Settings::save( $current );

		if ( class_exists( AuditLog::class ) ) {
			AuditLog::log(
				$enabled ? 'mail_subsystem_enabled' : 'mail_subsystem_disabled',
				'notice',
				[ 'actor' => $actor, 'provider' => Settings::provider() ]
			);
		}
	}
}
