<?php
declare(strict_types=1);

final class CB_Base_Integration_Grid_Test_Page implements \CB\Core\Admin\Page {
    public function slug(): string {
        return 'cb-integration-grid-test';
    }

    public function title(): string {
        return 'Integration Grid Test';
    }

    public function menu_title(): string {
        return 'Integration Grid Test';
    }

    public function capability(): string {
        return 'manage_options';
    }

    public function position(): ?int {
        return 150;
    }

    public function render(): void {
    }
}

final class CB_Base_Integration_Grid_Contract_Test extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();
        \CB\Core\Admin\PageRegistry::_reset_for_testing();
    }

    public function tear_down(): void {
        \CB\Core\Admin\PageRegistry::_reset_for_testing();
        parent::tear_down();
    }

    public function test_renderer_maps_integration_states_to_existing_status_semantics(): void {
        $html = \CB\Core\UI\IntegrationGrid::render( [
            [
                'name'         => 'Alpha',
                'description'  => 'Ready integration.',
                'status'       => 'ready',
                'status_label' => 'Ready',
            ],
            [
                'name'         => 'Beta',
                'description'  => 'Needs setup.',
                'status'       => 'needs-setup',
                'status_label' => 'Needs setup',
            ],
            [
                'name'         => 'Gamma',
                'description'  => 'Optional integration.',
                'status'       => 'optional',
                'status_label' => 'Optional',
            ],
            [
                'name'         => 'Delta',
                'description'  => 'Unavailable integration.',
                'status'       => 'unavailable',
                'status_label' => 'Unavailable',
            ],
        ] );

        self::assertStringContainsString( 'cb-core-integration-grid', $html );
        self::assertSame( 4, substr_count( $html, 'cb-core-integration-card"' ) );
        self::assertStringContainsString( 'cb-core-status__dot--success', $html );
        self::assertStringContainsString( 'cb-core-status__dot--warning', $html );
        self::assertStringContainsString( 'cb-core-status__dot--muted', $html );
        self::assertStringContainsString( 'cb-core-status__dot--danger', $html );
        self::assertStringContainsString( 'No configuration required', $html );
    }

    public function test_renderer_escapes_content_and_only_emits_complete_cta(): void {
        $html = \CB\Core\UI\IntegrationGrid::render( [
            [
                'name'         => '<Alpha>',
                'description'  => '<script>alert(1)</script>',
                'status'       => 'needs-setup',
                'status_label' => '<Needs setup>',
                'action_url'   => 'https://example.test/wp-admin/admin.php?page=alpha&tab=<unsafe>',
                'action_label' => '<Configure>',
            ],
            [
                'name'         => 'Incomplete CTA',
                'description'  => 'Action label intentionally absent.',
                'status'       => 'optional',
                'status_label' => 'Optional',
                'action_url'   => 'https://example.test/configure',
            ],
        ] );

        self::assertStringNotContainsString( '<script>', $html );
        self::assertStringContainsString( '&lt;Alpha&gt;', $html );
        self::assertStringContainsString( '&lt;Needs setup&gt;', $html );
        self::assertStringContainsString( '&lt;Configure&gt;', $html );
        self::assertSame( 1, substr_count( $html, 'cb-core-button--secondary' ) );
        self::assertStringNotContainsString( 'https://example.test/configure"', $html );
    }

    public function test_invalid_items_fail_closed(): void {
        self::assertSame( '', \CB\Core\UI\IntegrationGrid::render( [] ) );
        self::assertSame( '', \CB\Core\UI\IntegrationGrid::render( [
            [ 'name' => '', 'status' => 'ready' ],
            [ 'name' => 'Unknown', 'status' => 'mystery' ],
            'not-an-item',
        ] ) );
    }

    public function test_page_registry_accepts_single_integration_grid_requirement(): void {
        self::assertTrue( \CB\Core\Admin\PageRegistry::register(
            new CB_Base_Integration_Grid_Test_Page(),
            [ 'components' => [ 'integration-grid' ] ]
        ) );
    }

    public function test_integration_grid_requirement_owns_internal_asset_dependencies(): void {
        $context = \CB\Core\Admin\ScreenContext::from_request( 'core-blueprint_page_cb-integration-grid-test' );

        \CB\Core\Admin\AdminAssetCatalog::enqueue_component_requirement( 'integration-grid', $context );

        self::assertTrue( wp_style_is( 'cb-core-css-integration-grid', 'enqueued' ) );
        self::assertTrue( wp_style_is( 'cb-core-css-status-indicators', 'enqueued' ) );
        self::assertTrue( wp_style_is( 'cb-core-css-buttons', 'enqueued' ) );
    }
}
