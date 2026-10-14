<?php
/**
 * Dependency-free static release checks for JOOIM Withdrawal.
 *
 * These checks deliberately avoid requiring a running PrestaShop instance,
 * database server or optional PHP XML extensions.
 */
$root = dirname(__DIR__);
$failures = [];
$pass = [];

function check(bool $condition, string $label): void
{
    global $failures, $pass;
    if ($condition) {
        $pass[] = $label;
    } else {
        $failures[] = $label;
    }
}

function readFileStrict(string $path): string
{
    $content = @file_get_contents($path);
    return $content === false ? '' : $content;
}

function relativePath(string $root, string $path): string
{
    return ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
}

/**
 * @return array<int,array{source:string,target:string}>
 */
function parseXlfUnits(string $xml): array
{
    $units = [];
    if (!preg_match_all('~<trans-unit\b[^>]*>(.*?)</trans-unit>~s', $xml, $blocks)) {
        return $units;
    }

    foreach ($blocks[1] as $block) {
        if (!preg_match('~<source\b[^>]*>(.*?)</source>~s', $block, $sourceMatch)) {
            continue;
        }
        if (!preg_match('~<target\b[^>]*>(.*?)</target>~s', $block, $targetMatch)) {
            continue;
        }
        $decode = static function (string $value): string {
            return html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_XML1, 'UTF-8');
        };
        $units[] = [
            'source' => $decode($sourceMatch[1]),
            'target' => $decode($targetMatch[1]),
        ];
    }

    return $units;
}

/** @return array<int,string> */
function placeholders(string $value): array
{
    preg_match_all('/%[A-Za-z0-9_]+%|\{[A-Za-z0-9_]+\}/', $value, $matches);
    $result = $matches[0] ?? [];
    sort($result);
    return $result;
}

$required = [
    'CHANGELOG.md',
    'CONTRIBUTING.md',
    'LICENSE',
    'README.md',
    'README.sk.md',
    'README.cs.md',
    'README.de.md',
    'README.fr.md',
    'README.pl.md',
    'SECURITY.md',
    'SUPPORT.md',
    'composer.json',
    'config.xml',
    'jooim_withdrawal.php',
    'logo.png',
    'sql/install.sql',
    'sql/uninstall.sql',
    'controllers/front/direct.php',
    'controllers/front/entry.php',
    'controllers/front/submit.php',
    'controllers/front/directsubmit.php',
    'controllers/front/verify.php',
    'controllers/admin/AdminJooimWithdrawalController.php',
    'config/services.yml',
    'src/Command/MaintenanceCommand.php',
    'src/Service/TranslationCatalogInstaller.php',
    'vendor/autoload.php',
    'tests/runtime_smoke.php',
    'tests/submission_pipeline_smoke.php',
    'tests/submission_controller_lifecycle_smoke.php',
    'tests/submission_action_lifecycle_smoke.php',
    'tests/submission_transaction_smoke.php',
    'tests/translation_bridge_smoke.php',
    'tests/validate_catalogs.py',
    'tools/build_admin_translation_catalogs.py',
    'tools/admin_settings_sources.json',
    'views/templates/hook/footer-attribution.tpl',
    'views/templates/hook/footer-link.tpl',
    'views/img/logo.png',
    'var/documents/.gitkeep',
    'var/queue/.gitkeep',
    'var/temporary/.gitkeep',
];
foreach ($required as $file) {
    check(is_file($root . '/' . $file), 'required file: ' . $file);
}

// Repository metadata is intentionally omitted from the PrestaShop installation
// ZIP but must be present when these checks run in the Git repository tree.
$isRepositoryTree = is_file($root . '/.gitattributes') || is_dir($root . '/.github');
if ($isRepositoryTree) {
    foreach (['.gitattributes', '.gitignore', '.github/workflows/quality.yml'] as $file) {
        check(is_file($root . '/' . $file), 'repository file: ' . $file);
    }
}
check(!is_dir($root . '/override'), 'no PrestaShop core override directory');
$temporaryArtifacts = [];
foreach (['*.bak*', '*.orig', '*.rej', '*~'] as $pattern) {
    foreach (glob($root . '/' . $pattern) ?: [] as $path) {
        $temporaryArtifacts[] = basename($path);
    }
}
foreach (glob($root . '/src/**/*.bak*', GLOB_BRACE) ?: [] as $path) {
    $temporaryArtifacts[] = relativePath($root, $path);
}
check($temporaryArtifacts === [], 'no backup or patch-residue files in release tree');

