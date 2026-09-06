<?php
declare(strict_types=1);

use CB\Core\Admin\Admin;
use CB\Core\Admin\Pages\Logs\TabRegistry;
use CB\Core\Admin\Pages\Logs\Tabs\AIActivityTab;
use CB\Core\AIGovernance\AbilityObserver;
use CB\Core\AIGovernance\Activity;
use CB\Core\AIGovernance\Bootstrap as AIGovernanceBootstrap;
use CB\Core\AIGovernance\Exporter;
use CB\Core\AIGovernance\Privacy;
use CB\Core\AIGovernance\Repository;
use CB\Core\AIGovernance\Settings;
use CB\Core\Governance\RetentionStoreRegistry;

final class CB_Base_AI_Governance_Contract_Test extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		AbilityObserver::reset_for_tests();
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . Repository::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		delete_option( Settings::RETENTION_OPTION );
	}

	public function tear_down(): void {
		AbilityObserver::reset_for_tests();
		parent::tear_down();
	}

	public function test_ai_activity_schema_is_owned_independently_from_global_base_db_marker(): void {
		$this->assertSame( '1.0', CB_CORE_DB_VERSION );
		$this->assertSame( '1.0', Repository::DB_VERSION );
		$this->assertSame( 'cb_core_ai_activity_db_version', Repository::SCHEMA_OPTION );
		$this->assertSame( Repository::DB_VERSION, get_option( Repository::SCHEMA_OPTION ) );
		$this->assertSame( Repository::table(), $GLOBALS['wpdb']->prefix . 'cb_core_ai_activity' );
	}

	public function test_ai_activity_is_registered_as_logs_tab_without_standalone_page(): void {
		AIGovernanceBootstrap::register_log_tab();
		$tab = TabRegistry::get( AIActivityTab::SLUG );

		$this->assertIsArray( $tab );
		$this->assertSame( 'AI Activity', $tab['label'] );
		$this->assertSame( 50, $tab['priority'] );
		$this->assertSame( [ AIActivityTab::class, 'render' ], $tab['renderer'] );
		$this->assertFalse( class_exists( '\CB\Core\Admin\Pages\AIGovernance' ) );
	}

	public function test_ai_retention_store_points_to_ai_activity_logs_tab(): void {
		$existing = RetentionStoreRegistry::all();
		RetentionStoreRegistry::reset_for_tests();

		try {
			Repository::register_retention_store();
			$stores = RetentionStoreRegistry::snapshot();
			$this->assertArrayHasKey( 'core-ai-activity', $stores );
			$url = $stores['core-ai-activity']['settings_url'];
			$this->assertStringContainsString( 'page=' . Admin::LOGS_SLUG, $url );
			$this->assertStringContainsString( 'tab=' . AIActivityTab::SLUG, $url );
			$this->assertStringContainsString( '#retention', $url );
			$this->assertStringNotContainsString( 'core-blueprint-ai-governance', $url );
		} finally {
			RetentionStoreRegistry::reset_for_tests();
			foreach ( $existing as $definition ) {
				RetentionStoreRegistry::register( $definition );
			}
		}
	}

	public function test_public_activity_facade_is_metadata_first_and_resolves_actor_itself(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator', 'user_login' => 'ai-governance-actor' ] );
		wp_set_current_user( $user_id );

		$id = Activity::record( [
			'operation'    => 'core-blueprint-test/reported-operation',
			'outcome'      => 'succeeded',
			'transport'    => 'reported',
			'source_id'    => 'test-adapter',
			'source_label' => 'Test adapter',
			'target_type'  => 'post',
			'target_id'    => '42',
			'target_label' => 'Fixture target',
			'evidence'     => [
				'approval_id' => 'approval-17',
				'input_type'  => 'object',
			],
			'context'      => [
				'prompt'        => 'do not persist this prompt',
				'api_key'       => 'do not persist this key',
				'customer_name' => 'Allowed bounded metadata',
			],
		] );

		$this->assertIsString( $id );
		$row = Repository::get( $id );
		$this->assertNotNull( $row );
		$this->assertSame( $user_id, (int) $row->actor_user_id );
		$this->assertSame( 'ai-governance-actor', $row->actor_user_login );
		$this->assertSame( 'test-adapter', $row->source_id );
		$this->assertSame( 'reported', $row->capture_state );
		$this->assertSame( '[redacted:metadata-only]', $row->context_decoded['prompt'] );
		$this->assertSame( '[redacted]', $row->context_decoded['api_key'] );
		$this->assertSame( 'Allowed bounded metadata', $row->context_decoded['customer_name'] );
		$this->assertStringNotContainsString( 'do not persist this prompt', (string) $row->context );
		$this->assertStringNotContainsString( 'do not persist this key', (string) $row->context );
	}

	public function test_privacy_summary_never_copies_scalar_payload_values(): void {
		$this->assertSame( [ 'type' => 'string', 'bytes' => 12 ], Privacy::summarize( 'super-secret' ) );
		$this->assertSame( [ 'type' => 'array', 'item_count' => 2 ], Privacy::summarize( [ 'prompt' => 'secret', 'id' => 7 ] ) );
		$error_summary = Privacy::summarize( new WP_Error( 'fixture_secret_error', 'Sensitive error message.' ) );
		$this->assertSame( 'wp_error', $error_summary['type'] );
		$this->assertSame( [ 'fixture_secret_error' ], $error_summary['error_codes'] );
		$this->assertStringNotContainsString( 'Sensitive error message', wp_json_encode( $error_summary ) );
	}

	public function test_successful_real_ability_execution_is_correlated_without_raw_input_or_result(): void {
		$ability = wp_get_ability( 'core-blueprint-test/success' );
		$this->assertInstanceOf( WP_Ability::class, $ability );

		$result = $ability->execute( [ 'secret' => 'never-store-this-secret' ] );
		$this->assertSame( [ 'ok' => true ], $result );

		$record = $this->single_operation_record( 'core-blueprint-test/success' );
		$this->assertSame( 'succeeded', $record->outcome );
		$this->assertSame( 'completed', $record->capture_state );
		$this->assertSame( 'php', $record->transport );
		$this->assertNull( $record->source_id );
		$this->assertSame( 'array', $record->evidence_decoded['arguments_shape']['type'] ?? $record->evidence_decoded['normalized_arguments_shape']['type'] ?? null );
		$this->assertSame( 'array', $record->evidence_decoded['result_shape']['type'] ?? null );
		$this->assertStringNotContainsString( 'never-store-this-secret', (string) $record->evidence );
		$this->assertGreaterThanOrEqual( 0, (int) $record->duration_ms );
	}

	public function test_denied_invocation_visibility_matches_wordpress_evidence_surface(): void {
		$ability = wp_get_ability( 'core-blueprint-test/denied' );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		$result = $ability->execute( [ 'secret' => 'denied-secret' ] );
		$this->assertWPError( $result );

		$records = $this->operation_records( 'core-blueprint-test/denied' );
		if ( version_compare( get_bloginfo( 'version' ), '7.1', '>=' ) ) {
			$this->assertCount( 1, $records );
			$this->assertSame( 'denied', $records[0]->outcome );
			$this->assertSame( 'permission-checked', $records[0]->capture_state );
			$this->assertStringNotContainsString( 'denied-secret', (string) $records[0]->evidence );
			return;
		}
		$this->assertCount( 0, $records, 'WP 7.0 cannot globally observe a permission denial through the 6.9 actions.' );
	}

	public function test_invalid_invocation_visibility_matches_wordpress_evidence_surface(): void {
		$ability = wp_get_ability( 'core-blueprint-test/success' );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		$result = $ability->execute( [ 'secret' => [ 'invalid-shape' ] ] );
		$this->assertWPError( $result );

		$records = $this->operation_records( 'core-blueprint-test/success' );
		if ( version_compare( get_bloginfo( 'version' ), '7.1', '>=' ) ) {
			$this->assertCount( 1, $records );
			$this->assertSame( 'invalid', $records[0]->outcome );
			$this->assertContains( $records[0]->capture_state, [ 'normalized', 'input-validation' ] );
			return;
		}
		$this->assertCount( 0, $records, 'WP 7.0 cannot globally observe an input-validation failure through the 6.9 actions.' );
	}

	public function test_callback_failure_is_known_on_71_and_remains_open_evidence_on_70(): void {
		$ability = wp_get_ability( 'core-blueprint-test/failed' );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		$result = $ability->execute( [ 'secret' => 'failure-secret' ] );
		$this->assertWPError( $result );
		AbilityObserver::flush_open();

		$record = $this->single_operation_record( 'core-blueprint-test/failed' );
		if ( version_compare( get_bloginfo( 'version' ), '7.1', '>=' ) ) {
			$this->assertSame( 'failed', $record->outcome );
			$this->assertSame( 'callback-result', $record->capture_state );
			$this->assertSame( 'cb_ai_fixture_failed', $record->error_code );
		} else {
			$this->assertSame( 'unknown', $record->outcome );
			$this->assertSame( 'authorized', $record->capture_state );
			$this->assertNull( $record->completed_at );
		}
		$this->assertStringNotContainsString( 'failure-secret', (string) $record->evidence );
	}

	public function test_unknown_source_stays_unknown_for_direct_php_ability_execution(): void {
		$ability = wp_get_ability( 'core-blueprint-test/success' );
		$ability->execute( [ 'secret' => 'source-check' ] );
		$record = $this->single_operation_record( 'core-blueprint-test/success' );
		$this->assertSame( 'php', $record->transport );
		$this->assertNull( $record->source_id );
		$this->assertNull( $record->source_label );
	}

	public function test_query_filters_and_retention_prune_the_dedicated_store(): void {
		$id_a = Activity::record( [ 'operation' => 'fixture/a', 'outcome' => 'succeeded', 'source_id' => 'adapter-a' ] );
		$id_b = Activity::record( [ 'operation' => 'fixture/b', 'outcome' => 'failed', 'source_id' => 'adapter-b' ] );
		$this->assertIsString( $id_a );
		$this->assertIsString( $id_b );

		$filtered = Repository::query( [ 'source' => 'adapter-b', 'outcome' => 'failed' ] );
		$this->assertSame( 1, $filtered['total'] );
		$this->assertSame( 'fixture/b', $filtered['rows'][0]->operation );

		global $wpdb;
		$wpdb->update( Repository::table(), [ 'created_at' => '2020-01-01 00:00:00' ], [ 'activity_id' => $id_a ], [ '%s' ], [ '%s' ] );
		$this->assertSame( 1, Repository::prune( 30 ) );
		$this->assertNull( Repository::get( $id_a ) );
		$this->assertNotNull( Repository::get( $id_b ) );
	}

	public function test_csv_and_json_exports_preserve_structured_evidence(): void {
		$id = Activity::record( [
			'operation' => 'fixture/export',
			'outcome'   => 'succeeded',
			'evidence'  => [ 'approval_id' => 'approval-55' ],
		] );
		$this->assertIsString( $id );

		$json_handle = fopen( 'php://temp', 'w+b' );
		$this->assertIsResource( $json_handle );
		$this->assertSame( 1, Exporter::write( 'json', $json_handle, [ 'operation' => 'fixture/export' ] ) );
		rewind( $json_handle );
		$json = stream_get_contents( $json_handle );
		fclose( $json_handle );
		$decoded = json_decode( (string) $json, true );
		$this->assertSame( 'ai_activity', $decoded['export']['type'] ?? null );
		$this->assertSame( 'metadata-first', $decoded['export']['evidence_model'] ?? null );
		$this->assertSame( 'approval-55', $decoded['events'][0]['evidence']['approval_id'] ?? null );

		$csv_handle = fopen( 'php://temp', 'w+b' );
		$this->assertIsResource( $csv_handle );
		$this->assertSame( 1, Exporter::write( 'csv', $csv_handle, [ 'operation' => 'fixture/export' ] ) );
		rewind( $csv_handle );
		$csv = stream_get_contents( $csv_handle );
		fclose( $csv_handle );
		$this->assertStringContainsString( 'Activity ID', (string) $csv );
		$this->assertStringContainsString( 'approval-55', (string) $csv );
	}

	/** @return array<int,object> */
	private function operation_records( string $operation ): array {
		$result = Repository::query( [ 'operation' => $operation, 'per_page' => 50 ] );
		return array_values( array_filter(
			$result['rows'],
			static fn( object $row ): bool => $row->operation === $operation
		) );
	}

	private function single_operation_record( string $operation ): object {
		$records = $this->operation_records( $operation );
		$this->assertCount( 1, $records );
		return $records[0];
	}
}
