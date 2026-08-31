<?php
declare(strict_types=1);
/**
 * Failsafe\CloseWindow - `wp cb failsafe close-window` (state).
 *
 * Closes any active 60-minute bypass window opened by the secret URL.
 * If no window is active this is a no-op. State-change because closing
 * the window strengthens enforcement; banner-warning, no confirm-modal.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */


namespace CB\Core\CLI\Commands\Failsafe;

use CB\Core\Console\CommandInterface;
use CB\Core\Console\Result;

defined( 'ABSPATH' ) || exit;

final class CloseWindow implements CommandInterface {

	public function execute( array $args ): Result {
		$layers_before = \CB\Core\Security\Failsafe::active_layers();
		\CB\Core\Security\Failsafe::close_bypass_window();
		$layers_after = \CB\Core\Security\Failsafe::active_layers();

		$lines = [];
		if ( ! empty( $layers_before['transient'] ) ) {
			$lines[] = 'Active bypass window closed.';
			$lines[] = 'Layer 3 transient cleared.';
			$message = __( 'Bypass window closed. Layer 3 transient cleared.', 'core-blueprint' );
		} else {
			$lines[] = 'No active bypass window. Nothing to do.';
			$message = __( 'No active bypass window - nothing to close.', 'core-blueprint' );
		}

		return Result::success( $message, $lines, [ 'layers_before' => $layers_before, 'layers_after' => $layers_after ] );
	}

	public function args_schema(): array {
		return [];
	}

	public function side_effects(): string {
		return 'state';
	}

	/**
	 * Close any active 60-minute bypass window.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cb failsafe close-window
	 *
	 * @when after_wp_load
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		\CB\Core\Security\Failsafe::close_bypass_window();
		\WP_CLI::success( 'Any active bypass window has been closed.' );
	}
}
