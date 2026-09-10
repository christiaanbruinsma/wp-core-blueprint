<?php
declare(strict_types=1);
/**
 * Thin Automation Foundation trigger emission boundary.
 *
 * Base validates the registered trigger contract and dispatches one immutable
 * in-request event. It does not persist, queue, retry, schedule or orchestrate
 * the event; those responsibilities belong to optional automation consumers.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Automation;

defined( 'ABSPATH' ) || exit;

final class Emitter {

	private const EVENT_ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D';

	/**
	 * Emit one validated registered trigger.
	 *
	 * Producers SHOULD pass a stable domain event id when one exists so an
	 * orchestration runtime can deduplicate repeated delivery. When omitted,
	 * Base generates a request-local UUID for the emission.
	 *
	 * @param array<string,mixed> $payload
	 * @return TriggerEvent|\WP_Error
	 */
	public static function emit( string $provider, string $trigger_id, array $payload, ?string $event_id = null ): TriggerEvent|\WP_Error {
		$definition = TriggerRegistry::get( $provider, $trigger_id );
		if ( null === $definition ) {
			return new \WP_Error(
				'cb_core_automation_unknown_trigger',
				__( 'Unknown automation trigger.', 'core-blueprint' )
			);
		}

		$schema = $definition['payload_schema'] ?? null;
		if ( ! is_array( $schema ) || ! Schema::validate( $payload, $schema ) ) {
			return new \WP_Error(
				'cb_core_automation_invalid_payload',
				__( 'Automation trigger payload does not match its registered schema.', 'core-blueprint' )
			);
		}

		if ( null === $event_id || '' === trim( $event_id ) ) {
			$event_id = wp_generate_uuid4();
		} else {
			$event_id = trim( $event_id );
			if ( 1 !== preg_match( self::EVENT_ID_PATTERN, $event_id ) ) {
				return new \WP_Error(
					'cb_core_automation_invalid_event_id',
					__( 'Automation event id is invalid.', 'core-blueprint' )
				);
			}
		}

		$event = new TriggerEvent(
			$event_id,
			(string) $definition['provider'],
			(string) $definition['id'],
			(string) $definition['schema_version'],
			$payload,
			gmdate( 'c' )
		);

		/**
		 * Fires after a registered Automation Foundation trigger is validated.
		 *
		 * Consumers must treat this as an in-request delivery boundary. Base does
		 * not guarantee persistence or retries for listeners.
		 *
		 * @param TriggerEvent $event
		 */
		do_action( 'cb_core_automation_trigger_emitted', $event );

		return $event;
	}
}