$main = readFileStrict($root . '/jooim_withdrawal.php');
check(strpos($main, "public const VERSION = '1.1.1';") !== false, 'module version is exactly 1.1.1');
check(strpos($main, "\$this->name = 'jooim_withdrawal';") !== false, 'technical module name');
check(strpos($main, 'Modules.Jooimwithdrawal.Admin') !== false, 'explicit Admin translation domain');
check(strpos($main, 'Modules.Jooimwithdrawal.Shop') !== false, 'explicit Shop translation domain');
check(strpos($main, 'public function jooimTranslate(') !== false, 'public translation bridge for PrestaShop 8 collaborators');
check(strpos($main, 'https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal') !== false, 'official GitHub repository URL');
check(strpos($main, 'https://api.github.com/repos/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal/releases/latest') !== false, 'GitHub release API endpoint');
check(strpos($main, 'renderJooboxSupportPanel') !== false, 'Joobox support panel implementation');
check(strpos($main, 'Joobox support for this module') !== false, 'English Joobox support source string');
check(strpos($main, 'getJooboxSupportUrl') !== false, 'localized Joobox support URL resolver');
check(strpos($main, 'renderUpdateCheckPanel') !== false, 'read-only GitHub update checker');
check(strpos($main, '21600') !== false, 'six-hour update-check cache');
check(strpos($main, "'moduleRoutes', 'actionAfterLoadRoutes', 'displayHeader', 'displayFooter'") !== false, 'multilingual routes and standard footer hooks are registered');
check(strpos($main, "'storesLinkIdPrefix' => 'link-static-page-stores-'") !== false, 'stable ps_linklist stores-link id prefix is exposed to the footer normalizer');
check(strpos($main, 'public function hookActionAfterLoadRoutes') !== false, 'per-language route registration hook');
check(strpos($main, 'buildModuleRouteDefinitions') !== false, 'shared route definition builder');
check(strpos($main, "'en' => ['base' => 'contract-withdrawal', 'direct' => 'direct'") !== false, 'English localized withdrawal routes');
check(strpos($main, "'sk' => ['base' => 'odstupenie-od-zmluvy', 'direct' => 'priamo'") !== false, 'Slovak localized withdrawal routes');
check(strpos($main, "'de' => ['base' => 'vertragswiderruf', 'direct' => 'direkt'") !== false, 'German localized withdrawal routes');
check(strpos($main, "'uk' => ['base' => 'vidmova-vid-dohovoru', 'direct' => 'bezposeredno'") !== false, 'Ukrainian localized withdrawal routes');
check(strpos($main, "'uk' => ['base' => 'vidmova-vid-dohovoru', 'direct' => 'priamo'") === false, 'Ukrainian route does not reuse the Slovak direct slug');
check(strpos($main, 'private function jooimWithdrawalFooterUrl(): string') !== false, 'dedicated footer URL resolver');
check(preg_match('/private function jooimWithdrawalFooterUrl\(\): string.*?getModuleLink\(\s*\$this->name,\s*\'identify\'/s', $main) === 1, 'footer targets the localized order-reference page');
check(substr_count($main, "jooimWithdrawalUniversalUrl('direct')") === 0, 'footer does not fall back to the direct-withdrawal target');
check(strpos($main, "gmdate('Y-m-d H:i:s')") !== false, 'UTC timestamp usage');
check(strpos($main, '1.0.1') === false, 'no accidental 1.0.1 version in runtime module');
check(strpos($main, 'synchronizeRuntimeState') !== false, 'same-version runtime state synchronization');
check(strpos($main, 'ensureRuntimeSecurity') !== false, 'same-version security initialization');
check(strpos($main, 'getActiveLanguageUrls') !== false, 'active-language public URL generator');
check(strpos($main, 'hookDisplayFooterPart1') !== false, 'legacy displayFooterPart1 compatibility shim remains available');
preg_match('/public function hookDisplayFooterPart1\(\$params\)\s*\{(.*?)\n    \}/s', $main, $footerPart1Match);
check(isset($footerPart1Match[1]) && strpos($footerPart1Match[1], 'jooimWithdrawalRenderFooterLink') === false, 'displayFooterPart1 compatibility shim emits no visual link');
check(strpos($main, "'displayFooterPart1'] as \$legacyHook") !== false, 'stale displayFooterPart1 registration is removed during synchronization');
check(strpos($main, 'jooimWithdrawalStoresUrl') !== false, 'localized stores URL is exposed as a secondary exact-match anchor');
check(strpos($main, "'placement' => 'after-static-stores-link'") !== false, 'exact footer placement strategy is exposed to JavaScript');
check(strpos($main, '$output .= $this->renderFooterAttribution') !== false, 'displayFooter still renders the shared Joobox attribution');
check(strpos($main, 'prioritizeDisplayFooterHook') === false, 'obsolete first-position synchronizer removed');
check(strpos($main, 'START TRANSACTION') !== false, 'atomic footer hook reorder');
check(strpos($main, "Tools::getValue('configure') === \$this->name") !== false, 'Back Office configuration CSS loading');
check(strpos($main, 'TranslationCatalogInstaller') !== false, 'same-version packaged translation importer');
check(strpos($main, 'synchronizePackagedTranslations') !== false, 'runtime translation catalogue synchronization');
check(strpos($main, 'jooimWithdrawalAdminTabLabel') !== false, 'localized Back Office tab labels');
check(strpos($main, "'sk' => 'Odstúpenia od zmluvy'") !== false, 'Slovak Back Office tab label');


