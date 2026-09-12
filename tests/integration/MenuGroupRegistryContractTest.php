<?php
declare(strict_types=1);

use CB\Core\Admin\MenuGroup;
use CB\Core\Admin\MenuGroupRegistry;
use CB\Core\Admin\Page;

final class CB_Base_Menu_Group_Registry_Contract_Test extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		MenuGroupRegistry::_reset_for_testing();
	}

	protected function tearDown(): void {
		MenuGroupRegistry::_reset_for_testing();
		parent::tearDown();
	}

	public function test_registers_typed_product_group_and_pages_without_domain_special_cases(): void {
		$root = $this->page( 'cb-test-product', 'Workflows', 'manage_options', 10 );
		$child = $this->page( 'cb-test-product-runs', 'Runs', 'read', 20 );
		$group = new MenuGroup(
			'cb-test-product',
			'Test Product',
			'Test Product',
			'read',
			'dashicons-admin-generic',
			58
		);

		self::assertTrue(
			MenuGroupRegistry::register(
				$group,
				[ $root, $child ],
				[
					'cb-test-product' => [ 'components' => [ 'cards' ] ],
					'cb-test-product-runs' => [ 'components' => [ 'status' ] ],
				]
			)
		);
		self::assertSame( $group, MenuGroupRegistry::group( 'cb-test-product' ) );
		self::assertSame( $root, MenuGroupRegistry::get( 'cb-test-product' ) );
		self::assertSame( $child, MenuGroupRegistry::get( 'cb-test-product-runs' ) );
	}

	public function test_rejects_group_without_same_slug_landing_page(): void {
		$group = new MenuGroup(
			'cb-test-product',
			'Test Product',
			'Test Product',
			'manage_options'
		);

		self::assertFalse(
			MenuGroupRegistry::register(
				$group,
				[ $this->page( 'cb-test-product-runs', 'Runs', 'manage_options', 10 ) ]
			)
		);
	}

	private function page( string $slug, string $menu_title, string $capability, ?int $position ): Page {
		return new class( $slug, $menu_title, $capability, $position ) implements Page {
			public function __construct(
				private string $slug_value,
				private string $menu_title_value,
				private string $capability_value,
				private ?int $position_value
			) {}

			public function slug(): string {
				return $this->slug_value;
			}

			public function title(): string {
				return $this->menu_title_value;
			}

			public function menu_title(): string {
				return $this->menu_title_value;
			}

			public function capability(): string {
				return $this->capability_value;
			}

			public function position(): ?int {
				return $this->position_value;
			}

			public function render(): void {}
		};
	}
}
