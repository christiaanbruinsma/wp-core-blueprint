<?php
declare(strict_types=1);

use CB\Core\Automation\ActionInvoker;
use CB\Core\Automation\ActionRegistry;
use CB\Core\Automation\InvocationContext;
use CB\Core\Automation\StateInvoker;
use CB\Core\Automation\StateRegistry;
use CB\Core\Automation\TriggerRegistry;
use CB\Core\ExtensionRegistry;

final class CB_Base_Automation_Invocation_Contract_Test extends WP_UnitTestCase {

	private const PROVIDER = 'acme-invocation-fixture';
	private const PLUGIN_FILE = self::PROVIDER . '/' . self::PROVIDER . '.php';

	private int $action_calls = 0;
	private int $state_calls = 0;
	private ?InvocationContext $action_context = null;
	private ?InvocationContext $state_context = null;

	public function set_up(): void {
		parent::set_up();

		$this->remove_fixture();
		$this->create_fixture();
		wp_clean_plugins_cache( true );

		ExtensionRegistry::reset();
		TriggerRegistry::_reset_for_testing();
		$this->action_calls = 0;
		$this->state_calls = 0;
		$this->action_context = null;
		$this->state_context = null;

		add_action( 'cb_core_register_extensions', [ $this, 'register_fixture_extension' ] );
		add_action( 'cb_core_register_automation_capabilities', [ $this, 'register_fixture_capabilities' ] );
	}

	public function tear_down(): void {
		remove_action( 'cb_core_register_extensions', [ $this, 'register_fixture_extension' ] );
		remove_action( 'cb_core_register_automation_capabilities', [ $this, 'register_fixture_capabilities' ] );
		wp_set_current_user( 0 );
		ExtensionRegistry::reset();
		TriggerRegistry::_reset_for_testing();
		$this->remove_fixture();
		wp_clean_plugins_cache( true );

		parent::tear_down();
	}

	public function test_af3_public_invocation_contracts_exist(): void {
		self::assertTrue( class_exists( InvocationContext::class ) );
		self::assertTrue( class_exists( ActionInvoker::class ) );
		self::assertTrue( class_exists( StateInvoker::class ) );
		self::assertTrue( method_exists( ActionInvoker::class, 'invoke' ) );
		self::assertTrue( method_exists( StateInvoker::class, 'resolve' ) );
	}

	public function test_af3_registered_action_and_state_succeed_for_valid_explicit_principal(): void {
		$principal_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( 0 );
		$context = $this->context( $principal_id );

		$action = ActionInvoker::invoke(
			self::PROVIDER,
			'work.project.create',
			'1',
			[ 'source_id' => 42 ],
			$context
		);
		$state = StateInvoker::resolve(
			self::PROVIDER,
			'invoice.current',
			'1',
			[ 'invoice_id' => 42 ],
			$context
		);

		self::assertSame( [ 'project_id' => 42 ], $action );
		self::assertSame( [ 'status' => 'paid' ], $state );
		self::assertSame( 1, $this->action_calls );
		self::assertSame( 1, $this->state_calls );
	}

	public function test_af3_unknown_capability_and_schema_mismatch_fail_closed(): void {
		$principal_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$context = $this->context( $principal_id );

		$unknown_action = ActionInvoker::invoke( self::PROVIDER, 'work.unknown', '1', [], $context );
		$unknown_state = StateInvoker::resolve( self::PROVIDER, 'invoice.unknown', '1', [], $context );
		$action_version = ActionInvoker::invoke( self::PROVIDER, 'work.project.create', '2', [ 'source_id' => 1 ], $context );
		$state_version = StateInvoker::resolve( self::PROVIDER, 'invoice.current', '2', [ 'invoice_id' => 1 ], $context );

		self::assertWPError( $unknown_action );
		self::assertSame( 'cb_core_automation_unknown_action', $unknown_action->get_error_code() );
		self::assertWPError( $unknown_state );
		self::assertSame( 'cb_core_automation_unknown_state', $unknown_state->get_error_code() );
		self::assertWPError( $action_version );
		self::assertSame( 'cb_core_automation_schema_mismatch', $action_version->get_error_code() );
		self::assertWPError( $state_version );
		self::assertSame( 'cb_core_automation_schema_mismatch', $state_version->get_error_code() );
	}

