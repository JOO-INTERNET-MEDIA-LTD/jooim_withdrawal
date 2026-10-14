<?php

namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

/**
 * Imports packaged XLIFF catalogues for already installed languages.
 *
 * PrestaShop normally imports non-native module catalogues during module
 * installation. JOOIM Withdrawal intentionally remains at 1.0.0 while it is
 * tested, so replacing module files does not trigger that installer step.
 * This idempotent synchronizer fills missing/fallback SQL translations without
 * overwriting merchant-customized values.
 */
final class TranslationCatalogInstaller
{
    private string $moduleDirectory;

    public function __construct(string $moduleDirectory)
    {
        $this->moduleDirectory = rtrim($moduleDirectory, '/\\');
    }

    /**
     * @return array{ok:bool,changed:bool,inserted:int,updated:int,skipped:int,errors:array<int,string>}
     */
    public function synchronize(): array
    {
        $result = [
            'ok' => true,
            'changed' => false,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $translationsRoot = $this->moduleDirectory . '/translations';
        if (!is_dir($translationsRoot)) {
            $result['ok'] = false;
            $result['errors'][] = 'Packaged translation directory is missing.';

            return $result;
        }

        $languages = \Language::getLanguages(false);
        $db = \Db::getInstance();
        $table = _DB_PREFIX_ . 'translation';

        try {
            $db->execute('START TRANSACTION');

            foreach ($languages as $language) {
                $idLang = (int) ($language['id_lang'] ?? 0);
                if ($idLang <= 0) {
                    continue;
                }

                $catalogueDirectory = $this->resolveCatalogueDirectory($translationsRoot, $language);
                if ($catalogueDirectory === null) {
                    ++$result['skipped'];
                    continue;
                }

                $catalogues = glob($catalogueDirectory . '/ModulesJooimwithdrawal*.xlf') ?: [];
                sort($catalogues);
                foreach ($catalogues as $catalogue) {
                    $domain = $this->domainFromFilename($catalogue);
                    if ($domain === null) {
                        continue;
                    }

                    $entries = $this->parseCatalogue($catalogue);
                    if ($entries === []) {
                        continue;
                    }

                    $existingRows = $db->executeS(
                        'SELECT `id_translation`, `key`, `translation`'
                        . ' FROM `' . bqSQL($table) . '`'
                        . ' WHERE `id_lang` = ' . $idLang
                        . " AND `domain` = '" . pSQL($domain) . "'"
                        . ' AND `theme` IS NULL'
                        . ' ORDER BY `id_translation` ASC'
                    );
                    $existing = [];
                    foreach ((array) $existingRows as $row) {
                        $key = (string) ($row['key'] ?? '');
                        if ($key !== '' && !isset($existing[$key])) {
                            $existing[$key] = $row;
                        }
                    }

                    foreach ($entries as $source => $target) {
                        if (!isset($existing[$source])) {
                            $ok = $db->execute(
                                'INSERT INTO `' . bqSQL($table) . '`'
                                . ' (`id_lang`, `key`, `translation`, `domain`, `theme`) VALUES ('
                                . $idLang . ", '" . pSQL($source, true) . "', '"
                                . pSQL($target, true) . "', '" . pSQL($domain) . "', NULL)"
                            );
                            if (!$ok) {
                                throw new \RuntimeException('Unable to insert translation for ' . $domain . '.');
                            }
                            ++$result['inserted'];
                            $result['changed'] = true;
                            continue;
                        }

                        $row = $existing[$source];
                        $current = (string) ($row['translation'] ?? '');
                        // Preserve every genuine merchant edit. Replace only an
                        // empty value or the untouched English source fallback.
                        if ($current === '' || ($current === $source && $target !== $source)) {
                            $ok = $db->execute(
                                'UPDATE `' . bqSQL($table) . '` SET `translation` = \''
                                . pSQL($target, true) . '\''
                                . ' WHERE `id_translation` = ' . (int) $row['id_translation']
                            );
                            if (!$ok) {
                                throw new \RuntimeException('Unable to update translation for ' . $domain . '.');
                            }
                            ++$result['updated'];
                            $result['changed'] = true;
                        } else {
                            ++$result['skipped'];
                        }
                    }
                }
            }

            if (!$db->execute('COMMIT')) {
                throw new \RuntimeException('Unable to commit packaged translations.');
            }
        } catch (\Throwable $exception) {
            $db->execute('ROLLBACK');
            $result['ok'] = false;
            $result['changed'] = false;
            $result['errors'][] = $exception->getMessage();

            return $result;
        }

        if ($result['changed']) {
            try {
                if (method_exists('\Tools', 'clearSmartyCache')) {
                    \Tools::clearSmartyCache();
                }
                if (method_exists('\Tools', 'clearSf2Cache')) {
                    \Tools::clearSf2Cache();
                }
            } catch (\Throwable $exception) {
                // Translation rows are already committed. Cache can still be
                // cleared manually from Back Office if the hosting blocks it.
                $result['errors'][] = 'Translations were imported, but cache clearing failed: ' . $exception->getMessage();
            }
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $language
     */
    private function resolveCatalogueDirectory(string $root, array $language): ?string
    {
        $candidates = [];
        foreach (['locale', 'language_code'] as $field) {
            if (!empty($language[$field])) {
                $candidates[] = $this->normalizeLocale((string) $language[$field]);
            }
        }

        $iso = strtolower((string) ($language['iso_code'] ?? ''));
        $preferredByIso = [
            'bg' => 'bg-BG', 'cs' => 'cs-CZ', 'da' => 'da-DK', 'de' => 'de-DE',
            'el' => 'el-GR', 'en' => 'en-US', 'es' => 'es-ES', 'et' => 'et-EE',
            'fi' => 'fi-FI', 'fr' => 'fr-FR', 'ga' => 'ga-IE', 'hr' => 'hr-HR',
            'hu' => 'hu-HU', 'it' => 'it-IT', 'lt' => 'lt-LT', 'lv' => 'lv-LV',
            'mt' => 'mt-MT', 'nl' => 'nl-NL', 'pl' => 'pl-PL', 'pt' => 'pt-PT',
            'ro' => 'ro-RO', 'sk' => 'sk-SK', 'sl' => 'sl-SI', 'sv' => 'sv-SE',
            'uk' => 'uk-UA', 'lb' => 'lb-LU', 'nb' => 'nb-NO', 'nn' => 'nn-NO', 'no' => 'no-NO',
        ];
        if (isset($preferredByIso[$iso])) {
            $candidates[] = $preferredByIso[$iso];
        }

        foreach (array_values(array_unique($candidates)) as $locale) {
            $directory = $root . '/' . $locale;
            if (is_dir($directory)) {
                return $directory;
            }
        }

        return null;
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = str_replace('_', '-', trim($locale));
        $parts = explode('-', $locale, 2);
        if (count($parts) === 1) {
            return strtolower($parts[0]);
        }

        return strtolower($parts[0]) . '-' . strtoupper($parts[1]);
    }

    private function domainFromFilename(string $filename): ?string
    {
        if (!preg_match('/^(ModulesJooimwithdrawal[A-Za-z]+)\./', basename($filename), $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return array<string,string>
     */
    private function parseCatalogue(string $filename): array
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            if (!$document->load($filename, LIBXML_NONET | LIBXML_NOBLANKS)) {
                throw new \RuntimeException('Invalid XLIFF catalogue: ' . basename($filename));
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('x', 'urn:oasis:names:tc:xliff:document:1.2');
        $entries = [];
        foreach ($xpath->query('//x:trans-unit') ?: [] as $unit) {
            if (!$unit instanceof \DOMElement) {
                continue;
            }
            $sourceNode = $xpath->query('./x:source', $unit)->item(0);
            $targetNode = $xpath->query('./x:target', $unit)->item(0);
            $source = $sourceNode ? (string) $sourceNode->textContent : '';
            $target = $targetNode ? (string) $targetNode->textContent : '';
            if ($source === '' || $target === '') {
                continue;
            }
            $entries[$source] = $target;
        }

        return $entries;
    }
}
