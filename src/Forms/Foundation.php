<?php
declare(strict_types=1);
/**
 * Core Blueprint Forms Foundation contract definition.
 *
 * Base owns the normalized forms interoperability boundary. Concrete builders
 * and form plugins remain external providers and implement this public contract
 * through the Generic Interoperability Foundation.
 *
 * @package Core_Blueprint
 * @since   1.0.0
 */

namespace CB\Core\Forms;

use CB\Core\Interoperability\Registry;

defined( 'ABSPATH' ) || exit;

final class Foundation {

	public const CONTRACT_OWNER  = 'core-blueprint';
	public const CONTRACT_ID     = 'forms.provider';
	public const CONTRACT_VERSION = '1';

	/** Provider can emit normalized form submissions into Base. */
	public const SUPPORT_SUBMISSION_EMIT = 'submission.emit';

	private static bool $booted = false;

	/** Attach the Base-owned contract definition to Interoperability collection. */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'cb_core_register_interoperability_contracts', [ self::class, 'register_contract' ] );
	}

	/** @internal Base-owned contract registration callback. */
	public static function register_contract(): void {
		Registry::register_base_contract( [
			'id'          => self::CONTRACT_ID,
			'version'     => self::CONTRACT_VERSION,
			'label'       => __( 'Forms provider', 'core-blueprint' ),
			'description' => __( 'Provides normalized form interoperability to Core Blueprint.', 'core-blueprint' ),
			'interface'   => ProviderInterface::class,
		] );
	}
}
