<?php
declare(strict_types=1);

use CB\Core\Admin\Admin;

final class CB_Base_Extensions_Menu_Order_Contract_Test extends WP_UnitTestCase {

	public function test_extensions_is_moved_after_existing_extension_submenus(): void {
		global $submenu;
		$original = $submenu;

		$submenu = [
			CB_CORE_PARENT_MENU => [
				[ 'Core Blueprint', 'manage_options', CB_CORE_PARENT_MENU, 'Core Blueprint' ],
				[ 'Preferences', 'manage_options', Admin::PREFERENCES_SLUG, 'Preferences' ],
				[ 'Extensions', 'manage_options', Admin::SETTINGS_SLUG, 'Extensions' ],
				[ 'Legacy extension page', 'manage_options', 'vendor-extension-page', 'Legacy extension page' ],
			],
		];

		Admin::remove_duplicate_submenu();
		$items = array_values( $submenu[ CB_CORE_PARENT_MENU ] );
		$last  = $items[ count( $items ) - 1 ];

		self::assertSame( Admin::SETTINGS_SLUG, $last[2] ?? '' );
		self::assertSame( 'Extensions', $last[0] ?? '' );

		$submenu = $original;
	}
}