$statusPresenter = readFileStrict($root . '/src/Service/WithdrawalStatusPresenter.php');
check(strpos($statusPresenter, "'submitted' => 'Withdrawal received'") !== false, 'human status source map');
check(strpos($statusPresenter, "'duplicate' => 'Duplicate submission'") !== false, 'complete status source map');
check(strpos($statusPresenter, 'decorateHistoryRows') !== false, 'localized audit history status labels');
check(strpos($statusPresenter, 'status_code') !== false, 'stable technical status code retained');

$adminList = readFileStrict($root . '/views/templates/admin/list.tpl');
$adminDetail = readFileStrict($root . '/views/templates/admin/detail.tpl');
$adminOrderPanel = readFileStrict($root . '/views/templates/hook/admin-order-panel.tpl');
$frontStatus = readFileStrict($root . '/views/templates/front/status.tpl');
check(strpos($adminList, '$w.status_label') !== false, 'Back Office list uses localized status label');
check(strpos($adminList, '$w.status_code|escape') === false, 'Back Office list does not expose raw status code');
check(strpos($adminDetail, '$withdrawal.status_label') !== false, 'Back Office detail uses localized status label');
check(strpos($adminDetail, '$status.label') !== false, 'status selector uses localized labels');
check(strpos($adminDetail, '$h.old_status_label') !== false && strpos($adminDetail, '$h.new_status_label') !== false, 'audit history uses localized old/new labels');
check(strpos($adminOrderPanel, '$w.status_label') !== false, 'order panel uses localized status label');
check(strpos($frontStatus, '$withdrawal.status_label') !== false, 'customer status page uses localized status label');
check(is_file($root . '/tools/status_label_translations.json'), 'deterministic status translation data');
check(is_file($root . '/tools/build_status_translation_catalogs.py'), 'deterministic status catalogue builder');
check(is_file($root . '/tests/status_localization_smoke.php'), 'status localization regression test');

$invalidExternalTranslationCalls = [];
foreach (new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    static function ($file) use ($root): bool {
        $path = relativePath($root, $file->getPathname());
        return !in_array($path, ['.git', 'docs', 'var/temporary', 'var/documents', 'var/queue'], true)
            && !preg_match('/(?:^|\/)(?:\.env[^\/]*|[^\/]*\.(?:pem|key))$/', $path);
    }
)) as $phpFile) {
    if (!$phpFile->isFile() || strtolower($phpFile->getExtension()) !== 'php') {
        continue;
    }
    $relative = relativePath($root, $phpFile->getPathname());
    if ($relative === 'jooim_withdrawal.php') {
        continue;
    }
    $phpSource = readFileStrict($phpFile->getPathname());
    if (preg_match('/(?:\\$this->module|\\$module)->trans\\s*\\(/', $phpSource) === 1) {
        $invalidExternalTranslationCalls[] = $relative;
    }
}
check(
    $invalidExternalTranslationCalls === [],
    'no external controller or service calls protected ModuleCore::trans() directly'
);

$configXml = readFileStrict($root . '/config.xml');
check(strpos($configXml, '<version><![CDATA[1.1.1]]></version>') !== false, 'config.xml version 1.1.1');
check(strpos($configXml, '<name>jooim_withdrawal</name>') !== false, 'config.xml technical name');

$composerRaw = readFileStrict($root . '/composer.json');
$composer = json_decode($composerRaw, true);
check(is_array($composer) && json_last_error() === JSON_ERROR_NONE, 'composer.json is valid JSON');
check(($composer['name'] ?? '') === 'joo-internet-media/jooim-withdrawal', 'Composer package name');
check(($composer['homepage'] ?? '') === 'https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal', 'Composer homepage repository URL');
check(($composer['support']['source'] ?? '') === 'https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal', 'Composer source repository URL');
check(($composer['require']['php'] ?? '') === '>=8.1', 'Composer minimum PHP 8.1');

$footerAttribution = readFileStrict($root . '/views/templates/hook/footer-attribution.tpl');
check(strpos($footerAttribution, "data-jooim-attribution=\"jooim_withdrawal\"") !== false, 'module-specific attribution marker');
check(strpos($footerAttribution, '.jooim-joobox-attribution') !== false, 'shared JOOIM attribution selector');
check(strpos($footerAttribution, "a[href*=\"joobox.eu\"]") !== false, 'duplicate Joobox attribution detection');
check(strpos($footerAttribution, "s='PrestaShop module by'") !== false, 'English footer attribution source string');
check(substr_count($footerAttribution, 'https://joobox.eu') === 0, 'footer URL supplied by trusted module constant');
check(strpos($footerAttribution, 'removeChild(candidate)') !== false, 'duplicate attribution removes only duplicate blocks');
check(strpos($footerAttribution, 'DOMContentLoaded') !== false, 'attribution deduplication runs after the complete footer DOM exists');
check(strpos($footerAttribution, 'document.currentScript.previousElementSibling') === false, 'attribution deduplication does not depend on script placement');

