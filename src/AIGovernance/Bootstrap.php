<?php
declare(strict_types=1);
/**
 * AI Governance subsystem bootstrap.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */
namespace CB\Core\AIGovernance;

use CB\Core\Admin\PageRegistry;
use CB\Core\Admin\Pages\AIGovernance as AIGovernancePage;
use CB\Core\AIGovernance\Admin\Actions;
use CB\Core\Governance\EventRegistry;
use CB\Core\RequestContext;

defined( 'ABSPATH' ) || exit;

final class Bootstrap {
	public static function boot(): void {
		static $booted = false;
		if ( $booted ) {
			return;
		}
		$booted = true;

		add_action( 'plugins_loaded', [ Repository::class, 'register_schema' ], 4 );
		add_action( 'init', [ Repository::class, 'register_retention_store' ], 2 );
		add_action( 'init', [ __CLASS__, 'register_event_metadata' ], 5 );
		add_action( 'cb_core_register_pages', [ __CLASS__, 'register_page' ] );

		AbilityObserver::boot();

		if ( RequestContext::is_admin_post() ) {
			Actions::boot();
		}
	}

	public static function register_page(): void {
		PageRegistry::register_base(
			new AIGovernancePage(),
			[
				'components' => [ 'empty-state', 'form-controls', 'kv-table', 'badges' ],
			]
		);
	}

	public static function register_event_metadata(): void {
		EventRegistry::register_core( [
			'id'                 => 'ai.activity.exported',
			'label'              => __( 'AI Governance: activity exported', 'core-blueprint' ),
			'retention_category' => 'security',
		] );
		EventRegistry::register_core( [
			'id'                 => 'ai.retention.updated',
			'label'              => __( 'AI Governance: retention updated', 'core-blueprint' ),
			'retention_category' => 'settings',
		] );
	}
}
