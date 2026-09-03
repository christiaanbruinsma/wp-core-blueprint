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
		$was     = self::is_enabled();
		if ( $was === $enabled ) {
			return;
		}

		$previous = $current;
		$current['enabled'] = $enabled;
		Settings::save( $current );

		// Mail currently mirrors its master state into both the hot enabled option
		// and the cold configuration document. B1 does not redesign that storage;
		// it only requires both existing representations to agree before success is
		// reported. If either write was refused, restore the exact pre-transition
		// settings best-effort before surfacing a hard failure. No runtime hooks or
		// transition audit have run yet, so this compensation is local and safe.
		$config_enabled = ! empty( Settings::all()['enabled'] );
		if ( self::is_enabled() !== $enabled || $config_enabled !== $enabled ) {
			$previous['enabled'] = $was;
			Settings::save( $previous );
			throw new \RuntimeException( __( 'Mail state could not be persisted consistently.', 'core-blueprint' ) );
		}

		if ( class_exists( AuditLog::class ) ) {
			AuditLog::log(
				$enabled ? 'mail_subsystem_enabled' : 'mail_subsystem_disabled',
				'notice',
				[ 'actor' => $actor, 'provider' => Settings::provider() ]
			);
		}
	}
}