$footerWithdrawal = readFileStrict($root . '/views/templates/hook/footer_withdrawal_link.tpl');
$frontJs = readFileStrict($root . '/views/js/front.js');
$frontCss = readFileStrict($root . '/views/css/front.css');
$frontJsRevision = readFileStrict($root . '/views/js/front-footer-v4.js');
$frontCssRevision = readFileStrict($root . '/views/css/front-footer-v4.css');
check(strpos($footerWithdrawal, 'jooim_withdrawal_footer_url') !== false, 'footer uses its dedicated localized URL');
check(strpos($footerWithdrawal, 'data-jooim-withdrawal-footer-fallback') !== false, 'footer template identifies the accessible displayFooter fallback');
check(strpos($footerWithdrawal, 'data-jooim-withdrawal-priority="first"') === false, 'obsolete first-position marker removed');
check(strpos($frontJs, 'JOOIM_WITHDRAWAL_FOOTER_NORMALIZER_V4') !== false, 'footer normalizer V4');
check(strpos($frontJs, "'link-static-page-stores-'") !== false, 'footer normalizer targets the stable ps_linklist stores-link id prefix');
check(strpos($frontJs, 'insertImmediatelyAfter(storesItem, item)') !== false, 'footer normalizer inserts the legal link immediately after the stores list item');
check(strpos($frontJs, "document.querySelector('footer#footer')") !== false, 'footer normalizer selects the real #footer before generic footer elements');
check(strpos($frontJs, 'referenceItem.nextElementSibling === item') !== false, 'footer placement is verified as the immediate next list element');
check(strpos($frontJs, "item.setAttribute('data-jooim-withdrawal-footer-item', '1')") !== false, 'footer link is emitted as a normal marked list item');
check(strpos($frontCss, 'order: 2147483647') === false, 'footer CSS no longer forces detached block ordering');
check(strpos($frontCss, 'text-align: center') === false, 'footer link no longer renders as a detached centered full-width row');

check($frontJsRevision === $frontJs, 'revisioned footer JavaScript matches the canonical source');
check($frontCssRevision === $frontCss, 'revisioned footer CSS matches the canonical source');
check(strpos($main, "FRONT_ASSET_BASENAME = 'front-footer-v4'") !== false, 'front asset cache-busting revision is registered');

$verify = readFileStrict($root . '/controllers/front/verify.php');
check(strpos($verify, 'submitVerify') !== false && strpos($verify, '->consume(') !== false, 'magic link consumed only by explicit POST verification');

$identifyTemplate = readFileStrict($root . '/views/templates/front/identify.tpl');
check(strpos($identifyTemplate, '$jooim_identify_url') !== false, 'identify form uses a controller-assigned action URL');
check(strpos($identifyTemplate, '$link->getModuleLink') === false, 'identify template avoids method calls with inline arrays in Smarty');

$frontControllerBase = readFileStrict($root . '/src/Compatibility/FrontControllerBase.php');
check(strpos($frontControllerBase, 'ensureRuntimeSecurity') !== false, 'front controller verifies runtime security initialization');
check(strpos($frontControllerBase, 'function runPage') !== false, 'shared storefront failure guard');
check(strpos($frontControllerBase, 'WDERR-') !== false, 'storefront failure reference');
check(strpos($frontControllerBase, 'PrestaShopLogger::addLog') !== false, 'storefront failure logging');
check(strpos($frontControllerBase, "\$themePageTemplate = 'page.tpl'") !== false, 'public flow inherits the active theme page template');
check(strpos($frontControllerBase, "\$themePageTemplate = 'customer/page.tpl'") !== false, 'signed-in flow can inherit the customer-account page template');
check(strpos($frontControllerBase, 'jooim_theme_page_template') !== false, 'theme parent template is assigned to Smarty');

$identifyController = readFileStrict($root . '/controllers/front/identify.php');
$directController = readFileStrict($root . '/controllers/front/direct.php');
check(strpos($identifyController, '$this->runPage(') !== false, 'identify page uses the failure guard');
check(strpos($directController, '$this->runPage(') !== false, 'direct page uses the failure guard');

