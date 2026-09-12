<?php
declare(strict_types=1);

/**
 * Temporary Forms Foundation localization synchronizer.
 *
 * Materializes exactly the two reviewed Forms Foundation strings across the six
 * canonical PHP catalogs and advances the canonical source-count guard from
 * 3346 to 3348. The script is intentionally narrow and idempotent.
 */

$root = dirname(__DIR__);

$entries = [
    'Forms provider' => [
        'anchor' => 'Format',
        'translations' => [
            'nl_NL' => 'Formulierprovider',
            'de_DE' => 'Formularanbieter',
            'fr_FR' => 'Fournisseur de formulaires',
            'es_ES' => 'Proveedor de formularios',
            'it_IT' => 'Provider di moduli',
            'pt_PT' => 'Fornecedor de formulários',
        ],
    ],
    'Provides normalized form interoperability to Core Blueprint.' => [
        'anchor' => 'Provider',
        'translations' => [
            'nl_NL' => 'Biedt genormaliseerde formulierinteroperabiliteit aan Core Blueprint.',
            'de_DE' => 'Stellt Core Blueprint normalisierte Formular-Interoperabilität bereit.',
            'fr_FR' => 'Fournit à Core Blueprint une interopérabilité normalisée pour les formulaires.',
            'es_ES' => 'Proporciona a Core Blueprint interoperabilidad normalizada para formularios.',
            'it_IT' => 'Fornisce a Core Blueprint interoperabilità normalizzata per i moduli.',
            'pt_PT' => 'Fornece ao Core Blueprint interoperabilidade normalizada para formulários.',
        ],
    ],
];

$locales = [ 'nl_NL', 'de_DE', 'fr_FR', 'es_ES', 'it_IT', 'pt_PT' ];

function cb_forms_l10n_php_string(string $value): string {
    return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
}

foreach ($locales as $locale) {
    $path = $root . '/languages/core-blueprint-' . $locale . '.l10n.php';
    if (!is_file($path)) {
        throw new RuntimeException(sprintf('Missing catalog: %s', $path));
    }

    $catalog = require $path;
    if (!is_array($catalog) || !isset($catalog['messages']) || !is_array($catalog['messages'])) {
        throw new RuntimeException(sprintf('Invalid catalog payload: %s', $locale));
    }

    $messageCount = count($catalog['messages']);
    $alreadySynchronized = 3348 === $messageCount;
    if (!$alreadySynchronized && 3346 !== $messageCount) {
        throw new RuntimeException(sprintf('%s expected 3346 or 3348 messages, found %d.', $locale, $messageCount));
    }

    if ($alreadySynchronized) {
        foreach ($entries as $source => $definition) {
            $expected = $definition['translations'][$locale] ?? null;
            if (($catalog['messages'][$source] ?? null) !== $expected) {
                throw new RuntimeException(sprintf('%s has an unexpected synchronized value for %s.', $locale, $source));
            }
        }
        printf("%s: already synchronized.\n", $locale);
        continue;
    }

    $contents = (string) file_get_contents($path);
    foreach ($entries as $source => $definition) {
        if (array_key_exists($source, $catalog['messages']) || str_contains($contents, cb_forms_l10n_php_string($source) . ' =>')) {
            throw new RuntimeException(sprintf('%s unexpectedly already contains %s.', $locale, $source));
        }

        $anchor = (string) $definition['anchor'];
        $translation = $definition['translations'][$locale] ?? null;
        if (!is_string($translation) || '' === $translation) {
            throw new RuntimeException(sprintf('Missing %s translation for %s.', $locale, $source));
        }

        $needle = '        ' . cb_forms_l10n_php_string($anchor) . ' => ';
        $anchorAt = strpos($contents, $needle);
        if (false === $anchorAt) {
            throw new RuntimeException(sprintf('%s anchor not found for %s.', $locale, $source));
        }
        $lineEnd = strpos($contents, "\n", $anchorAt);
        if (false === $lineEnd) {
            throw new RuntimeException(sprintf('%s anchor line is unterminated for %s.', $locale, $source));
        }

        $line = '        ' . cb_forms_l10n_php_string($source) . ' => ' . cb_forms_l10n_php_string($translation) . ",\n";
        $contents = substr($contents, 0, $lineEnd + 1) . $line . substr($contents, $lineEnd + 1);
    }

    if (false === file_put_contents($path, $contents)) {
        throw new RuntimeException(sprintf('Could not write catalog: %s', $path));
    }

    $updated = require $path;
    if (!is_array($updated) || !isset($updated['messages']) || 3348 !== count($updated['messages'])) {
        throw new RuntimeException(sprintf('%s did not materialize to 3348 messages.', $locale));
    }
    foreach ($entries as $source => $definition) {
        if (($updated['messages'][$source] ?? null) !== $definition['translations'][$locale]) {
            throw new RuntimeException(sprintf('%s verification failed for %s.', $locale, $source));
        }
    }

    printf("%s: synchronized.\n", $locale);
}

$guardPath = $root . '/tools/check-translations.php';
$guard = (string) file_get_contents($guardPath);
$oldCount = 'if ( 3346 !== count( $source ) ) {';
$newCount = 'if ( 3348 !== count( $source ) ) {';
if (str_contains($guard, $oldCount)) {
    $guard = str_replace($oldCount, $newCount, $guard, $countReplacements);
    if (1 !== $countReplacements) {
        throw new RuntimeException('Could not advance the canonical translation source-count guard exactly once.');
    }
} elseif (!str_contains($guard, $newCount)) {
    throw new RuntimeException('Translation source-count guard is in an unexpected state.');
}

$oldDiagnostic = 'Expected 3344 canonical source keys';
$newDiagnostic = 'Expected 3348 canonical source keys';
if (str_contains($guard, $oldDiagnostic)) {
    $guard = str_replace($oldDiagnostic, $newDiagnostic, $guard, $diagnosticReplacements);
    if (1 !== $diagnosticReplacements) {
        throw new RuntimeException('Could not update the canonical translation diagnostic exactly once.');
    }
} elseif (!str_contains($guard, $newDiagnostic)) {
    throw new RuntimeException('Translation source-count diagnostic is in an unexpected state.');
}

if (false === file_put_contents($guardPath, $guard)) {
    throw new RuntimeException('Could not write the translation source-count guard.');
}

printf("PASS: Forms Foundation catalogs synchronized across %d locales.\n", count($locales));
