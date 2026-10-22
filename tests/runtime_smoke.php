<?php
/**
 * Dependency-free runtime regression smoke test.
 *
 * This verifies the failure paths that previously could produce an HTTP 500
 * before a real PrestaShop integration test is available. It does not emulate
 * the complete PrestaShop runtime.
 */

declare(strict_types=1);

define('_COOKIE_KEY_', 'test-only-prestashop-cookie-key-with-sufficient-entropy');

final class Context
{
    public object $shop;
}

final class Tools
{
    public static function usingSecureMode(): bool
    {
        return true;
    }
}

final class Configuration
{
    /** @var array<string,string> */
    private static array $values = [];
    private static bool $allowWrites = true;

    public static function reset(array $values = [], bool $allowWrites = true): void
    {
        self::$values = $values;
        self::$allowWrites = $allowWrites;
    }

    public static function get(string $key, $idLang = null, $idShopGroup = null, $idShop = null, $default = false)
    {
        unset($idLang, $idShopGroup, $idShop);
        return self::$values[$key] ?? $default;
    }

    public static function updateGlobalValue(string $key, $value, bool $html = false): bool
    {
        unset($html);
        if (!self::$allowWrites) {
            return false;
        }
        self::$values[$key] = (string) $value;
        return true;
    }

    public static function updateValue(
        string $key,
        $value,
        bool $html = false,
        $idShopGroup = null,
        $idShop = null
    ): bool {
        unset($html, $idShopGroup, $idShop);
        if (!self::$allowWrites) {
            return false;
        }
        self::$values[$key] = (string) $value;
        return true;
    }
}

$root = dirname(__DIR__);
require_once $root . '/src/Security/TokenService.php';
require_once $root . '/src/Security/CsrfService.php';

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Security\CsrfService;
use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Security\TokenService;

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

Configuration::reset();
$tokens = new TokenService();
$createdSecret = (string) Configuration::get('JOOIM_WITHDRAWAL_SECRET');
$assert(strlen($createdSecret) === 64, 'missing module secret is created as 64 hexadecimal characters');
$assert(ctype_xdigit($createdSecret), 'created module secret is hexadecimal');
$firstHash = $tokens->hash('test-value', 'runtime-smoke');
$secondHash = (new TokenService())->hash('test-value', 'runtime-smoke');
$assert(hash_equals($firstHash, $secondHash), 'persisted secret remains stable across service instances');

Configuration::reset([], false);
$fallbackOne = (new TokenService())->hash('test-value', 'runtime-smoke');
$fallbackTwo = (new TokenService())->hash('test-value', 'runtime-smoke');
$assert(hash_equals($fallbackOne, $fallbackTwo), 'read-only installation uses a stable cookie-key fallback');

Configuration::reset(['JOOIM_WITHDRAWAL_SECRET' => str_repeat('a', 64)]);
$_COOKIE = [];
$context = new Context();
$context->shop = (object) ['id' => 7];
$csrf = new CsrfService($context, new TokenService());
$csrfToken = $csrf->issue('identify');
$assert(isset($_COOKIE['jooim_withdrawal_csrf']), 'CSRF service creates its dedicated cookie');
$assert($csrf->validate('identify', $csrfToken, true), 'browser-bound CSRF token validates for its purpose and shop');
$assert(!$csrf->validate('direct_withdrawal', $csrfToken), 'CSRF token cannot be reused for another purpose');
$context->shop = (object) ['id' => 8];
$assert(!$csrf->validate('identify', $csrfToken), 'CSRF token cannot cross shop boundaries');

$baseController = (string) file_get_contents($root . '/src/Compatibility/FrontControllerBase.php');
$identifyController = (string) file_get_contents($root . '/controllers/front/identify.php');
$directController = (string) file_get_contents($root . '/controllers/front/direct.php');
$identifyTemplate = (string) file_get_contents($root . '/views/templates/front/identify.tpl');
$configTemplate = (string) file_get_contents($root . '/views/templates/admin/configure.tpl');
$footerTemplate = (string) file_get_contents($root . '/views/templates/hook/footer_withdrawal_link.tpl');
$frontJs = (string) file_get_contents($root . '/views/js/front.js');
$frontCss = (string) file_get_contents($root . '/views/css/front.css');
$frontJsRevision = (string) file_get_contents($root . '/views/js/front-footer-v4.js');
$frontCssRevision = (string) file_get_contents($root . '/views/css/front-footer-v4.css');
$csrfSource = (string) file_get_contents($root . '/src/Security/CsrfService.php');
$main = (string) file_get_contents($root . '/jooim_withdrawal.php');
$translationInstaller = (string) file_get_contents($root . '/src/Service/TranslationCatalogInstaller.php');
$frontTemplates = glob($root . '/views/templates/front/*.tpl') ?: [];