	public function test_af3_invalid_input_fails_before_provider_callback(): void {
		$principal_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$context = $this->context( $principal_id );

		$action = ActionInvoker::invoke( self::PROVIDER, 'work.project.create', '1', [ 'source_id' => '42' ], $context );
		$state = StateInvoker::resolve( self::PROVIDER, 'invoice.current', '1', [ 'invoice_id' => '42' ], $context );

		self::assertWPError( $action );
		self::assertSame( 'cb_core_automation_invalid_input', $action->get_error_code() );
		self::assertWPError( $state );
		self::assertSame( 'cb_core_automation_invalid_input', $state->get_error_code() );
		self::assertSame( 0, $this->action_calls );
		self::assertSame( 0, $this->state_calls );
	}

	public function test_af3_missing_deleted_and_unprivileged_principals_fail_closed(): void {
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$deleted_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_delete_user( $deleted_id );

		$missing = ActionInvoker::invoke(
			self::PROVIDER,
			'work.project.create',
			'1',
			[ 'source_id' => 1 ],
			$this->context( 0 )
		);
		$deleted = ActionInvoker::invoke(
			self::PROVIDER,
			'work.project.create',
			'1',
			[ 'source_id' => 1 ],
			$this->context( $deleted_id )
		);
		$denied = ActionInvoker::invoke(
			self::PROVIDER,
			'work.project.create',
			'1',
			[ 'source_id' => 1 ],
			$this->context( $subscriber_id )
		);
		$state_denied = StateInvoker::resolve(
			self::PROVIDER,
			'invoice.current',
			'1',
			[ 'invoice_id' => 1 ],
			$this->context( $subscriber_id )
		);

		self::assertWPError( $missing );
		self::assertSame( 'cb_core_automation_principal_missing', $missing->get_error_code() );
		self::assertWPError( $deleted );
		self::assertSame( 'cb_core_automation_principal_invalid', $deleted->get_error_code() );
		self::assertWPError( $denied );
		self::assertSame( 'cb_core_automation_permission_denied', $denied->get_error_code() );
		self::assertWPError( $state_denied );
		self::assertSame( 'cb_core_automation_permission_denied', $state_denied->get_error_code() );
		self::assertSame( 0, $this->action_calls );
		self::assertSame( 0, $this->state_calls );
	}

	public function test_af3_explicit_principal_not_ambient_current_user_controls_permission(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$subscriber_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );

		wp_set_current_user( $admin_id );
		$denied = ActionInvoker::invoke(
			self::PROVIDER,
			'work.project.create',
			'1',
			[ 'source_id' => 1 ],
			$this->context( $subscriber_id )
		);

		wp_set_current_user( 0 );
		$allowed = ActionInvoker::invoke(
			self::PROVIDER,
			'work.project.create',
			'1',
			[ 'source_id' => 2 ],
			$this->context( $admin_id )
		);

