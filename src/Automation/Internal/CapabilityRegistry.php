<?php
declare(strict_types=1);
/**
 * Internal storage and collection lifecycle for Automation Foundation.
 *
 * Public consumers use TriggerRegistry and ActionRegistry. This class keeps one
 * collection pass so a provider can register triggers and actions from a single
 * callback without duplicate lifecycle dispatch.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Automation\Internal;

use CB\Core\Automation\Schema;
use CB\Core\ExtensionRegistry;

defined( 'ABSPATH' ) || exit;

final class CapabilityRegistry {

	public const BASE_PROVIDER = 'core-blueprint';
	private const ID_PATTERN = '/^[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)+$/D';
	private const SCHEMA_VERSION_PATTERN = '/^[1-9][0-9]*$/D';

	/** @var array<string,array<string,mixed>> */
	private static array $triggers = [];
	/** @var array<string,array<string,mixed>> */
	private static array $actions = [];
	private static bool $collected = false;

	/**
	 * Whether all active plugin files have loaded and capability collection is safe.
	 *
	 * Automation discovery must never freeze during top-level plugin loading: a
	 * later plugin in the same request may not yet have attached its registration
	 * callbacks. `plugins_loaded` is the first safe cross-plugin collection gate.
	 */
	public static function is_ready(): bool {
		return self::$collected || did_action( 'plugins_loaded' ) > 0 || doing_action( 'plugins_loaded' );
	}

	/** Collect registered capabilities once all active plugins have loaded. */
	public static function collect(): bool {
		if ( self::$collected ) {
			return true;
		}
		if ( ! self::is_ready() ) {
			return false;
		}

		// Freeze before dispatch so recursive discovery cannot fire the public
		// registration lifecycle twice in one request.
		self::$collected = true;

		// Provider identity must exist before capabilities are accepted. At this
		// point every active plugin file has loaded, so collecting ExtensionRegistry
		// cannot starve a provider that would have registered later in load order.
		ExtensionRegistry::collect();
		do_action( 'cb_core_register_automation_capabilities' );
		return true;
	}

	/** @param array<string,mixed> $definition */
	public static function register_trigger( array $definition, bool $base_owned ): bool {
		if ( ! $base_owned && ! doing_action( 'cb_core_register_automation_capabilities' ) ) {
			self::diagnostic( 'Trigger registration refused outside cb_core_register_automation_capabilities.' );
			return false;
		}

		$normalized = self::normalize_trigger( $definition, $base_owned );
		if ( null === $normalized ) {
			return false;
		}

		$key = self::key( $normalized['provider'], $normalized['id'] );
		if ( isset( self::$triggers[ $key ] ) ) {
			self::diagnostic( sprintf( 'Duplicate automation trigger refused: %s.', $key ) );
			return false;
		}

		self::$triggers[ $key ] = $normalized;
		return true;
	}

	/** @param array<string,mixed> $definition */
	public static function register_action( array $definition, bool $base_owned ): bool {
		if ( ! $base_owned && ! doing_action( 'cb_core_register_automation_capabilities' ) ) {
			self::diagnostic( 'Action registration refused outside cb_core_register_automation_capabilities.' );
			return false;
		}

		$normalized = self::normalize_action( $definition, $base_owned );
		if ( null === $normalized ) {
			return false;
		}

		$key = self::key( $normalized['provider'], $normalized['id'] );
		if ( isset( self::$actions[ $key ] ) ) {
			self::diagnostic( sprintf( 'Duplicate automation action refused: %s.', $key ) );
			return false;
		}

		self::$actions[ $key ] = $normalized;
		return true;
	}

	/** @return array<string,array<string,mixed>> */
	public static function triggers(): array {
		self::collect();
		return self::$triggers;
	}

	/** @return array<string,array<string,mixed>> */
	public static function actions(): array {
		self::collect();
		$out = [];
		foreach ( self::$actions as $key => $definition ) {
			$public = $definition;
			unset( $public['executor'] );
			$out[ $key ] = $public;
		}
		return $out;
	}

	/** @return array<string,mixed>|null */
	public static function trigger( string $provider, string $id ): ?array {
		self::collect();
		$key = self::key_if_valid( $provider, $id );
		return null !== $key ? ( self::$triggers[ $key ] ?? null ) : null;
	}

	/** @return array<string,mixed>|null */
	public static function action( string $provider, string $id ): ?array {
		self::collect();
		$key = self::key_if_valid( $provider, $id );
		if ( null === $key || ! isset( self::$actions[ $key ] ) ) {
			return null;
		}
		$public = self::$actions[ $key ];
		unset( $public['executor'] );
		return $public;
	}

	/** @internal Future orchestration runtime boundary; not public API in AF1. */
	public static function executor( string $provider, string $id ): ?callable {
		self::collect();
		$key = self::key_if_valid( $provider, $id );
		if ( null === $key || ! isset( self::$actions[ $key ]['executor'] ) ) {
			return null;
		}
		$executor = self::$actions[ $key ]['executor'];
		return is_callable( $executor ) ? $executor : null;
	}

	/** @internal */
	public static function reset_for_tests(): void {
		self::$triggers = [];
		self::$actions = [];
		self::$collected = false;
	}

	/** @param array<string,mixed> $definition @return array<string,mixed>|null */
	private static function normalize_trigger( array $definition, bool $base_owned ): ?array {
		$allowed = [ 'provider', 'id', 'label', 'description', 'schema_version', 'payload_schema' ];
		if ( [] !== array_diff( array_keys( $definition ), $allowed ) ) {
			return null;
		}

		$common = self::normalize_common( $definition, $base_owned );
		$schema = isset( $definition['payload_schema'] ) && is_array( $definition['payload_schema'] )
			? Schema::normalize( $definition['payload_schema'] )
			: null;
		if ( null === $common || null === $schema ) {
			return null;
		}

		return $common + [ 'payload_schema' => $schema ];
	}

	/** @param array<string,mixed> $definition @return array<string,mixed>|null */
	private static function normalize_action( array $definition, bool $base_owned ): ?array {
		$allowed = [ 'provider', 'id', 'label', 'description', 'schema_version', 'input_schema', 'output_schema', 'required_capability', 'executor' ];
		if ( [] !== array_diff( array_keys( $definition ), $allowed ) ) {
			return null;
		}

		$common = self::normalize_common( $definition, $base_owned );
		$input = isset( $definition['input_schema'] ) && is_array( $definition['input_schema'] )
			? Schema::normalize( $definition['input_schema'] )
			: null;
		$output_raw = $definition['output_schema'] ?? [];
		$output = is_array( $output_raw ) ? Schema::normalize( $output_raw ) : null;
		$capability = isset( $definition['required_capability'] ) && is_string( $definition['required_capability'] )
			? trim( $definition['required_capability'] )
			: '';
		$executor = $definition['executor'] ?? null;

		if (
			null === $common
			|| null === $input
			|| null === $output
			|| '' === $capability
			|| sanitize_key( $capability ) !== $capability
			|| ! is_callable( $executor )
		) {
			return null;
		}

		return $common + [
			'input_schema'        => $input,
			'output_schema'       => $output,
			'required_capability' => $capability,
			'executor'            => $executor,
		];
	}

	/** @param array<string,mixed> $definition @return array<string,mixed>|null */
	private static function normalize_common( array $definition, bool $base_owned ): ?array {
		$provider = $base_owned
			? self::BASE_PROVIDER
			: ( isset( $definition['provider'] ) && is_string( $definition['provider'] ) ? trim( $definition['provider'] ) : '' );
		$id = isset( $definition['id'] ) && is_string( $definition['id'] ) ? trim( $definition['id'] ) : '';
		$label = isset( $definition['label'] ) && is_string( $definition['label'] )
			? trim( wp_strip_all_tags( $definition['label'] ) )
			: '';
		$description = isset( $definition['description'] ) && is_string( $definition['description'] )
			? trim( wp_strip_all_tags( $definition['description'] ) )
			: '';
		$schema_version = isset( $definition['schema_version'] ) && is_string( $definition['schema_version'] )
			? trim( $definition['schema_version'] )
			: '';

		if ( ! $base_owned ) {
			if (
				self::BASE_PROVIDER === $provider
				|| ! ExtensionRegistry::is_valid_id( $provider )
				|| null === ExtensionRegistry::definition( $provider )
			) {
				self::diagnostic( sprintf( 'Unknown automation capability provider refused: %s.', $provider ) );
				return null;
			}
		}

		if (
			1 !== preg_match( self::ID_PATTERN, $id )
			|| '' === $label
			|| strlen( $label ) > 120
			|| strlen( $description ) > 500
			|| 1 !== preg_match( self::SCHEMA_VERSION_PATTERN, $schema_version )
		) {
			return null;
		}

		return [
			'provider'       => $provider,
			'id'             => $id,
			'label'          => $label,
			'description'    => $description,
			'schema_version' => $schema_version,
		];
	}

	private static function key( string $provider, string $id ): string {
		return $provider . '::' . $id;
	}

	private static function key_if_valid( string $provider, string $id ): ?string {
		$provider = trim( $provider );
		$id = trim( $id );
		if (
			( self::BASE_PROVIDER !== $provider && ! ExtensionRegistry::is_valid_id( $provider ) )
			|| 1 !== preg_match( self::ID_PATTERN, $id )
		) {
			return null;
		}
		return self::key( $provider, $id );
	}

	private static function diagnostic( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[Core Blueprint Automation Foundation] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
