<?php
declare(strict_types=1);

namespace CB\Core\Snippets;

defined( 'ABSPATH' ) || exit;

final class Schema {
	public const TYPES = [ 'php', 'css', 'js', 'html' ];

	public static function default_meta(): array {
		return [
			'id'          => '',
			'title'       => '',
			'description' => '',
			'type'        => 'php',
			'location'    => 'plugins_loaded',
			'priority'    => 10,
			'enabled'     => false,
			'shortcode'   => '',
			'tags'        => [],
			'conditions'  => [ 'relation' => 'and', 'rules' => [] ],
			'created_at'  => '',
			'updated_at'  => '',
			'last_error'  => null,
			'code_hash'   => '',
			'source'      => 'core-blueprint',
		];
	}

	public static function locations_for_type( string $type ): array {
		switch ( $type ) {
			case 'php':
				return [
					'plugins_loaded' => __( 'Everywhere / early', 'core-blueprint' ),
					'init'           => __( 'WordPress init', 'core-blueprint' ),
					'wp_loaded'      => __( 'WordPress loaded', 'core-blueprint' ),
					'admin_init'     => __( 'Admin only', 'core-blueprint' ),
					'wp_head'        => __( 'Frontend head', 'core-blueprint' ),
					'wp_footer'      => __( 'Frontend footer', 'core-blueprint' ),
					'admin_head'     => __( 'Admin head', 'core-blueprint' ),
					'admin_footer'   => __( 'Admin footer', 'core-blueprint' ),
					'shortcode'      => __( 'Shortcode', 'core-blueprint' ),
				];
			case 'css':
				return [
					'frontend' => __( 'Frontend', 'core-blueprint' ),
					'admin'    => __( 'Admin', 'core-blueprint' ),
					'both'     => __( 'Frontend and admin', 'core-blueprint' ),
				];
			case 'js':
				return [
					'wp_head'      => __( 'Frontend head', 'core-blueprint' ),
					'wp_footer'    => __( 'Frontend footer', 'core-blueprint' ),
					'admin_head'   => __( 'Admin head', 'core-blueprint' ),
					'admin_footer' => __( 'Admin footer', 'core-blueprint' ),
				];
			case 'html':
				return [
					'shortcode'    => __( 'Shortcode', 'core-blueprint' ),
					'wp_head'      => __( 'Frontend head', 'core-blueprint' ),
					'wp_footer'    => __( 'Frontend footer', 'core-blueprint' ),
					'admin_head'   => __( 'Admin head', 'core-blueprint' ),
					'admin_footer' => __( 'Admin footer', 'core-blueprint' ),
				];
		}

		return [];
	}

	public static function default_location( string $type ): string {
		$map = [
			'php'  => 'plugins_loaded',
			'css'  => 'frontend',
			'js'   => 'wp_footer',
			'html' => 'shortcode',
		];
		return $map[ $type ] ?? 'plugins_loaded';
	}

	public static function valid_location( string $type, string $location ): bool {
		return isset( self::locations_for_type( $type )[ $location ] );
	}
}
