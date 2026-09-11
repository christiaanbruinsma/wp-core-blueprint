<?php
declare(strict_types=1);
/**
 * Aggregate Mail module state used by the existing Dashboard activation card.
 *
 * Mail Delivery and Mail Designer are independent runtime capabilities. This
 * compatibility state is enabled whenever either capability is enabled.
 * Enabling the legacy module from Dashboard preserves historic behaviour by
 * enabling Delivery; disabling it is an explicit master-off and disables both.
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

	public static function set_enabled( bool $enabled, string $actor = 'unknown' ): void {
		$current = Settings::all();
		$was = self::is_enabled();
		if ( $was === $enabled ) {
			return;
		}

		$previous = $current;
		if ( $enabled ) {
			// Preserve the pre-split Dashboard meaning: activating Mail enables
			// outbound delivery. Designer remains explicit opt-in.
			$current['delivery_enabled'] = true;
		} else {
			$current['delivery_enabled'] = false;
			$current['designer_enabled'] = false;
		}
		Settings::save( $current );

		if ( self::is_enabled() !== $enabled ) {
			Settings::save( $previous );
			throw new \RuntimeException( __( 'Mail state could not be persisted consistently.', 'core-blueprint' ) );
		}

		if ( class_exists( AuditLog::class ) ) {
			AuditLog::log(
				$enabled ? 'mail_subsystem_enabled' : 'mail_subsystem_disabled',
				'notice',
				[
					'actor'    => $actor,
					'provider' => Settings::provider(),
					'delivery' => DeliveryState::is_enabled(),
					'designer' => DesignerState::is_enabled(),
				]
			);
		}
	}
}
