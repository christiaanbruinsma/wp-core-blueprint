<?php
declare(strict_types=1);
/**
 * ObjectPicker - shared async object selection Foundation.
 *
 * The component owns progressive enhancement, selected-item presentation and
 * the transport contract for async search. Consumers own the search action,
 * authorization, result semantics and persistence.
 *
 * @package Core_Blueprint
 */

namespace CB\Core\UI;

defined( 'ABSPATH' ) || exit;

final class ObjectPicker {
	/**
	 * Render an async searchable object picker over one real text input.
	 *
	 * @param array<string,mixed> $args Component arguments.
	 */
	public static function render( array $args ): string {
		$name          = (string) ( $args['name'] ?? '' );
		$id            = sanitize_html_class( (string) ( $args['id'] ?? '' ) );
		$multiple      = ! empty( $args['multiple'] );
		$action        = sanitize_key( (string) ( $args['action'] ?? '' ) );
		$nonce         = (string) ( $args['nonce'] ?? '' );
		$context       = is_array( $args['context'] ?? null ) ? $args['context'] : [];
		$selected      = is_array( $args['selected'] ?? null ) ? $args['selected'] : [];
		$placeholder   = (string) ( $args['placeholder'] ?? __( 'Search…', 'core-blueprint' ) );
		$empty_message = (string) ( $args['empty_message'] ?? __( 'No matching items found.', 'core-blueprint' ) );
		$extra         = trim( (string) ( $args['class'] ?? '' ) );

		if ( '' === $name || '' === $action || '' === $nonce ) {
			return '';
		}

		$ids = [];
		foreach ( $selected as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$item_id = absint( $item['id'] ?? 0 );
			if ( $item_id > 0 && ! in_array( $item_id, $ids, true ) ) {
				$ids[] = $item_id;
			}
		}
		if ( ! $multiple && count( $ids ) > 1 ) {
			$ids = [ $ids[0] ];
		}

		$value = implode( ',', $ids );
		$classes = [ 'cb-core-object-picker' ];
		if ( '' !== $extra ) {
			$classes[] = $extra;
		}

		$context_json  = wp_json_encode( $context );
		$selected_json = wp_json_encode( array_values( $selected ) );
		if ( ! is_string( $context_json ) || ! is_string( $selected_json ) ) {
			return '';
		}

		$html  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-cb-core-object-picker';
		$html .= ' data-search-action="' . esc_attr( $action ) . '"';
		$html .= ' data-search-nonce="' . esc_attr( $nonce ) . '"';
		$html .= ' data-search-context="' . esc_attr( $context_json ) . '"';
		$html .= ' data-selected="' . esc_attr( $selected_json ) . '"';
		$html .= ' data-multiple="' . ( $multiple ? '1' : '0' ) . '"';
		$html .= ' data-empty-message="' . esc_attr( $empty_message ) . '">';
		$html .= '<input' . ( '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . ' class="regular-text code cb-core-object-picker__input" type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" data-cb-core-object-picker-input />';
		$html .= '<div class="cb-core-object-picker__enhanced" data-cb-core-object-picker-enhanced hidden>';
		$html .= '<div class="cb-core-object-picker__selected" data-cb-core-object-picker-selected></div>';
		$html .= '<label class="screen-reader-text"' . ( '' !== $id ? ' for="' . esc_attr( $id . '-search' ) . '"' : '' ) . '>' . esc_html__( 'Search items', 'core-blueprint' ) . '</label>';
		$html .= '<input' . ( '' !== $id ? ' id="' . esc_attr( $id . '-search' ) . '"' : '' ) . ' type="search" class="regular-text cb-core-object-picker__search" placeholder="' . esc_attr( $placeholder ) . '" autocomplete="off" data-cb-core-object-picker-search />';
		$html .= '<div class="cb-core-object-picker__results" data-cb-core-object-picker-results hidden></div>';
		$html .= '<p class="description cb-core-object-picker__hint">' . esc_html( $multiple ? __( 'Search and select one or more items. Selected IDs are stored in order.', 'core-blueprint' ) : __( 'Search and select one item.', 'core-blueprint' ) ) . '</p>';
		$html .= '</div></div>';

		return $html;
	}
}
