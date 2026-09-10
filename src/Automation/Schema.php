<?php
declare(strict_types=1);
/**
 * Strict transport schema for Automation Foundation trigger/action contracts.
 *
 * Schemas intentionally describe only scalar values and flat lists of scalars.
 * Domain objects, resources and nested arbitrary structures do not belong on
 * the automation interoperability boundary.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Automation;

defined( 'ABSPATH' ) || exit;

final class Schema {

	private const FIELD_PATTERN = '/^[a-z][a-z0-9_]*$/D';
	private const TYPES = [ 'string', 'integer', 'number', 'boolean', 'array' ];
	private const ITEM_TYPES = [ 'string', 'integer', 'number', 'boolean' ];

	/**
	 * Normalize and validate one public transport schema.
	 *
	 * @param array<string,mixed> $schema
	 * @return array<string,array{type:string,required:bool,sensitive:bool,items:?string}>|null
	 */
	public static function normalize( array $schema ): ?array {
		$normalized = [];

		foreach ( $schema as $field => $definition ) {
			if ( ! is_string( $field ) || 1 !== preg_match( self::FIELD_PATTERN, $field ) || ! is_array( $definition ) ) {
				return null;
			}

			$unknown = array_diff( array_keys( $definition ), [ 'type', 'required', 'sensitive', 'items' ] );
			if ( [] !== $unknown ) {
				return null;
			}

			$type = isset( $definition['type'] ) && is_string( $definition['type'] )
				? trim( $definition['type'] )
				: '';
			if ( ! in_array( $type, self::TYPES, true ) ) {
				return null;
			}

			$required = $definition['required'] ?? false;
			$sensitive = $definition['sensitive'] ?? false;
			if ( ! is_bool( $required ) || ! is_bool( $sensitive ) ) {
				return null;
			}

			$items = null;
			if ( 'array' === $type ) {
				$items = $definition['items'] ?? null;
				if ( ! is_string( $items ) || ! in_array( $items, self::ITEM_TYPES, true ) ) {
					return null;
				}
			} elseif ( array_key_exists( 'items', $definition ) ) {
				return null;
			}

			$normalized[ $field ] = [
				'type'      => $type,
				'required'  => $required,
				'sensitive' => $sensitive,
				'items'     => $items,
			];
		}

		return $normalized;
	}

	/**
	 * Validate a runtime payload against a previously declared schema.
	 *
	 * Unknown payload fields fail closed. Automation consumers may therefore
	 * rely on the declared schema instead of receiving undocumented data.
	 *
	 * @param array<string,mixed> $payload
	 * @param array<string,mixed> $schema
	 */
	public static function validate( array $payload, array $schema ): bool {
		$normalized = self::normalize( $schema );
		if ( null === $normalized ) {
			return false;
		}

		foreach ( $normalized as $field => $definition ) {
			if ( $definition['required'] && ! array_key_exists( $field, $payload ) ) {
				return false;
			}
		}

		foreach ( $payload as $field => $value ) {
			if ( ! is_string( $field ) || ! isset( $normalized[ $field ] ) ) {
				return false;
			}
			if ( ! self::matches( $value, $normalized[ $field ] ) ) {
				return false;
			}
		}

		return true;
	}

	/** @param array{type:string,required:bool,sensitive:bool,items:?string} $definition */
	private static function matches( mixed $value, array $definition ): bool {
		switch ( $definition['type'] ) {
			case 'string':
				return is_string( $value );
			case 'integer':
				return is_int( $value );
			case 'number':
				return is_int( $value ) || ( is_float( $value ) && is_finite( $value ) );
			case 'boolean':
				return is_bool( $value );
			case 'array':
				if ( ! is_array( $value ) || ! array_is_list( $value ) || null === $definition['items'] ) {
					return false;
				}
				foreach ( $value as $item ) {
					if ( ! self::matches_item( $item, $definition['items'] ) ) {
						return false;
					}
				}
				return true;
		}

		return false;
	}

	private static function matches_item( mixed $value, string $type ): bool {
		return match ( $type ) {
			'string'  => is_string( $value ),
			'integer' => is_int( $value ),
			'number'  => is_int( $value ) || ( is_float( $value ) && is_finite( $value ) ),
			'boolean' => is_bool( $value ),
			default   => false,
		};
	}
}
