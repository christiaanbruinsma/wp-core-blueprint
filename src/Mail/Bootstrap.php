<?php
declare(strict_types=1);
/**
 * Core Blueprint Mail subsystem bootstrap.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Mail;

use CB\Core\Admin\PageRegistry;
use CB\Core\Log\AuditLog;
use CB\Core\RequestContext;
use CB\Core\Mail\Admin\Actions;
use CB\Core\Mail\Admin\LogsTab;
use CB\Core\Mail\Admin\Page;
use CB\Core\Mail\Log\Repository;

defined( 'ABSPATH' ) || exit;

final class Bootstrap {

	public static function boot(): void {
		// Register the dedicated mail log before the central DB migration sweep.
		add_action( 'plugins_loaded', [ Repository::class, 'register_schema' ], 4 );
		add_action( 'init', [ Repository::class, 'register_retention_store' ], 2 );

		// Runtime activates only after every plugin is loaded, so conflict
		// detection sees the complete active-plugin set.
		add_action( 'plugins_loaded', [ Runtime::class, 'boot' ], 30 );

		add_action( 'cb_core_register_pages', static function (): void {
			if ( ! State::is_enabled() ) {
				return;
			}
			PageRegistry::register_base( new Page() );
		} );
		add_action( 'cb_core_logs_register_tabs', [ LogsTab::class, 'register' ] );

		add_action( 'init', [ __CLASS__, 'register_i18n_filters' ], 1 );
		add_action( 'init', [ __CLASS__, 'register_sender_identities' ], 5 );
		// Raw system-event metadata is translation-free and remains available early.
		add_filter( 'cb_core_system_log_event_types', [ __CLASS__, 'register_system_events' ] );

		if ( RequestContext::is_admin_post() ) {
			Actions::boot();
		}
		if ( RequestContext::is_admin_screen() ) {
			add_action( 'admin_notices', [ __CLASS__, 'conflict_notice' ] );
		}
	}

	/** Register translation-bearing metadata filters after the textdomain is loaded. */
	public static function register_i18n_filters(): void {
		\CB\Core\Governance\EventRegistry::register_core_many( self::register_event_labels( [] ) );
	}

	/**
	 * Public registration lifecycle for extension-owned sender identity slots.
	 */
	public static function register_sender_identities(): void {
		do_action( 'cb_core_register_mail_sender_identities' );
	}

	public static function register_event_labels( array $labels ): array {
		$labels['mail.subsystem.enabled']  = __( 'Mail: subsystem enabled', 'core-blueprint' );
		$labels['mail.subsystem.disabled'] = __( 'Mail: subsystem disabled', 'core-blueprint' );
		$labels['mail.settings.updated']   = __( 'Mail: settings updated', 'core-blueprint' );
		$labels['mail.test.sent']          = __( 'Mail: test email sent', 'core-blueprint' );
		$labels['mail.log.cleared']        = __( 'Mail: log cleared', 'core-blueprint' );
		return $labels;
	}

	public static function register_system_events( array $types ): array {
		$types['system.mail_failed'] = [
			'description' => 'Mail delivery failed via {provider}: {error_code}',
			'category'    => 'other',
			'severity'    => 'warning',
		];
		return $types;
	}

	public static function conflict_notice(): void {
		if ( ! State::is_enabled() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$conflicts = ConflictDetector::active();
		if ( empty( $conflicts ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__( 'Core Blueprint Mail is configured but inactive.', 'core-blueprint' ),
			esc_html( sprintf(
				/* translators: %s: comma-separated list of conflicting mail plugins */
				__( 'Another mail transport is active: %s. Core Blueprint will not register transport hooks until the conflict is removed.', 'core-blueprint' ),
				implode( ', ', $conflicts )
			) ),
			esc_url( admin_url( 'admin.php?page=' . Page::SLUG ) ),
			esc_html__( 'Review Mail settings', 'core-blueprint' )
		);
	}
}
