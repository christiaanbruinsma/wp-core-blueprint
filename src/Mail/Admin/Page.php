<?php
declare(strict_types=1);
/**
 * Core Blueprint Mail admin page.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Mail\Admin;

use CB\Core\Admin\PageBase;
use CB\Core\Admin\TabNav;
use CB\Core\Mail\ConflictDetector;
use CB\Core\Mail\Runtime;
use CB\Core\Mail\Secrets;
use CB\Core\Mail\Settings;
use CB\Core\Mail\State;
use CB\Core\UI\Notice;
use CB\Core\UI\Status;

defined( 'ABSPATH' ) || exit;

final class Page extends PageBase {

	public const SLUG = 'core-blueprint-mail';

	public function slug(): string { return self::SLUG; }
	public function title(): string { return __( 'Mail', 'core-blueprint' ); }
	public function position(): ?int { return 29; }
	public function capability(): string { return 'manage_options'; }

	public function render(): void {
		$this->guard();

		$tabs = [
			'settings' => __( 'Settings', 'core-blueprint' ),
			'test'     => __( 'Test Email', 'core-blueprint' ),
			'logs'     => __( 'Logs', 'core-blueprint' ),
		];
		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab       = isset( $tabs[ $requested ] ) ? $requested : 'settings';

		if ( 'logs' === $tab ) {
			$html = LogsTab::html( self::SLUG, 'logs' );
			echo TabNav::inject( $html, self::SLUG, $tab, $tabs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$settings          = Settings::all();
		$enabled           = State::is_enabled();
		$conflicts         = ConflictDetector::active();
		$runtime_active    = Runtime::is_active();
		$has_brevo_secret  = '' !== Secrets::decrypt( (string) $settings['brevo_api_key'] );
		$has_smtp_password = '' !== Secrets::decrypt( (string) $settings['smtp_password'] );
		$provider_label        = Settings::provider_label();
		$retention_options     = Settings::RETENTION_DAYS;
		$activation_error      = Settings::activation_error( $settings );
		$module_status_html    = Status::render( $enabled ? 'active' : 'idle', $enabled ? __( 'Enabled', 'core-blueprint' ) : __( 'Disabled', 'core-blueprint' ) );
		$runtime_status_html   = Status::render( $runtime_active ? 'active' : 'idle', $runtime_active ? __( 'Active', 'core-blueprint' ) : __( 'Inactive', 'core-blueprint' ) );
		$settings_runtime_status_html = Status::render(
			$runtime_active ? 'active' : ( $enabled && ( ! empty( $conflicts ) || '' !== $activation_error ) ? 'warning' : 'idle' ),
			$runtime_active
				? __( 'Active', 'core-blueprint' )
				: ( '' !== $activation_error ? __( 'Configuration required', 'core-blueprint' ) : ( $enabled && ! empty( $conflicts ) ? __( 'Blocked', 'core-blueprint' ) : __( 'Disabled', 'core-blueprint' ) ) )
		);
		$conflict_status_html  = ! empty( $conflicts )
			? Status::render( 'warning', implode( ', ', $conflicts ) )
			: '';
		$result                  = Actions::pull_result();
		$result_notice_html      = '';
		$result_toast_message    = '';
		if ( is_array( $result ) && ! empty( $result['message'] ) ) {
			if ( 'test' === $tab && 'success' === ( $result['type'] ?? '' ) ) {
				$result_toast_message = (string) $result['message'];
			} else {
				$result_notice_html = Notice::render( [
					'variant' => 'error' === ( $result['type'] ?? '' ) ? Notice::ERROR : Notice::SUCCESS,
					'message' => (string) $result['message'],
				] );
			}
		}
		$conflict_notice    = '';
		if ( ! empty( $conflicts ) ) {
			$conflict_notice = Notice::render( [
				'variant' => Notice::WARNING,
				'title'   => __( 'Transport conflict detected', 'core-blueprint' ),
				'message' => sprintf(
					/* translators: %s: active conflicting mail transport plugin names */
					__( 'Active mail transport: %s. You can safely prepare Core Blueprint Mail now, but its transport hooks stay inactive until the other mail plugin is disabled.', 'core-blueprint' ),
					implode( ', ', $conflicts )
				),
			] );
		}

		ob_start();
		include 'settings' === $tab
			? CB_CORE_DIR . 'templates/mail-settings.php'
			: CB_CORE_DIR . 'templates/mail-test.php';
		$html = (string) ob_get_clean();

		echo TabNav::inject( $html, self::SLUG, $tab, $tabs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
