<?php
declare(strict_types=1);
/**
 * Package Downloads module master-switch state.
 *
 * Missing option means enabled so existing installations keep their current
 * behavior until an operator explicitly switches the module off.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\PackageDownload;

use CB\Core\Log\AuditLog;

use CB\Core\Modules\ModuleStateInterface;

defined( 'ABSPATH' ) || exit;

final class State implements ModuleStateInterface {
	private const OPTION = 'cb_core_package_download_enabled';

	public static function is_enabled(): bool {
		return '0' !== (string) get_option( self::OPTION, '1' );
	}

	public static function set_enabled( bool $enabled, string $actor = 'unknown' ): void {
		$was = self::is_enabled();
		if ( $was === $enabled ) {
			return;
		}

		update_option( self::OPTION, $enabled ? '1' : '0', false );
		if ( class_exists( AuditLog::class ) ) {
			AuditLog::log(
				$enabled ? 'package_download_subsystem_enabled' : 'package_download_subsystem_disabled',
				'notice',
				[ 'actor' => $actor ]
			);
		}
	}
}