$assert(strpos($baseController, 'function runPage') !== false, 'shared storefront failure guard exists');
$assert(strpos($baseController, 'WDERR-') !== false, 'customer-visible failure reference exists');
$assert(strpos($baseController, 'PrestaShopLogger::addLog') !== false, 'storefront exceptions are logged');
$assert(strpos($identifyController, '$this->runPage(') !== false, 'order-reference page uses the failure guard');
$assert(strpos($directController, '$this->runPage(') !== false, 'direct withdrawal page uses the failure guard');
$assert(
    strpos($identifyTemplate, 'action="{$jooim_identify_url|') !== false,
    'identify form uses a controller-assigned URL'
);
$assert(
    strpos($identifyTemplate, "getModuleLink('jooim_withdrawal','identify',[],true)") === false,
    'identify template does not use a parser-sensitive inline array call'
);

preg_match_all('/name="(JOOIM_WITHDRAWAL_[A-Z0-9_]+)"/', $configTemplate, $matches);
$configurationInputs = array_values(array_unique($matches[1] ?? []));
$assert(count($configurationInputs) === 24, 'all 24 editable settings are visible in Back Office');
preg_match_all('/type="hidden" name="(JOOIM_WITHDRAWAL_[A-Z0-9_]+)" value="0"/', $configTemplate, $hiddenBooleans);
$assert(count($hiddenBooleans[1]) === 6, 'six boolean switches submit an explicit unchecked zero');
foreach ($configurationInputs as $field) {
    $assert(
        preg_match('/<(?:input|textarea|select)\b(?![^>]*type="hidden")[^>]*name="' . preg_quote($field, '/') . '"/', $configTemplate) === 1,
        $field . ' has a visible editable control'
    );
}
foreach ($hiddenBooleans[1] as $field) {
    $assert(
        preg_match('/type="checkbox" role="switch"[^>]*name="' . preg_quote($field, '/') . '" value="1"/', $configTemplate) === 1,
        $field . ' has an explicit checked one companion'
    );
}
$assert(substr_count($configTemplate, 'class="help-block"') >= 30, 'Back Office inputs and operations include help text');
$assert(strpos($main, "public const VERSION = '1.1.1'") !== false, 'module version is 1.1.1');
$assert($frontJsRevision === $frontJs, 'revisioned footer JavaScript matches canonical source');
$assert($frontCssRevision === $frontCss, 'revisioned footer CSS matches canonical source');
$assert(strpos($main, "FRONT_ASSET_BASENAME = 'front-footer-v4'") !== false, 'revisioned footer assets are registered');
$assert(strpos($main, 'synchronizeRuntimeState') !== false, 'same-version runtime synchronization exists');
$assert(strpos($csrfSource, 'jooim_withdrawal_csrf') !== false, 'CSRF service uses a dedicated module cookie');
$assert(strpos($csrfSource, '$this->context->cookie') === false, 'CSRF service never writes to the PrestaShop main cookie');
$assert(strpos($csrfSource, "'httponly' => true") !== false, 'dedicated CSRF cookie is HttpOnly');
$assert(strpos($csrfSource, "'samesite' => 'Lax'") !== false, 'dedicated CSRF cookie uses SameSite=Lax');
$assert(strpos($configTemplate, '$language_urls') !== false, 'Back Office lists links for active shop languages');
$assert(strpos($main, 'getActiveLanguageUrls') !== false, 'language-specific URLs are generated by language ID');
$assert(strpos($footerTemplate, 'data-jooim-withdrawal-footer-fallback') !== false, 'footer template marks the accessible displayFooter fallback');
$assert(strpos($main, 'hookDisplayFooterPart1') !== false, 'legacy theme-specific hook is retained as a no-output shim');
preg_match('/public function hookDisplayFooterPart1\(\$params\)\s*\{(.*?)\n    \}/s', $main, $footerPart1Match);
$assert(isset($footerPart1Match[1]) && strpos($footerPart1Match[1], 'jooimWithdrawalRenderFooterLink') === false, 'displayFooterPart1 emits no visual link');
$assert(strpos($main, 'jooimWithdrawalStoresUrl') !== false, 'localized stores URL is exposed for exact footer matching');
$assert(strpos($main, 'private function jooimWithdrawalFooterUrl(): string') !== false, 'footer has a dedicated localized URL resolver');
$assert(preg_match('/private function jooimWithdrawalFooterUrl\(\): string.*?getModuleLink\(\s*\$this->name,\s*\'identify\'/s', $main) === 1, 'footer points to the localized order-reference page');
$assert(strpos($footerTemplate, 'jooim_withdrawal_footer_url') !== false, 'footer template receives the localized identify URL');
$assert(strpos($main, "'placement' => 'after-static-stores-link'") !== false, 'footer placement strategy targets the stores link');
$assert(strpos($main, '$output .= $this->renderFooterAttribution') !== false, 'shared Joobox attribution remains available in displayFooter');
$assert(strpos($frontJs, 'JOOIM_WITHDRAWAL_FOOTER_NORMALIZER_V4') !== false, 'footer normalizer V4 is packaged');
$assert(strpos($frontJs, "'link-static-page-stores-'") !== false, 'DOM normalizer targets the stable ps_linklist stores entry');
$assert(strpos($frontJs, 'insertImmediatelyAfter(storesItem, item)') !== false, 'DOM normalizer inserts immediately after the stores item');
$assert(strpos($frontJs, "document.querySelector('footer#footer')") !== false, 'real site footer is selected before page-footer elements');
$assert(strpos($frontJs, 'referenceItem.nextElementSibling === item') !== false, 'immediate adjacency below the stores item is verified');
$assert(strpos($frontCss, 'order: 2147483647') === false, 'footer CSS no longer forces detached block ordering');
$assert(strpos($frontCss, 'text-align: center') === false, 'footer link is no longer a detached centered row');
$assert(strpos($main, 'hookActionAfterLoadRoutes') !== false, 'multilingual route hook is implemented');
$assert(strpos($main, "'en' => ['base' => 'contract-withdrawal', 'direct' => 'direct'") !== false, 'English route is localized');
$assert(strpos($main, "'de' => ['base' => 'vertragswiderruf', 'direct' => 'direkt'") !== false, 'German route is localized');
$assert(strpos($main, "'uk' => ['base' => 'vidmova-vid-dohovoru', 'direct' => 'bezposeredno'") !== false, 'Ukrainian route is localized without Slovak suffix');
$assert(strpos($configTemplate, '$universal_public_url') !== false, 'Back Office exposes a language-neutral order-reference URL');
$assert(strpos($configTemplate, '$universal_direct_url') !== false, 'Back Office exposes a language-neutral direct-withdrawal URL');
$assert(strpos($baseController, "\$themePageTemplate = 'page.tpl'") !== false, 'public pages inherit the active theme page shell');
$assert(strpos($baseController, "\$themePageTemplate = 'customer/page.tpl'") !== false, 'logged-in pages can inherit the customer account shell');
$assert(count($frontTemplates) === 9, 'all nine storefront templates are present');
foreach ($frontTemplates as $template) {
    $source = (string) file_get_contents($template);
    $assert(strpos($source, '{extends file=$jooim_theme_page_template}') === 0, basename($template) . ' inherits the selected theme parent');
}
$assert(strpos($translationInstaller, "_DB_PREFIX_ . 'translation'") !== false, 'same-version importer writes packaged translations to PrestaShop translation storage');
$assert(strpos($translationInstaller, '$current === $source && $target !== $source') !== false, 'translation importer preserves genuine merchant edits');
$assert(strpos($main, 'jooimWithdrawalSynchronizeAdminTabNames') !== false, 'Back Office tab labels are synchronized per employee language');

if ($failures !== []) {
    echo 'FAIL: ' . count($failures) . PHP_EOL;
    foreach ($failures as $failure) {
        echo ' - ' . $failure . PHP_EOL;
    }
    exit(1);
}

echo "PASS: runtime smoke checks\n";
