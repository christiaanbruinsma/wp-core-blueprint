<?php
declare(strict_types=1);
/**
 * Public Automation Foundation state-capability registration and discovery.
 *
 * State capabilities describe provider-owned, read-only live-state queries.
 * Resolvers are intentionally withheld from public discovery. AF2 defines the
 * interoperability contract only; invocation authority belongs to the future
 * orchestration runtime and the provider remains the final domain boundary.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Automation;

use CB\Core\Automation\Internal\CapabilityRegistry;

defined( 'ABSPATH' ) || exit;

final class StateRegistry {

	/** @param array<string,mixed> $definition */
	public static function register( array $definition ): bool {
		return CapabilityRegistry::register_state( $definition, false );
	}

	/**
	 * Register a Base-owned state capability.
	 *
	 * Provider identity is forced to `core-blueprint`; any supplied provider
	 * value is ignored. Internal Base lifecycle API, not a public extension path.
	 *
	 * @internal
	 * @param array<string,mixed> $definition
	 */
	public static function register_base( array $definition ): bool {
		unset( $definition['provider'] );
		return CapabilityRegistry::register_state( $definition, true );
	}

	/** @return array<string,array<string,mixed>> */
	public static function all(): array {
		return CapabilityRegistry::states();
	}

	/** @return array<string,mixed>|null */
	public static function get( string $provider, string $id ): ?array {
		return CapabilityRegistry::state( $provider, $id );
	}

	/** @internal */
	public static function _reset_for_testing(): void {
		CapabilityRegistry::reset_for_tests();
	}
}