$submitController = readFileStrict($root . '/controllers/front/submit.php');
$directSubmitController = readFileStrict($root . '/controllers/front/directsubmit.php');
foreach (['submit' => $submitController, 'directsubmit' => $directSubmitController] as $controllerName => $actionController) {
    $postProcessPosition = strpos($actionController, 'public function postProcess()');
    $initContentPosition = strpos($actionController, 'public function initContent()');
    check($postProcessPosition !== false, $controllerName . ' action processes POST in postProcess');
    check($initContentPosition !== false && $postProcessPosition < $initContentPosition, $controllerName . ' action runs before content/header rendering');
    check(strpos($actionController, '$this->displayHeader(false);') !== false, $controllerName . ' action disables storefront header');
    check(strpos($actionController, '$this->displayFooter(false);') !== false, $controllerName . ' action disables storefront footer');
    check(strpos($actionController, '$this->displayHeaderJavaScript(false);') !== false, $controllerName . ' action disables storefront header JavaScript');
    check(strpos($actionController, 'JOOIM_WITHDRAWAL_POST_REDIRECT_GET_V3') !== false, $controllerName . ' action carries PRG lifecycle marker');
    check(strpos($actionController, '$this->setRedirectAfter(') !== false, $controllerName . ' action schedules redirect before displayHeader');
    check(strpos($actionController, '$this->runPage(') === false, $controllerName . ' action never initializes theme content before commit');
    check(strpos($actionController, 'parent::initContent()') === false, $controllerName . ' action never invokes displayHeader');
    check(strpos($actionController, 'reportFrontFailure(') !== false, $controllerName . ' action logs failure without rendering a broken theme');
    check(strpos($actionController, 'submissionFailureUrl(') !== false, $controllerName . ' action redirects to a normal GET error page');
}
check(strpos($frontControllerBase, 'function reportFrontFailure') !== false, 'non-rendering storefront failure reporter');
check(strpos($frontControllerBase, 'function submissionFailureUrl') !== false, 'safe post-submit failure redirect builder');
check(strpos($frontControllerBase, 'function submissionErrorMessage') !== false, 'safe redirected submission error resolver');
check(strpos($frontControllerBase, "return 'EXT';") !== false, 'external module failures receive EXT component');
check(strpos($identifyController, '$this->submissionErrorMessage()') !== false, 'identify page renders safe redirected submit errors');
check(strpos($directController, '\'submission_error\' => $this->submissionErrorMessage()') !== false, 'direct page renders safe redirected submit errors');
$directTemplate = readFileStrict($root . '/views/templates/front/direct.tpl');
check(strpos($directTemplate, '$submission_error') !== false, 'direct template displays safe redirected submit errors');

$csrfService = readFileStrict($root . '/src/Security/CsrfService.php');
check(strpos($csrfService, 'jooim_withdrawal_csrf') !== false, 'dedicated CSRF cookie name');
check(strpos($csrfService, '$this->context->cookie') === false, 'CSRF does not enlarge the PrestaShop main cookie');
check(strpos($csrfService, "'httponly' => true") !== false, 'CSRF cookie is HttpOnly');
check(strpos($csrfService, "'samesite' => 'Lax'") !== false, 'CSRF cookie is SameSite=Lax');
check(strpos($csrfService, "'secure' => " . '$this->isSecureRequest()') !== false, 'CSRF cookie follows HTTPS state');
check(strpos($csrfService, "'s' => " . '$this->shopId()') !== false, 'CSRF token is bound to the shop');

$tokenService = readFileStrict($root . '/src/Security/TokenService.php');
check(strpos($tokenService, 'updateGlobalValue') !== false, 'missing module secret can be persisted globally');
check(strpos($tokenService, "jooim_withdrawal:module-secret:v1") !== false, 'stable installation-scoped secret fallback');

$frontControllers = glob($root . '/controllers/front/*.php');
sort($frontControllers);
check(count($frontControllers) === 14, 'expected 14 front controllers');
foreach ($frontControllers as $frontController) {
    $text = readFileStrict($frontController);
    check(
        strpos($text, "require_once dirname(__DIR__, 2) . '/autoload.php';") !== false,
        'explicit front-controller autoload: ' . relativePath($root, $frontController)
    );
}

