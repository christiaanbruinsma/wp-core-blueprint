<?php
declare(strict_types=1);

final class CB_Base_Gate5B_Localization_Runtime_Test extends WP_UnitTestCase {

    public function test_all_shipped_locales_load_from_bundled_php_catalogs(): void {
        $cases = [
            'nl_NL' => [ 'Volgende', '%d auditregels opgeschoond.' ],
            'de_DE' => [ 'Weiter', '%d Audit-Einträge bereinigt.' ],
            'fr_FR' => [ 'Suivant', '%d entrées d’audit purgées.' ],
            'es_ES' => [ 'Siguiente', 'Se han depurado %d entradas de auditoría.' ],
            'it_IT' => [ 'Avanti', 'Eliminate %d voci di audit.' ],
            'pt_PT' => [ 'Seguinte', 'Eliminadas %d entradas de auditoria.' ],
        ];

        foreach ( $cases as $locale => [ $expected_next, $expected_plural ] ) {
            $php_catalog = CB_CORE_DIR . 'languages/core-blueprint-' . $locale . '.l10n.php';
            $legacy_mo   = CB_CORE_DIR . 'languages/core-blueprint-' . $locale . '.mo';

            self::assertFileExists( $php_catalog, $locale );
            self::assertFileDoesNotExist( $legacy_mo, $locale );

            unload_textdomain( 'core-blueprint', true );

            // WordPress 6.5+ prefers the sibling .l10n.php file for an MO path;
            // Base requires WP 7.0+, so PHP-only catalogs are a supported runtime.
            $loaded = load_textdomain( 'core-blueprint', $legacy_mo, $locale );

            self::assertTrue( $loaded, 'Failed loading bundled PHP catalog for ' . $locale );
            self::assertSame( $expected_next, __( 'Next', 'core-blueprint' ), $locale );
            self::assertSame(
                $expected_plural,
                _n( 'Pruned %d audit entry.', 'Pruned %d audit entries.', 2, 'core-blueprint' ),
                $locale
            );
        }

        unload_textdomain( 'core-blueprint', true );
    }
}
