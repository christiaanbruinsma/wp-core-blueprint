<?php
declare(strict_types=1);

/**
 * Temporary Designer Mode localization synchronizer.
 *
 * Materializes exactly the two approved Designer Mode strings across the six
 * canonical PHP catalogs and advances the translation source-count guard from
 * 3344 to 3346. The script is intentionally narrow and idempotent.
 */

$root = dirname(__DIR__);

$entries = [
    'Design with Core Blueprint' => [
        'anchor' => 'Design system email presentation once in Core Blueprint. WordPress and installed extensions remain responsible for when mail is sent, to whom and with which domain data.',
        'translations' => [
            'nl_NL' => 'Ontwerp met Core Blueprint',
            'de_DE' => 'Mit Core Blueprint gestalten',
            'fr_FR' => 'Concevoir avec Core Blueprint',
            'es_ES' => 'Diseñar con Core Blueprint',
            'it_IT' => 'Progetta con Core Blueprint',
            'pt_PT' => 'Criar com o Core Blueprint',
        ],
    ],
    'Open Designer Mode' => [
        'anchor' => 'Open Dashboard',
        'translations' => [
            'nl_NL' => 'Designer-modus openen',
            'de_DE' => 'Designer-Modus öffnen',
            'fr_FR' => 'Ouvrir le mode Designer',
            'es_ES' => 'Abrir el modo Diseñador',
            'it_IT' => 'Apri la modalità Designer',
            'pt_PT' => 'Abrir o modo Designer',
        ],
    ],
];

$locales = [ 'nl_NL', 'de_DE', 'fr_FR', 'es_ES', 'it_IT', 'pt_PT' ];

function php_string(string $value): string {
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
    $alreadySynchronized = 3346 === $messageCount;
    if (!$alreadySynchronized && 3344 !== $messageCount) {
        throw new RuntimeException(sprintf('%s expected 3344 or 3346 messages, found %d.', $locale, $messageCount));
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
        if (array_key_exists($source, $catalog['messages']) || str_contains($contents, php_string($source) . ' =>')) {
            throw new RuntimeException(sprintf('%s unexpectedly already contains %s.', $locale, $source));
        }

        $anchor = (string) $definition['anchor'];
        $translation = $definition['translations'][$locale] ?? null;
        if (!is_string($translation) || '' === $translation) {
            throw new RuntimeException(sprintf('Missing %s translation for %s.', $locale, $source));
        }

        $needle = '        ' . php_string($anchor) . ' => ';
        $anchorAt = strpos($contents, $needle);
        if (false === $anchorAt) {
            throw new RuntimeException(sprintf('%s anchor not found for %s.', $locale, $source));
        }
        $lineEnd = strpos($contents, "\n", $anchorAt);
        if (false === $lineEnd) {
            throw new RuntimeException(sprintf('%s anchor line is unterminated for %s.', $locale, $source));
        }

        $line = '        ' . php_string($source) . ' => ' . php_string($translation) . ",\n";
        $contents = substr($contents, 0, $lineEnd + 1) . $line . substr($contents, $lineEnd + 1);
    }

    if (false === file_put_contents($path, $contents)) {
        throw new RuntimeException(sprintf('Could not write catalog: %s', $path));
    }

    $updated = require $path;
    if (!is_array($updated) || !isset($updated['messages']) || 3346 !== count($updated['messages'])) {
        throw new RuntimeException(sprintf('%s did not materialize to 3346 messages.', $locale));
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
$old = 'if ( 3344 !== count( $source ) ) {';
$new = 'if ( 3346 !== count( $source ) ) {';
if (str_contains($guard, $old)) {
    $guard = str_replace($old, $new, $guard, $replacements);
    if (1 !== $replacements || false === file_put_contents($guardPath, $guard)) {
        throw new RuntimeException('Could not advance the canonical translation source-count guard.');
    }
} elseif (!str_contains($guard, $new)) {
    throw new RuntimeException('Translation source-count guard is in an unexpected state.');
}

printf("PASS: Designer Mode catalogs synchronized across %d locales.\n", count($locales));