		self::assertWPError( $denied );
		self::assertSame( 'cb_core_automation_permission_denied', $denied->get_error_code() );
		self::assertSame( [ 'project_id' => 2 ], $allowed );
	}

	public function test_af3_lost_capability_is_rechecked_on_each_invocation(): void {
		$principal_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$context = $this->context( $principal_id );

		$first = ActionInvoker::invoke( self::PROVIDER, 'work.project.create', '1', [ 'source_id' => 1 ], $context );
		$user = get_userdata( $principal_id );
		self::assertInstanceOf( WP_User::class, $user );
		$user->set_role( 'subscriber' );
		$second = ActionInvoker::invoke( self::PROVIDER, 'work.project.create', '1', [ 'source_id' => 2 ], $context );

		self::assertSame( [ 'project_id' => 1 ], $first );
		self::assertWPError( $second );
		self::assertSame( 'cb_core_automation_permission_denied', $second->get_error_code() );
	}

	public function test_af3_provider_wp_errors_are_safely_normalized(): void {
		$principal_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$context = $this->context( $principal_id );

		$action = ActionInvoker::invoke( self::PROVIDER, 'work.fail', '1', [], $context );
		$state = StateInvoker::resolve( self::PROVIDER, 'invoice.fail', '1', [], $context );

		self::assertWPError( $action );
		self::assertSame( 'cb_core_automation_execution_failed', $action->get_error_code() );
		self::assertSame( 'acme_domain_secret_failure', $action->get_error_data()['provider_error_code'] ?? null );
		self::assertStringNotContainsString( 'TOP SECRET', $action->get_error_message() );
		self::assertWPError( $state );
		self::assertSame( 'cb_core_automation_execution_failed', $state->get_error_code() );
		self::assertSame( 'acme_state_secret_failure', $state->get_error_data()['provider_error_code'] ?? null );
		self::assertStringNotContainsString( 'TOP SECRET', $state->get_error_message() );
	}

	public function test_af3_throwables_are_normalized_without_exception_details(): void {
		$principal_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$context = $this->context( $principal_id );

		$action = ActionInvoker::invoke( self::PROVIDER, 'work.throw', '1', [], $context );
		$state = StateInvoker::resolve( self::PROVIDER, 'invoice.throw', '1', [], $context );

		self::assertWPError( $action );
		self::assertSame( 'cb_core_automation_execution_failed', $action->get_error_code() );
		self::assertStringNotContainsString( 'TOP SECRET', $action->get_error_message() );
		self::assertWPError( $state );
		self::assertSame( 'cb_core_automation_execution_failed', $state->get_error_code() );
		self::assertStringNotContainsString( 'TOP SECRET', $state->get_error_message() );
	}

	public function test_af3_invalid_provider_output_fails_closed(): void {
		$principal_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$context = $this->context( $principal_id );

		$action = ActionInvoker::invoke( self::PROVIDER, 'work.bad_output', '1', [], $context );
		$state = StateInvoker::resolve( self::PROVIDER, 'invoice.bad_output', '1', [], $context );

		self::assertWPError( $action );
		self::assertSame( 'cb_core_automation_invalid_output', $action->get_error_code() );
		self::assertWPError( $state );
		self::assertSame( 'cb_core_automation_invalid_output', $state->get_error_code() );
	}

	public function test_af3_context_reaches_context_aware_callbacks_and_discovery_remains_private(): void {
		$principal_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$context = $this->context( $principal_id );

		$action = ActionInvoker::invoke( self::PROVIDER, 'work.context', '1', [], $context );
		$state = StateInvoker::resolve( self::PROVIDER, 'invoice.context', '1', [], $context );
		$action_definition = ActionRegistry::get( self::PROVIDER, 'work.context' );
		$state_definition = StateRegistry::get( self::PROVIDER, 'invoice.context' );

		self::assertSame( [ 'ok' => true ], $action );
		self::assertSame( [ 'ok' => true ], $state );
		self::assertSame( $context, $this->action_context );
		self::assertSame( $context, $this->state_context );
		self::assertSame( 'corr-123', $this->action_context?->correlation_id() );
		self::assertSame( 'run-456', $this->action_context?->run_id() );
		self::assertSame( 'step-789', $this->action_context?->step_id() );
		self::assertSame( 2, $this->action_context?->attempt() );
		self::assertSame( 'workflow-12', $this->action_context?->workflow_id() );
		self::assertSame( '7', $this->action_context?->workflow_revision() );
		self::assertIsArray( $action_definition );
		self::assertArrayNotHasKey( 'executor', $action_definition );
		self::assertIsArray( $state_definition );
		self::assertArrayNotHasKey( 'resolver', $state_definition );
	}

	public function register_fixture_extension(): void {
		ExtensionRegistry::register( [
			'id'            => self::PROVIDER,
			'plugin_file'   => self::PLUGIN_FILE,
			'requires_api'  => '1.0',
			'requires_base' => '1.0.0-rc1',
			'menu_url'      => '',
			'status_id'     => '',
		] );
	}

	public function register_fixture_capabilities(): void {
		ActionRegistry::register( [
			'provider'            => self::PROVIDER,
			'id'                  => 'work.project.create',
			'label'               => 'Create project',
			'description'         => 'Legacy one-argument executor fixture.',
			'schema_version'      => '1',
			'input_schema'        => [
				'source_id' => [ 'type' => 'integer', 'required' => true ],
			],
			'output_schema'       => [
				'project_id' => [ 'type' => 'integer', 'required' => true ],
			],
			'required_capability' => 'manage_options',
			'executor'            => function ( array $input ): array {
				++$this->action_calls;
				return [ 'project_id' => $input['source_id'] ];
			},
		] );

		StateRegistry::register( [
			'provider'            => self::PROVIDER,
			'id'                  => 'invoice.current',
			'label'               => 'Current invoice',
			'description'         => 'Legacy one-argument resolver fixture.',
			'schema_version'      => '1',
			'input_schema'        => [
				'invoice_id' => [ 'type' => 'integer', 'required' => true ],
			],
			'output_schema'       => [
				'status' => [ 'type' => 'string', 'required' => true ],
			],
			'required_capability' => 'manage_options',
			'resolver'            => function ( array $input ): array {
				++$this->state_calls;
				return [ 'status' => 'paid' ];
			},
		] );

		ActionRegistry::register( $this->simple_action_definition(
			'work.context',
			function ( array $input, InvocationContext $context ): array {
				$this->action_context = $context;
				return [ 'ok' => true ];
			}
		) );
		StateRegistry::register( $this->simple_state_definition(
			'invoice.context',
			function ( array $input, InvocationContext $context ): array {
				$this->state_context = $context;
				return [ 'ok' => true ];
			}
		) );

		ActionRegistry::register( $this->simple_action_definition(
			'work.fail',
			static fn (): WP_Error => new WP_Error(
				'acme_domain_secret_failure',
				'TOP SECRET provider detail',
				[ 'secret' => 'must-not-propagate' ]
			)
		) );
		StateRegistry::register( $this->simple_state_definition(
			'invoice.fail',
			static fn (): WP_Error => new WP_Error(
				'acme_state_secret_failure',
				'TOP SECRET provider detail',
				[ 'secret' => 'must-not-propagate' ]
			)
		) );

		ActionRegistry::register( $this->simple_action_definition(
			'work.throw',
			static function (): array {
				throw new RuntimeException( 'TOP SECRET throwable detail' );
			}
		) );
		StateRegistry::register( $this->simple_state_definition(
			'invoice.throw',
			static function (): array {
				throw new RuntimeException( 'TOP SECRET throwable detail' );
			}
		) );

		ActionRegistry::register( $this->simple_action_definition(
			'work.bad_output',
			static fn (): array => [ 'ok' => 'not-a-boolean' ]
		) );
		StateRegistry::register( $this->simple_state_definition(
			'invoice.bad_output',
			static fn (): array => [ 'ok' => 'not-a-boolean' ]
		) );
	}

	/** @return array<string,mixed> */
	private function simple_action_definition( string $id, callable $executor ): array {
		return [
			'provider'            => self::PROVIDER,
			'id'                  => $id,
			'label'               => $id,
			'description'         => '',
			'schema_version'      => '1',
			'input_schema'        => [],
			'output_schema'       => [
				'ok' => [ 'type' => 'boolean', 'required' => true ],
			],
			'required_capability' => 'manage_options',
			'executor'            => $executor,
		];
	}

	/** @return array<string,mixed> */
	private function simple_state_definition( string $id, callable $resolver ): array {
		return [
			'provider'            => self::PROVIDER,
			'id'                  => $id,
			'label'               => $id,
			'description'         => '',
			'schema_version'      => '1',
			'input_schema'        => [],
			'output_schema'       => [
				'ok' => [ 'type' => 'boolean', 'required' => true ],
			],
			'required_capability' => 'manage_options',
			'resolver'            => $resolver,
		];
	}

	private function context( int $principal_user_id ): InvocationContext {
		return new InvocationContext(
			$principal_user_id,
			'automations',
			'corr-123',
			'run-456',
			'step-789',
			2,
			'workflow-12',
			'7'
		);
	}

	private function create_fixture(): void {
		$directory = WP_PLUGIN_DIR . '/' . self::PROVIDER;
		self::assertTrue( wp_mkdir_p( $directory ), 'Could not create Automation Invocation fixture directory.' );

		$plugin = <<<'PHP'
<?php
/**
 * Plugin Name: Acme Automation Invocation Fixture
 * Author: Acme Labs
 * Version: 1.0.0
 */
defined( 'ABSPATH' ) || exit;
PHP;

		self::assertNotFalse(
			file_put_contents( $directory . '/' . self::PROVIDER . '.php', $plugin ),
			'Could not write Automation Invocation fixture plugin.'
		);
	}

	private function remove_fixture(): void {
		$file = WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE;
		$directory = dirname( $file );
		if ( is_file( $file ) ) {
			unlink( $file );
		}
		if ( is_dir( $directory ) ) {
			rmdir( $directory );
		}
	}
}