$configureTemplate = readFileStrict($root . '/views/templates/admin/configure.tpl');
check(strpos($configureTemplate, '$current_configure_url') !== false, 'Back Office forms use an explicit configuration URL');
check(strpos($configureTemplate, '$runtime_sync') !== false, 'Back Office displays runtime synchronization state');
check(strpos($configureTemplate, '$language_urls') !== false, 'Back Office displays active-language URLs');
check(strpos($configureTemplate, "s='English fallback'") !== false, 'Back Office identifies missing language catalogues');
check(strpos($configureTemplate, "s='Back Office settings only'") !== false, 'Back Office distinguishes settings-only catalogues');
check(strpos($main, "'admin_translation_available' =>") !== false, 'active-language metadata includes settings-translation availability');
check(strpos($configureTemplate, 'language_url.public_url') !== false, 'Back Office links the order-reference flow for each language');
check(strpos($configureTemplate, 'language_url.direct_url') !== false, 'Back Office links the direct flow for each language');
foreach ([
    'JOOIM_WITHDRAWAL_ENABLED',
    'JOOIM_WITHDRAWAL_DIRECT_FUNCTION',
    'JOOIM_WITHDRAWAL_FOOTER_LINK',
    'JOOIM_WITHDRAWAL_ACCOUNT_LINK',
    'JOOIM_WITHDRAWAL_ORDER_BUTTON',
    'JOOIM_WITHDRAWAL_MERCHANT_NAME',
    'JOOIM_WITHDRAWAL_MERCHANT_ADDRESS',
    'JOOIM_WITHDRAWAL_MERCHANT_ID',
    'JOOIM_WITHDRAWAL_MERCHANT_EMAIL',
    'JOOIM_WITHDRAWAL_MERCHANT_PHONE',
    'JOOIM_WITHDRAWAL_RETURN_ADDRESS',
    'JOOIM_WITHDRAWAL_PRIVACY_URL',
    'JOOIM_WITHDRAWAL_MAGIC_TTL_MINUTES',
    'JOOIM_WITHDRAWAL_SESSION_IDLE_MINUTES',
    'JOOIM_WITHDRAWAL_SESSION_MAX_MINUTES',
    'JOOIM_WITHDRAWAL_RESEND_COOLDOWN_SECONDS',
    'JOOIM_WITHDRAWAL_IP_15M_LIMIT',
    'JOOIM_WITHDRAWAL_IP_DAY_LIMIT',
    'JOOIM_WITHDRAWAL_ORDER_HOUR_LIMIT',
    'JOOIM_WITHDRAWAL_ORDER_DAY_LIMIT',
    'JOOIM_WITHDRAWAL_NOTE_MAX_LENGTH',
    'JOOIM_WITHDRAWAL_ATTACH_PDF',
    'JOOIM_WITHDRAWAL_RETRY_COUNT',
    'JOOIM_WITHDRAWAL_REFERENCE_PREFIX',
] as $configurationField) {
    check(strpos($configureTemplate, 'name="' . $configurationField . '"') !== false, 'documented Back Office field: ' . $configurationField);
}
check(substr_count($configureTemplate, 'class="help-block"') >= 30, 'Back Office settings include purpose and expected-value help text');
check(strpos($configureTemplate, 'type="hidden" name="JOOIM_WITHDRAWAL_NOTE_MAX_LENGTH"') === false, 'customer note limit is visible in Back Office');
check(strpos($configureTemplate, 'type="hidden" name="JOOIM_WITHDRAWAL_ATTACH_PDF"') === false, 'PDF acknowledgement setting is visible in Back Office');

$frontTemplates = glob($root . '/views/templates/front/*.tpl') ?: [];
sort($frontTemplates);
check(count($frontTemplates) === 9, 'expected nine storefront templates');
foreach ($frontTemplates as $frontTemplate) {
    $templateSource = readFileStrict($frontTemplate);
    check(
        strpos($templateSource, '{extends file=$jooim_theme_page_template}') === 0,
        'storefront template inherits active theme: ' . relativePath($root, $frontTemplate)
    );
    check(strpos($templateSource, "{block name='page_content'}") !== false, 'theme page-content block: ' . relativePath($root, $frontTemplate));
}

$directReview = readFileStrict($root . '/views/templates/front/direct-review.tpl');
check(strpos($directReview, 'Confirm withdrawal') !== false, 'unambiguous final direct action');

$sql = readFileStrict($root . '/sql/install.sql');
foreach ([
    'jooim_withdrawal`',
    'jooim_withdrawal_item',
    'jooim_withdrawal_access_request',
    'jooim_withdrawal_guest_session',
    'jooim_withdrawal_draft',
    'jooim_withdrawal_history',
    'jooim_withdrawal_document',
    'jooim_withdrawal_mail',
    'jooim_withdrawal_order_identity',
    'jooim_withdrawal_text_version',
    'jooim_withdrawal_event_outbox',
    'jooim_withdrawal_sequence',
    'jooim_withdrawal_refund',
] as $table) {
    check(strpos($sql, $table) !== false, 'SQL table: ' . $table);
}
check(strpos($sql, 'UNIQUE KEY `uniq_withdrawal_source_draft`') !== false, 'database idempotency unique key');

$phpFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    static function ($file) use ($root): bool {
        $path = relativePath($root, $file->getPathname());
        return !in_array($path, ['.git', 'docs', 'var/temporary', 'var/documents', 'var/queue'], true)
            && !preg_match('/(?:^|\/)(?:\.env[^\/]*|[^\/]*\.(?:pem|key))$/', $path);
    }
));
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
        $phpFiles[] = $file->getPathname();
    }
}
sort($phpFiles);
check(count($phpFiles) >= 45, 'expected module PHP surface including regression tests');
foreach ($phpFiles as $file) {
    $output = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    check($code === 0, 'PHP lint: ' . relativePath($root, $file));
}

// Keep additional runtime regressions in the existing CI/release validation entry point.
foreach (['configuration_validation_smoke.php', 'recovery_smoke.php', 'admin_transaction_smoke.php'] as $regression) {
    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/' . $regression) . ' 2>&1', $output, $code);
    check($code === 0, 'runtime regression: ' . $regression . ($code ? ' / ' . implode(' ', $output) : ''));
}

