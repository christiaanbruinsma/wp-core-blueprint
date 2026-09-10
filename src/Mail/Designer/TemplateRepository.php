<?php
declare(strict_types=1);
/**
 * Persistence boundary for user-customized Mail Designer templates.
 *
 * Provider defaults are never copied globally. Only explicit overrides are
 * stored, together with the provider-default fingerprint they were based on.
 * Unknown overrides remain untouched while a provider is temporarily inactive.
 *
 * @package Core_Blueprint
 */

namespace CB\Core\Mail\Designer;

use CB\Core\Design\Profile\Mail\Validator;

defined( 'ABSPATH' ) || exit;

final class TemplateRepository {
	public const OPTION = 'cb_core_mail_template_overrides';

	/** @return array<string,mixed>|null */
	public static function get( string $template_id ): ?array {
		$definition = TemplateRegistry::get( $template_id );
		if ( null === $definition ) {
			return null;
		}

		$overrides = self::overrides();
		$override = is_array( $overrides[ $template_id ] ?? null ) ? $overrides[ $template_id ] : [];
		$project = is_array( $override['project'] ?? null ) ? $override['project'] : $definition['project'];
		$subject = isset( $override['subject'] ) && is_string( $override['subject'] ) ? $override['subject'] : (string) $definition['subject'];

		return array_merge( $definition, [
			'subject'        => $subject,
			'project'        => $project,
			'customized'     => [] !== $override,
			'modified_at'    => isset( $override['modified_at'] ) ? (string) $override['modified_at'] : '',
			'base_fingerprint' => isset( $override['base_fingerprint'] ) ? (string) $override['base_fingerprint'] : self::fingerprint( $definition ),
		]);
	}

	/** @param array<string,mixed> $project */
	public static function save( string $template_id, string $subject, array $project ): bool {
		$definition = TemplateRegistry::get( $template_id );
		if ( null === $definition ) {
			return false;
		}

		$diagnostics = ( new Validator() )->validate( $project );
		if ( $diagnostics->has_errors() ) {
			return false;
		}

		$subject = sanitize_text_field( $subject );
		if ( '' === $subject ) {
			return false;
		}

		$overrides = self::overrides();
		$overrides[ $template_id ] = [
			'subject'          => $subject,
			'project'          => $project,
			'base_fingerprint' => self::fingerprint( $definition ),
			'modified_at'      => gmdate( 'Y-m-d H:i:s' ),
		];
		return update_option( self::OPTION, $overrides, false );
	}

	public static function reset( string $template_id ): bool {
		if ( null === TemplateRegistry::get( $template_id ) ) {
			return false;
		}
		$overrides = self::overrides();
		if ( ! isset( $overrides[ $template_id ] ) ) {
			return true;
		}
		unset( $overrides[ $template_id ] );
		return update_option( self::OPTION, $overrides, false );
	}

	/** @return array<string,array<string,mixed>> */
	public static function overrides(): array {
		$stored = get_option( self::OPTION, [] );
		return is_array( $stored ) ? $stored : [];
	}

	/** @param array<string,mixed> $definition */
	private static function fingerprint( array $definition ): string {
		$payload = [
			'subject' => (string) ( $definition['subject'] ?? '' ),
			'project' => is_array( $definition['project'] ?? null ) ? $definition['project'] : [],
		];
		$json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		return hash( 'sha256', is_string( $json ) ? $json : '' );
	}

	private function __construct() {}
}
