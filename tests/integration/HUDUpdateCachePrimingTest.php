<?php
declare(strict_types=1);

use CB\Core\HUD\Bootstrap;

final class CB_Base_HUD_Update_Cache_Priming_Test extends WP_UnitTestCase {

    private int $administrator_id = 0;

    public function set_up(): void {
        parent::set_up();

        wp_using_ext_object_cache(false);
        $this->administrator_id = self::factory()->user->create([
            'role' => 'administrator',
        ]);
        wp_set_current_user($this->administrator_id);
        add_filter('map_meta_cap', [ $this, 'grant_update_core_capability' ], PHP_INT_MAX, 2);
    }

    public function tear_down(): void {
        remove_filter('map_meta_cap', [ $this, 'grant_update_core_capability' ], PHP_INT_MAX);
        wp_using_ext_object_cache(false);
        wp_set_current_user(0);
        parent::tear_down();
    }

    /**
     * Keep this test focused on the HUD cache path rather than WordPress'
     * environment-specific file-modification policy for update_core.
     *
     * @param string[] $caps Primitive capabilities resolved by WordPress.
     * @return string[]
     */
    public function grant_update_core_capability(array $caps, string $cap): array {
        return 'update_core' === $cap ? [ 'read' ] : $caps;
    }

    public function test_prime_hook_runs_immediately_before_hud_item_registration(): void {
        self::assertSame(9, has_action('init', [ Bootstrap::class, 'prime_update_cache' ]));
        self::assertSame(10, has_action('init', [ Bootstrap::class, 'register_items' ]));
    }

    public function test_prime_update_cache_batches_cold_update_site_options_into_one_query(): void {
        global $wpdb;

        self::assertInstanceOf(wpdb::class, $wpdb);
        self::assertTrue(current_user_can('update_core'));
        self::assertFalse(wp_using_ext_object_cache());

        $options = $this->install_update_site_options();

        // Keep the general autoload cache warm, but make these three non-autoloaded
        // update options cold so the measured work belongs only to the F3B prime.
        wp_load_alloptions();
        foreach (array_keys($options) as $option) {
            wp_cache_delete($option, 'options');
        }

        $before_prime = (int) $wpdb->num_queries;
        Bootstrap::prime_update_cache();
        $after_prime = (int) $wpdb->num_queries;

        self::assertSame(1, $after_prime - $before_prime, 'The three update site options should be primed by one bulk query.');

        self::assertNotFalse(get_site_transient('update_plugins'));
        self::assertNotFalse(get_site_transient('update_themes'));
        self::assertNotFalse(get_site_transient('update_core'));
        self::assertSame($after_prime, (int) $wpdb->num_queries, 'Update transient reads should reuse the primed request cache.');
    }

    public function test_prime_update_cache_skips_database_work_with_external_object_cache(): void {
        global $wpdb;

        self::assertInstanceOf(wpdb::class, $wpdb);
        wp_using_ext_object_cache(true);

        $before = (int) $wpdb->num_queries;
        Bootstrap::prime_update_cache();

        self::assertSame($before, (int) $wpdb->num_queries);
    }

    /**
     * @return array<string, object>
     */
    private function install_update_site_options(): array {
        $options = [
            '_site_transient_update_plugins' => (object) [
                'response'     => [],
                'translations' => [],
            ],
            '_site_transient_update_themes' => (object) [
                'response'     => [],
                'translations' => [],
            ],
            '_site_transient_update_core' => (object) [
                'updates'         => [],
                'translations'    => [],
                'last_checked'    => time(),
                'version_checked' => wp_get_wp_version(),
            ],
        ];

        foreach ($options as $option => $value) {
            update_option($option, $value, false);
        }

        return $options;
    }
}