$mailCount = count(glob($root . '/mails/{sk,cs,en,de,fr,pl}/*.{html,txt}', GLOB_BRACE));
check($mailCount === 96, '96 localized mail template files');

$locales = ['en-US', 'sk-SK', 'cs-CZ', 'de-DE', 'fr-FR', 'pl-PL'];
$domains = ['Admin', 'Shop', 'Email', 'Pdf', 'Validation'];
$xlfFiles = glob($root . '/translations/*/*.xlf');
sort($xlfFiles);
$localeManifest = json_decode(readFileStrict($root . '/tools/locales.json'), true);
check(count($xlfFiles) === count($localeManifest) * 5, 'five complete catalogues for every supported locale');

$criticalTranslations = [
    'Joobox support for this module',
    'Get Joobox support',
    'Update check',
    'PrestaShop module by',
];

foreach ($locales as $locale) {
    foreach ($domains as $domain) {
        $path = $root . '/translations/' . $locale . '/ModulesJooimwithdrawal' . $domain . '.' . $locale . '.xlf';
        check(is_file($path), 'XLIFF catalogue: ' . $locale . ' / ' . $domain);
        if (!is_file($path)) {
            continue;
        }
        $xml = readFileStrict($path);
        check(strpos($xml, '<xliff') !== false && strpos($xml, '</xliff>') !== false, 'XLIFF structure: ' . relativePath($root, $path));
        check(strpos($xml, 'source-language="en-US"') !== false, 'English XLIFF source language: ' . relativePath($root, $path));
        check(strpos($xml, 'target-language="' . $locale . '"') !== false, 'XLIFF target locale: ' . relativePath($root, $path));

        $units = parseXlfUnits($xml);
        check(count($units) > 0, 'XLIFF contains translation units: ' . relativePath($root, $path));
        foreach ($units as $index => $unit) {
            check($unit['target'] !== '', 'non-empty XLIFF target: ' . relativePath($root, $path) . ' #' . ($index + 1));
            check(placeholders($unit['source']) === placeholders($unit['target']), 'placeholder parity: ' . relativePath($root, $path) . ' #' . ($index + 1));
        }
    }

    $adminPath = $root . '/translations/' . $locale . '/ModulesJooimwithdrawalAdmin.' . $locale . '.xlf';
    $adminUnits = parseXlfUnits(readFileStrict($adminPath));
    $adminLookup = [];
    foreach ($adminUnits as $unit) {
        $adminLookup[$unit['source']] = $unit['target'];
    }
    preg_match_all(
        "~\{l\s+s='([^']*)'\s+d='Modules\.Jooimwithdrawal\.Admin'~",
        $configureTemplate,
        $configureMatches
    );
    $configureSources = array_values(array_unique(array_map(
        static fn (string $value): string => str_replace("\\'", "'", $value),
        $configureMatches[1] ?? []
    )));
    check(count($configureSources) >= 70, 'configuration template exposes a comprehensive translatable help catalogue (' . $locale . ')');
    foreach ($configureSources as $source) {
        check(isset($adminLookup[$source]), 'configuration translation source exists (' . $locale . '): ' . $source);
        if (isset($adminLookup[$source])) {
            check($adminLookup[$source] !== '', 'configuration translation target is non-empty (' . $locale . '): ' . $source);
            if ($locale !== 'en-US' && $source !== 'JOOIM Withdrawal') {
                check($adminLookup[$source] !== $source, 'configuration help is translated (' . $locale . '): ' . $source);
            }
        }
    }

    $shopPath = $root . '/translations/' . $locale . '/ModulesJooimwithdrawalShop.' . $locale . '.xlf';
    $combinedUnits = array_merge(parseXlfUnits(readFileStrict($adminPath)), parseXlfUnits(readFileStrict($shopPath)));
    $lookup = [];
    foreach ($combinedUnits as $unit) {
        $lookup[$unit['source']] = $unit['target'];
    }
    foreach ($criticalTranslations as $source) {
        check(isset($lookup[$source]), 'critical source exists (' . $locale . '): ' . $source);
        if (isset($lookup[$source])) {
            check($locale === 'en-US' ? $lookup[$source] === $source : $lookup[$source] !== $source, 'critical target translated (' . $locale . '): ' . $source);
        }
    }
    foreach (['Form expired', 'Not found'] as $source) {
        check(isset($lookup[$source]), 'front-office source exists (' . $locale . '): ' . $source);
        if ($locale !== 'en-US' && isset($lookup[$source])) {
            check($lookup[$source] !== $source, 'front-office target translated (' . $locale . '): ' . $source);
        }
    }
}

