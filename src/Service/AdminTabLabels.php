<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

/** Resolve BO captions even when the current Symfony/SQL catalogue is stale. */
final class AdminTabLabels
{
    private string $catalogue;
    private const SOURCES = ['links'=>'Links', 'availability'=>'Availability', 'trader'=>'Trader', 'security'=>'Security', 'data'=>'Data removal'];
    public function __construct(string $catalogue) { $this->catalogue = $catalogue; }
    public function labels($language, callable $translate): array
    {
        $rows = json_decode((string) file_get_contents($this->catalogue), true);
        if (!is_array($rows)) { throw new \RuntimeException('Packaged admin tab catalogue is invalid.'); }
        $locale = is_object($language) && method_exists($language, 'getLocale') ? (string) $language->getLocale() : (string) ($language->locale ?? $language->language_code ?? '');
        $locale = strtolower(str_replace('_', '-', trim($locale)));
        $iso = strtolower(trim((string) ($language->iso_code ?? '')));
        if ($iso === '') { $iso = explode('-', $locale)[0]; }
        $fallback = [];
        foreach ($rows as $key=>$values) { if (strtolower($key) === $locale) { $fallback = $values; break; } }
        if (!$fallback) {
            foreach ($rows as $key=>$values) { if (strtolower(explode('-', $key)[0]) === $iso) { $fallback = $values; break; } }
        }
        $labels = [];
        foreach (self::SOURCES as $key=>$source) {
            $native = (string) $translate($source);
            // Native merchant wording wins; only missing/English fallback is repaired.
            $labels[$key] = $native !== '' && $native !== $source ? $native : ($fallback[$source] ?? $source);
        }
        return $labels;
    }
}