$adminLocales = [
    'bg-BG', 'cs-CZ', 'da-DK', 'de-DE', 'el-GR', 'en-CA', 'en-GB', 'en-US',
    'es-ES', 'et-EE', 'fi-FI', 'fr-CA', 'fr-FR', 'ga-IE', 'hr-HR', 'hu-HU',
    'it-IT', 'lt-LT', 'lv-LV', 'mt-MT', 'nl-NL', 'pl-PL', 'pt-PT', 'ro-RO',
    'sk-SK', 'sl-SI', 'sv-SE', 'uk-UA',
];
$settingsSources = json_decode(readFileStrict($root . '/tools/admin_settings_sources.json'), true);
check(is_array($settingsSources) && count($settingsSources) === 120, 'authoritative list contains 120 Back Office settings strings');
foreach ($adminLocales as $locale) {
    $adminPath = $root . '/translations/' . $locale . '/ModulesJooimwithdrawalAdmin.' . $locale . '.xlf';
    check(is_file($adminPath), 'Back Office settings catalogue: ' . $locale);
    if (!is_file($adminPath) || !is_array($settingsSources)) {
        continue;
    }
    $lookup = [];
    foreach (parseXlfUnits(readFileStrict($adminPath)) as $unit) {
        $lookup[$unit['source']] = $unit['target'];
    }
    foreach ($settingsSources as $source) {
        check(isset($lookup[$source]) && $lookup[$source] !== '', 'settings translation coverage (' . $locale . '): ' . $source);
        if (isset($lookup[$source])) {
            check(placeholders($source) === placeholders($lookup[$source]), 'settings placeholder parity (' . $locale . '): ' . $source);
        }
    }
}

$readme = readFileStrict($root . '/README.md');
foreach (['README.sk.md', 'README.cs.md', 'README.de.md', 'README.fr.md', 'README.pl.md'] as $localizedReadme) {
    check(strpos($readme, $localizedReadme) !== false, 'English README links to ' . $localizedReadme);
}
check(strpos($readme, 'Status: **stable public release**') !== false, 'README identifies 1.0.0 as the stable public release');
check(strpos($readme, 'Joobox support for this module') !== false, 'README documents Joobox support');
check(strpos($readme, 'Joobox footer attribution') !== false, 'README documents shared footer attribution');
check(strpos($readme, 'GitHub update check') !== false, 'README documents update checker');
check(strpos($readme, 'English is the source language') !== false, 'README documents English source language');

foreach (['sk', 'cs', 'de', 'fr', 'pl'] as $language) {
    $localized = readFileStrict($root . '/README.' . $language . '.md');
    check(strpos($localized, '[English](README.md)') !== false, 'localized README links back to English: ' . $language);
    check(strpos($localized, '1.0.0') !== false, 'localized README preserves development version: ' . $language);
}

check(strpos($readme, 'JOO-INTERNET-MEDIA-LTD/jooim_withdrawal') !== false, 'README targets the official repository');
check(strpos($readme, 'jooim_withdrawal-1.1.1.zip') !== false, 'README names the installation release asset');
check(strpos($readme, 'docs/') === false, 'public README does not link local-only documentation');

$nullableInsertFiles = [
    'controllers/front/directreview.php',
    'src/Service/GuestAccessService.php',
    'src/Service/WithdrawalDraftService.php',
    'src/Service/WithdrawalMailService.php',
    'src/Service/WithdrawalSubmissionService.php',
];
foreach ($nullableInsertFiles as $file) {
    $text = readFileStrict($root . '/' . $file);
    check(strpos($text, '], true);') !== false, 'NULL-preserving Db::insert usage: ' . $file);
}

$logo = readFileStrict($root . '/logo.png');
$logoCopy = readFileStrict($root . '/views/img/logo.png');
check(substr($logo, 0, 8) === "\x89PNG\r\n\x1a\n", 'root logo is PNG');
check(hash('sha256', $logo) === hash('sha256', $logoCopy), 'root and views logo copies match');

$secretPatterns = [
    implode('', ['-----BEGIN ', 'PRIVATE KEY-----']),
    implode('', ['-----BEGIN RSA ', 'PRIVATE KEY-----']),
    implode('', ['gh', 'p_']),
    implode('', ['github_', 'pat_']),
];
$secretFindings = [];
foreach (new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    static function ($file) use ($root): bool {
        $path = relativePath($root, $file->getPathname());
        return !in_array($path, ['.git', 'docs', 'var/temporary', 'var/documents', 'var/queue'], true)
            && !preg_match('/(?:^|\/)(?:\.env[^\/]*|[^\/]*\.(?:pem|key))$/', $path);
    }
)) as $file) {
    if (!$file->isFile() || $file->getSize() > 2 * 1024 * 1024) {
        continue;
    }
    $content = readFileStrict($file->getPathname());
    foreach ($secretPatterns as $pattern) {
        if (strpos($content, $pattern) !== false) {
            $secretFindings[] = relativePath($root, $file->getPathname()) . ': ' . $pattern;
        }
    }
}
check($secretFindings === [], 'no embedded private keys or GitHub tokens');

printf("PASS: %d\n", count($pass));
if ($failures) {
    printf("FAIL: %d\n", count($failures));
    foreach ($failures as $failure) {
        echo " - {$failure}\n";
    }
    exit(1);
}
echo "FAIL: 0\n";
