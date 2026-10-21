<?php
/**
 * JOOIM Withdrawal – Online Contract Withdrawal
 *
 * @author JOO INTERNET MEDIA LTD
 * @license MIT
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/autoload.php'; // JOOIM_WITHDRAWAL_SAFE_AUTOLOAD


if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'JooInternetMedia\\PrestaShop\\Module\\JooimWithdrawal\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\ModuleConfig;
use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\OrderIdentityService;
use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\ServiceContainer;
use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\TranslationCatalogInstaller;
use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\WithdrawalStatusPresenter;

class Jooim_Withdrawal extends Module
{
    public const VERSION = '1.1.1';
    private const FRONT_ASSET_BASENAME = 'front-footer-v4';
    public const GITHUB_REPOSITORY_URL = 'https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal';
    public const GITHUB_RELEASES_URL = 'https://github.com/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal/releases/latest';
    public const JOOBOX_HOME_URL = 'https://joobox.eu';
    private const GITHUB_API_LATEST_RELEASE_URL = 'https://api.github.com/repos/JOO-INTERNET-MEDIA-LTD/jooim_withdrawal/releases/latest';
    private const CONFIG_GITHUB_LATEST_VERSION = 'JOOIM_WITHDRAWAL_GITHUB_LATEST_VERSION';
    private const CONFIG_GITHUB_CHECKED_AT = 'JOOIM_WITHDRAWAL_GITHUB_CHECKED_AT';
    private const CONFIG_GITHUB_ERROR = 'JOOIM_WITHDRAWAL_GITHUB_ERROR';
    public const TRANS_ADMIN = 'Modules.Jooimwithdrawal.Admin';
    public const TRANS_SHOP = 'Modules.Jooimwithdrawal.Shop';
    public const TRANS_EMAIL = 'Modules.Jooimwithdrawal.Email';
    public const TRANS_PDF = 'Modules.Jooimwithdrawal.Pdf';
    public const TRANS_VALIDATION = 'Modules.Jooimwithdrawal.Validation';

    private bool $runtimeSecurityChecked = false;
    private bool $runtimeSecurityReady = false;
    private bool $packagedTranslationsChanged = false;

    public function __construct()
    {
        $this->name = 'jooim_withdrawal';
        $this->tab = 'administration';
        $this->version = self::VERSION;
        $this->author = 'JOO INTERNET MEDIA LTD';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => '9.99.99'];

        parent::__construct();

        $this->displayName = $this->trans(
            'Customer Right of Withdrawal',
            [],
            self::TRANS_ADMIN
        );
        $this->description = $this->trans(
            'Secure online exercise and administration of the consumer right of withdrawal.',
            [],
            self::TRANS_ADMIN
        );
    }


    /**
     * Public translation bridge for module collaborators.
     *
     * ModuleCore::trans() is protected in PrestaShop 8. Controllers and
     * namespaced services therefore must call this public bridge instead of
     * invoking $module->trans() directly.
     *
     * @param mixed $id
     * @param array<string,mixed> $parameters
     * @param mixed $domain
     * @param mixed $locale
     */
    public function jooimTranslate($id, array $parameters = [], $domain = null, $locale = null): string
    {
        return (string) $this->trans($id, $parameters, $domain, $locale);
    }

    public function jooimWithdrawalInstallCore(): bool
    {
        if (PHP_VERSION_ID < 80100) {
            $this->_errors[] = $this->trans('PHP 8.1 or newer is required.', [], self::TRANS_ADMIN);
            return false;
        }
        if (version_compare(_PS_VERSION_, '8.0.0', '<') || version_compare(_PS_VERSION_, '10.0.0', '>=')) {
            $this->_errors[] = $this->trans('Supported PrestaShop versions are 8.x and 9.x.', [], self::TRANS_ADMIN);
            return false;
        }

        if (!parent::install()) {
            return false;
        }

        try {
            if (!$this->installDatabase()) {
                throw new RuntimeException('Database installation failed.');
            }
            if (!$this->installConfiguration()) {
                throw new RuntimeException('Configuration installation failed.');
            }
            $translationSync = $this->synchronizePackagedTranslations();
            if (!$translationSync['ok']) {
                throw new RuntimeException('Packaged translation installation failed.');
            }
            if (!$this->registerRequiredHooks()) {
                throw new RuntimeException('Hook registration failed.');
            }
            if (!$this->synchronizeFooterAttributionHook()) {
                throw new RuntimeException('Footer hook prioritization failed.');
            }
            if (!$this->installTabs()) {
                throw new RuntimeException('Back-office tab installation failed.');
            }
            ServiceContainer::get($this)->storage()->ensureStorage();
        } catch (Throwable $e) {
            $this->_errors[] = $e->getMessage();
            $this->rollbackInstall();
            parent::uninstall();
            return false;
        }

        return true;
    }

    /** JOOIM_WITHDRAWAL_INSTALL_WRAPPER */
    public function install()
    {
        if (!$this->jooimWithdrawalInstallCore()) {
            return false;
        }
    
        try {
            $this->jooimWithdrawalEnsureRuntimeIntegration();
        } catch (Throwable $e) {
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog('[jooim_withdrawal] Install integration: ' . $e->getMessage(), 3);
            }
            return false;
        }
    
        return true;
    }

    public function jooimWithdrawalUpgrade(): bool
    {
        try {
            $this->jooimWithdrawalEnsureRuntimeIntegration();
            return (bool) $this->synchronizeRuntimeState()['ok'];
        } catch (Throwable $exception) {
            $this->logRuntimeProblem('Module runtime upgrade failed.');
            return false;
        }
    }

    /**
     * Intentionally preserves legally relevant data. Destructive purge is a separate explicit action.
     */
    public function uninstall(): bool
    {
        $this->uninstallTabs();
        $this->removeOperationalConfiguration();
        return parent::uninstall();
    }

    private function installDatabase(): bool
    {
        $sql = file_get_contents(__DIR__ . '/sql/install.sql');
        if ($sql === false) {
            return false;
        }
        $sql = str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);
        foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement !== '' && !Db::getInstance()->execute($statement)) {
                return false;
            }
        }
        return true;
    }

    private function rollbackInstall(): void
    {
        // A failed reinstall must never drop retained legal records. Leave
        // idempotently created tables available for a subsequent repair.
        $this->uninstallTabs();
    }

    /**
     * @return array<string,string>
     */
    private function configurationDefaults(): array
    {
        return [
            'JOOIM_WITHDRAWAL_ENABLED' => '1',
            'JOOIM_WITHDRAWAL_FOOTER_LINK' => '1',
            'JOOIM_WITHDRAWAL_ACCOUNT_LINK' => '1',
            'JOOIM_WITHDRAWAL_ORDER_BUTTON' => '1',
            'JOOIM_WITHDRAWAL_DIRECT_FUNCTION' => '1',
            'JOOIM_WITHDRAWAL_REFERENCE_PREFIX' => 'WD',
            'JOOIM_WITHDRAWAL_MAGIC_TTL_MINUTES' => '30',
            'JOOIM_WITHDRAWAL_SESSION_IDLE_MINUTES' => '30',
            'JOOIM_WITHDRAWAL_SESSION_MAX_MINUTES' => '120',
            'JOOIM_WITHDRAWAL_RESEND_COOLDOWN_SECONDS' => '60',
            'JOOIM_WITHDRAWAL_IP_15M_LIMIT' => '5',
            'JOOIM_WITHDRAWAL_IP_DAY_LIMIT' => '20',
            'JOOIM_WITHDRAWAL_ORDER_HOUR_LIMIT' => '3',
            'JOOIM_WITHDRAWAL_ORDER_DAY_LIMIT' => '10',
            'JOOIM_WITHDRAWAL_NOTE_MAX_LENGTH' => '2000',
            'JOOIM_WITHDRAWAL_ATTACH_PDF' => '1',
            'JOOIM_WITHDRAWAL_RETRY_COUNT' => '3',
            'JOOIM_WITHDRAWAL_PRIVACY_URL' => '',
            'JOOIM_WITHDRAWAL_MERCHANT_NAME' => (string) Configuration::get('PS_SHOP_NAME'),
            'JOOIM_WITHDRAWAL_MERCHANT_ADDRESS' => '',
            'JOOIM_WITHDRAWAL_MERCHANT_ID' => '',
            'JOOIM_WITHDRAWAL_MERCHANT_EMAIL' => (string) Configuration::get('PS_SHOP_EMAIL'),
            'JOOIM_WITHDRAWAL_MERCHANT_PHONE' => '',
            'JOOIM_WITHDRAWAL_RETURN_ADDRESS' => '',
        ];
    }

    private function installConfiguration(): bool
    {
        $config = new ModuleConfig($this->context);
        $ok = true;

        foreach ($this->configurationDefaults() as $key => $value) {
            if (!$config->has($key)) {
                $ok = $config->set($key, $value) && $ok;
            }
        }

        $securityReady = $this->ensureRuntimeSecurity();
        $cronReady = false;
        try {
            $cronReady = $this->ensureSecret('JOOIM_WITHDRAWAL_CRON_TOKEN', 24);
        } catch (Throwable $e) {
            $this->logRuntimeProblem('Cron token initialization failed: ' . $e->getMessage());
        }

        return $securityReady && $cronReady && $ok;
    }

    private function ensureSecret(string $key, int $bytes): bool
    {
        // Never rotate an existing effective value. Old test builds may have stored
        // the secret in a shop context; replacing files under the same 1.0.0 version
        // must not invalidate already issued hashes, sessions or documents.
        if ((string) Configuration::get($key) !== '') {
            return true;
        }

        $secret = bin2hex(random_bytes($bytes));
        $updated = method_exists(Configuration::class, 'updateGlobalValue')
            ? Configuration::updateGlobalValue($key, $secret)
            : Configuration::updateValue($key, $secret, false, 0, 0);

        // An empty shop-specific value can mask a newly created global value.
        // In that exceptional case, repair only the active shop context.
        if ((string) Configuration::get($key) === '') {
            $updated = Configuration::updateValue($key, $secret) && $updated;
        }

        return (bool) $updated && (string) Configuration::get($key) !== '';
    }

    /**
     * Ensures that front-office cryptographic services can be constructed even
     * when the merchant replaced files with another test build of version 1.0.0.
     */
    public function ensureRuntimeSecurity(): bool
    {
        if ($this->runtimeSecurityChecked) {
            return $this->runtimeSecurityReady;
        }

        $this->runtimeSecurityChecked = true;
        try {
            $this->runtimeSecurityReady = $this->ensureSecret('JOOIM_WITHDRAWAL_SECRET', 32);
        } catch (Throwable $e) {
            $this->runtimeSecurityReady = false;
            $this->logRuntimeProblem('Security initialization failed: ' . $e->getMessage());
        }

        // Read-only or partially migrated test installations can still use a
        // deterministic installation-scoped fallback. TokenService derives the
        // same value from PrestaShop's private cookie key and never exposes it.
        if (!$this->runtimeSecurityReady && defined('_COOKIE_KEY_') && (string) _COOKIE_KEY_ !== '') {
            $this->runtimeSecurityReady = true;
        }

        return $this->runtimeSecurityReady;
    }

    /**
     * @return array{ok:bool,errors:array<int,string>}
     */
    private function synchronizeRuntimeState(): array
    {
        $errors = [];
        $steps = [
            'database schema' => fn (): bool => $this->installDatabase(),
            'configuration' => fn (): bool => $this->installConfiguration(),
            'translations' => fn (): bool => $this->synchronizePackagedTranslations()['ok'],
            'hooks' => fn (): bool => $this->registerRequiredHooks(),
            'footer attribution hook' => fn (): bool => $this->synchronizeFooterAttributionHook(),
            'Back Office tab' => fn (): bool => $this->installTabs(),
            'document storage' => function (): bool {
                ServiceContainer::get($this)->storage()->ensureStorage();
                return true;
            },
        ];

        foreach ($steps as $label => $step) {
            try {
                if (!$step()) {
                    $errors[] = $label;
                }
            } catch (Throwable $e) {
                $errors[] = $label;
                $this->logRuntimeProblem($label . ': ' . $e->getMessage());
            }
        }

        return ['ok' => $errors === [], 'errors' => $errors];
    }

    /**
     * @return array{ok:bool,changed:bool,inserted:int,updated:int,skipped:int,errors:array<int,string>}
     */
    private function synchronizePackagedTranslations(): array
    {
        $installer = new TranslationCatalogInstaller(__DIR__);
        $result = $installer->synchronize();
        $this->packagedTranslationsChanged = $this->packagedTranslationsChanged || $result['changed'];
        foreach ($result['errors'] as $error) {
            $this->logRuntimeProblem('Translation synchronization: ' . $error);
        }

        return $result;
    }

    private function logRuntimeProblem(string $message): void
    {
        if (class_exists('PrestaShopLogger')) {
            PrestaShopLogger::addLog(
                '[jooim_withdrawal] ' . $message,
                3,
                null,
                'Module',
                (int) $this->id,
                true
            );
        }
    }

    private function removeOperationalConfiguration(): void
    {
        $keys = [
            'JOOIM_WITHDRAWAL_ENABLED', 'JOOIM_WITHDRAWAL_FOOTER_LINK',
            'JOOIM_WITHDRAWAL_ACCOUNT_LINK', 'JOOIM_WITHDRAWAL_ORDER_BUTTON',
            'JOOIM_WITHDRAWAL_DIRECT_FUNCTION',
            self::CONFIG_GITHUB_LATEST_VERSION,
            self::CONFIG_GITHUB_CHECKED_AT,
            self::CONFIG_GITHUB_ERROR,
        ];
        foreach ($keys as $key) {
            Configuration::deleteByName($key);
        }
    }

    private function registerRequiredHooks(): bool
    {
        $hooks = [
            'moduleRoutes', 'actionAfterLoadRoutes', 'displayHeader', 'displayFooter',
            'displayCustomerAccount', 'displayOrderDetail',
            'displayAdminOrderSide', 'displayAdminOrderMain', 'actionValidateOrder',
            'actionObjectOrderAddAfter', 'actionOrderStatusPostUpdate',
            'actionFrontControllerSetMedia', 'actionAdminControllerSetMedia',
        ];
        foreach ($hooks as $hook) {
            if (!$this->registerHookIfNeeded($hook)) {
                return false;
            }
        }
        return true;
    }

    private function registerHookIfNeeded(string $hookName): bool
    {
        if (method_exists($this, 'isRegisteredInHook') && $this->isRegisteredInHook($hookName)) {
            return true;
        }

        return (bool) $this->registerHook($hookName);
    }

    private function installTabs(): bool
    {
        $existingId = (int) Tab::getIdFromClassName('AdminJooimWithdrawal');
        if ($existingId > 0) {
            $this->jooimWithdrawalSynchronizeAdminTabNames();
            return true;
        }

        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminJooimWithdrawal';
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentOrders');
        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = $this->jooimWithdrawalAdminTabLabel(
                (string) ($language['iso_code'] ?? 'en')
            );
        }

        return (bool) $tab->add();
    }

    private function uninstallTabs(): void
    {
        $id = (int) Tab::getIdFromClassName('AdminJooimWithdrawal');
        if ($id > 0) {
            $tab = new Tab($id);
            $tab->delete();
        }
    }

    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    public function getContent(): string
    {
        // JOOIM_WITHDRAWAL_RUNTIME_SYNC
        try {
            $this->jooimWithdrawalEnsureRuntimeIntegration();
        } catch (Throwable $e) {
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog('[jooim_withdrawal] Runtime synchronization: ' . $e->getMessage(), 3);
            }
        }
        $output = '';
        $runtimeSync = $this->synchronizeRuntimeState();

        // The translator may have loaded the old SQL catalogue in the module
        // constructor. After importing packaged translations, perform one safe
        // GET redirect so the same configuration page is rendered with a fresh
        // Symfony translation catalogue. Never redirect a submitted form.
        if ($this->packagedTranslationsChanged
            && !Tools::getValue('jooimTranslationsReady')
            && !Tools::isSubmit('submitJooimWithdrawal')
            && !Tools::isSubmit('purgeJooimWithdrawalData')
        ) {
            Tools::redirectAdmin($this->context->link->getAdminLink(
                'AdminModules',
                true,
                [],
                [
                    'configure' => $this->name,
                    'module_name' => $this->name,
                    'jooimTranslationsReady' => 1,
                ]
            ));
        }

        $config = new ModuleConfig($this->context);

        if (!$runtimeSync['ok']) {
            $output .= $this->displayError($this->trans(
                'Some runtime requirements could not be synchronized. Review the PrestaShop logs before public use.',
                [],
                self::TRANS_ADMIN
            ));
        }

        if (Tools::isSubmit('submitJooimWithdrawal')) {
            $fields = [
                'JOOIM_WITHDRAWAL_ENABLED', 'JOOIM_WITHDRAWAL_FOOTER_LINK',
                'JOOIM_WITHDRAWAL_ACCOUNT_LINK', 'JOOIM_WITHDRAWAL_ORDER_BUTTON',
                'JOOIM_WITHDRAWAL_DIRECT_FUNCTION', 'JOOIM_WITHDRAWAL_REFERENCE_PREFIX',
                'JOOIM_WITHDRAWAL_MAGIC_TTL_MINUTES', 'JOOIM_WITHDRAWAL_SESSION_IDLE_MINUTES',
                'JOOIM_WITHDRAWAL_SESSION_MAX_MINUTES', 'JOOIM_WITHDRAWAL_RESEND_COOLDOWN_SECONDS',
                'JOOIM_WITHDRAWAL_IP_15M_LIMIT', 'JOOIM_WITHDRAWAL_IP_DAY_LIMIT',
                'JOOIM_WITHDRAWAL_ORDER_HOUR_LIMIT', 'JOOIM_WITHDRAWAL_ORDER_DAY_LIMIT',
                'JOOIM_WITHDRAWAL_NOTE_MAX_LENGTH', 'JOOIM_WITHDRAWAL_ATTACH_PDF', 'JOOIM_WITHDRAWAL_RETRY_COUNT',
                'JOOIM_WITHDRAWAL_PRIVACY_URL', 'JOOIM_WITHDRAWAL_MERCHANT_NAME',
                'JOOIM_WITHDRAWAL_MERCHANT_ADDRESS', 'JOOIM_WITHDRAWAL_MERCHANT_ID',
                'JOOIM_WITHDRAWAL_MERCHANT_EMAIL', 'JOOIM_WITHDRAWAL_MERCHANT_PHONE',
                'JOOIM_WITHDRAWAL_RETURN_ADDRESS',
            ];
            $values = [];
            foreach ($fields as $key) {
                $values[$key] = trim((string) Tools::getValue($key, ''));
            }
            $validator = new \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\ConfigurationValidator();
            if (!$validator->isValid($values)) {
                $output .= $this->displayError($this->trans('Invalid settings. Check the entered values.', [], self::TRANS_ADMIN));
            } else {
                $saved = $config->saveAll($values);
                $output .= $saved
                    ? $this->displayConfirmation($this->trans('Settings saved.', [], self::TRANS_ADMIN))
                    : $this->displayError($this->trans('Invalid settings. Check the entered values.', [], self::TRANS_ADMIN));
            }
        }

        if (Tools::isSubmit('purgeJooimWithdrawalData')) {
            if ((string) Tools::getValue('purge_confirmation') === 'DELETE') {
                $sql = file_get_contents(__DIR__ . '/sql/uninstall.sql');
                $sql = str_replace('PREFIX_', _DB_PREFIX_, (string) $sql);
                foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
                    if (trim($statement) !== '') {
                        Db::getInstance()->execute($statement);
                    }
                }
                $output .= $this->displayConfirmation($this->trans('Module data was permanently deleted.', [], self::TRANS_ADMIN));
            } else {
                $output .= $this->displayError($this->trans('Type DELETE to confirm permanent data deletion.', [], self::TRANS_ADMIN));
            }
        }

        $currentConfigureUrl = AdminController::$currentIndex
            . '&configure=' . rawurlencode($this->name)
            . '&token=' . rawurlencode(Tools::getAdminTokenLite('AdminModules'));

        $baseLink = $this->context->link->getBaseLink(
            isset($this->context->shop->id) ? (int) $this->context->shop->id : null,
            true
        );
        $entryBaseUrl = rtrim((string) $baseLink, '/') . '/index.php?fc=module&module=' . rawurlencode($this->name)
            . '&controller=entry&target=';

        $this->context->smarty->assign([
            'config' => $config->all(),
            'runtime_sync' => $runtimeSync,
            'current_configure_url' => $currentConfigureUrl,
            'module_admin_url' => $this->context->link->getAdminLink('AdminJooimWithdrawal'),
            'public_url' => $this->context->link->getModuleLink($this->name, 'identify', [], true),
            'direct_url' => $this->context->link->getModuleLink($this->name, 'direct', [], true),
            'universal_public_url' => $entryBaseUrl . 'identify',
            'universal_direct_url' => $entryBaseUrl . 'direct',
            'language_urls' => $this->getActiveLanguageUrls(),
            'maintenance_url' => $this->context->link->getModuleLink($this->name, 'maintenance', [
                'token' => (string) Configuration::get('JOOIM_WITHDRAWAL_CRON_TOKEN'),
            ], true),
        ]);
        return $output
            . $this->renderJooboxSupportPanel()
            . $this->renderUpdateCheckPanel()
            . $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    protected function synchronizeFooterAttributionHook(): bool
    {
        // Earlier 1.0.0 test builds tried to render through theme-specific
        // hooks such as displayFooterPart1. On the production theme that hook
        // is emitted inside the page-content footer, above the real #footer.
        // Keep those methods as no-output compatibility shims and remove their
        // registrations. The standard displayFooter output is the no-JS
        // fallback; JavaScript then relocates the legal link into the existing
        // ps_linklist <ul>, immediately after the static stores link.
        foreach (['displayFooterBefore', 'displayFooterAfter', 'displayBeforeBodyClosingTag', 'displayFooterPart1'] as $legacyHook) {
            if (method_exists($this, 'isRegisteredInHook') && $this->isRegisteredInHook($legacyHook)) {
                $this->unregisterHook($legacyHook);
            }
        }

        if (!$this->registerHookIfNeeded('displayFooter')) {
            return false;
        }

        return $this->moveFooterLinkHookToEnd('displayFooter');
    }

    /**
     * Places the standard displayFooter fallback and Joobox attribution after
     * other modules. The normal JavaScript path removes the visual fallback and
     * inserts the legal link directly after the ps_linklist Stores item.
     */
    private function moveFooterLinkHookToEnd(string $hookName): bool
    {
        $idHook = (int) Hook::getIdByName($hookName);
        if ($idHook <= 0 || (int) $this->id <= 0) {
            return false;
        }

        $shopIds = array_map('intval', (array) Shop::getContextListShopID());
        if ($shopIds === [] && isset($this->context->shop->id)) {
            $shopIds = [(int) $this->context->shop->id];
        }

        $db = Db::getInstance();
        foreach (array_values(array_unique($shopIds)) as $shopId) {
            if ($shopId <= 0) {
                continue;
            }

            $rows = $db->executeS(
                'SELECT `id_module`, `position`'
                . ' FROM `' . _DB_PREFIX_ . 'hook_module`'
                . ' WHERE `id_hook` = ' . $idHook
                . ' AND `id_shop` = ' . $shopId
                . ' ORDER BY `position` ASC, `id_module` ASC'
            );
            if (!is_array($rows) || $rows === []) {
                return false;
            }

            $moduleIds = array_map('intval', array_column($rows, 'id_module'));
            $currentIndex = array_search((int) $this->id, $moduleIds, true);
            if ($currentIndex === false) {
                return false;
            }
            if ($currentIndex === count($moduleIds) - 1) {
                continue;
            }

            unset($moduleIds[$currentIndex]);
            $moduleIds[] = (int) $this->id;
            $moduleIds = array_values($moduleIds);

            if (!$db->execute('START TRANSACTION')) {
                return false;
            }

            $ok = $db->execute(
                'UPDATE `' . _DB_PREFIX_ . 'hook_module`'
                . ' SET `position` = `position` + 10000'
                . ' WHERE `id_hook` = ' . $idHook
                . ' AND `id_shop` = ' . $shopId
            );

            foreach ($moduleIds as $index => $moduleId) {
                if (!$ok) {
                    break;
                }
                $ok = $db->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'hook_module`'
                    . ' SET `position` = ' . ((int) $index + 1)
                    . ' WHERE `id_hook` = ' . $idHook
                    . ' AND `id_shop` = ' . $shopId
                    . ' AND `id_module` = ' . (int) $moduleId
                );
            }

            if ($ok) {
                $ok = $db->execute('COMMIT');
                if (!$ok) {
                    $db->execute('ROLLBACK');
                }
            } else {
                $db->execute('ROLLBACK');
            }

            if (!$ok) {
                return false;
            }
        }

        if (class_exists('Cache') && defined('Hook::MODULE_LIST_BY_HOOK_KEY')) {
            Cache::clean(Hook::MODULE_LIST_BY_HOOK_KEY . '*');
        }

        return true;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getActiveLanguageUrls(): array
    {
        $shopId = isset($this->context->shop->id) ? (int) $this->context->shop->id : 0;
        $languages = Language::getLanguages(true, $shopId > 0 ? $shopId : false);
        $rows = [];

        foreach ($languages as $language) {
            $idLang = (int) ($language['id_lang'] ?? 0);
            if ($idLang <= 0) {
                continue;
            }

            $isoCode = Tools::strtolower((string) ($language['iso_code'] ?? ''));
            $locale = (string) ($language['locale'] ?? $language['language_code'] ?? $isoCode);
            $catalogueLocale = str_replace('_', '-', $locale);
            $translationDirectory = __DIR__ . '/translations/' . $catalogueLocale;
            $adminTranslationAvailable = is_file(
                $translationDirectory . '/ModulesJooimwithdrawalAdmin.' . $catalogueLocale . '.xlf'
            );
            $completeTranslationAvailable = true;
            foreach (['Admin', 'Shop', 'Email', 'Pdf', 'Validation'] as $domain) {
                if (!is_file(
                    $translationDirectory . '/ModulesJooimwithdrawal' . $domain . '.' . $catalogueLocale . '.xlf'
                )) {
                    $completeTranslationAvailable = false;
                    break;
                }
            }

            $rows[] = [
                'id_lang' => $idLang,
                'name' => (string) ($language['name'] ?? strtoupper($isoCode)),
                'iso_code' => $isoCode,
                'locale' => $catalogueLocale,
                'public_url' => $this->context->link->getModuleLink(
                    $this->name,
                    'identify',
                    [],
                    true,
                    $idLang,
                    $shopId > 0 ? $shopId : null
                ),
                'direct_url' => $this->context->link->getModuleLink(
                    $this->name,
                    'direct',
                    [],
                    true,
                    $idLang,
                    $shopId > 0 ? $shopId : null
                ),
                'translation_available' => $completeTranslationAvailable,
                'admin_translation_available' => $adminTranslationAvailable,
                'is_english_source' => $isoCode === 'en',
            ];
        }

        return $rows;
    }

    protected function getJooboxSupportUrl(): string
    {
        $language = isset($this->context->language) ? $this->context->language : null;
        $iso = $language && isset($language->iso_code)
            ? Tools::strtolower((string) $language->iso_code)
            : 'en';
        $locale = '';

        if ($language) {
            if (isset($language->locale) && $language->locale) {
                $locale = (string) $language->locale;
            } elseif (isset($language->language_code) && $language->language_code) {
                $locale = (string) $language->language_code;
            }
        }

        $locale = Tools::strtolower(str_replace('_', '-', $locale));

        if ($locale === 'de-at') {
            return 'https://joobox.eu/de-at/unterstutzung';
        }
        if ($locale === 'de-ch') {
            return 'https://joobox.eu/de-ch/unterstutzung';
        }
        if ($locale === 'en-ca') {
            return 'https://joobox.eu/en-ca/support';
        }
        if ($locale === 'fr-ca') {
            return 'https://joobox.eu/fr-ca/support';
        }

        switch ($iso) {
            case 'sk':
                return 'https://joobox.eu/sk-sk/podpora';
            case 'cs':
                return 'https://joobox.eu/cs-cz/podpora';
            case 'de':
                return 'https://joobox.eu/de-de/unterstutzung';
            case 'fr':
                return 'https://joobox.eu/fr-fr/soutien';
            case 'it':
                return 'https://joobox.eu/it-it/supporto';
            case 'pl':
                return 'https://joobox.eu/pl-pl/wsparcie';
            case 'en':
            default:
                return 'https://joobox.eu/en-gb/support';
        }
    }

    protected function renderJooboxSupportPanel(): string
    {
        $supportUrl = $this->getJooboxSupportUrl();

        return '<div class="panel jooim-withdrawal-support-panel">'
            . '<h3><i class="icon-life-ring"></i> '
            . $this->trans('Joobox support for this module', [], self::TRANS_ADMIN)
            . '</h3>'
            . '<p>'
            . $this->trans(
                'This free GitHub module is provided without individual technical support. Installation, configuration, production testing, updates and troubleshooting are available for customers using Joobox PrestaShop Cloud or another paid service explicitly agreed with JOO INTERNET MEDIA LTD.',
                [],
                self::TRANS_ADMIN
            )
            . '</p>'
            . '<p><a class="btn btn-primary" href="'
            . htmlspecialchars($supportUrl, ENT_QUOTES, 'UTF-8')
            . '" target="_blank" rel="noopener">'
            . $this->trans('Get Joobox support', [], self::TRANS_ADMIN)
            . '</a> <a class="btn btn-default" href="'
            . htmlspecialchars(self::GITHUB_REPOSITORY_URL, ENT_QUOTES, 'UTF-8')
            . '" target="_blank" rel="noopener">GitHub</a></p>'
            . '</div>';
    }

    protected function renderUpdateCheckPanel(): string
    {
        $result = $this->getLatestGithubReleaseVersion((bool) Tools::getValue('jooim_refresh_update'));
        $latest = $result['version'] !== ''
            ? $result['version']
            : $this->trans('Unknown', [], self::TRANS_ADMIN);
        $statusClass = 'alert alert-info';
        $statusText = $this->trans(
            'Unable to verify the latest version from GitHub. Check releases manually.',
            [],
            self::TRANS_ADMIN
        );

        if ($result['version'] !== '') {
            if (version_compare($result['version'], $this->version, '>')) {
                $statusClass = 'alert alert-warning';
                $statusText = $this->trans('A new module version is available.', [], self::TRANS_ADMIN);
            } else {
                $statusClass = 'alert alert-success';
                $statusText = $this->trans('Module is up to date.', [], self::TRANS_ADMIN);
            }
        } elseif ($result['error'] !== '') {
            $statusText = $this->trans(
                'GitHub check failed: %error%',
                ['%error%' => $result['error']],
                self::TRANS_ADMIN
            );
        }

        $refreshUrl = AdminController::$currentIndex
            . '&configure=' . rawurlencode($this->name)
            . '&token=' . rawurlencode(Tools::getAdminTokenLite('AdminModules'))
            . '&jooim_refresh_update=1';

        $html = '<div class="panel jooim-withdrawal-update-panel">';
        $html .= '<h3><i class="icon-refresh"></i> '
            . $this->trans('Update check', [], self::TRANS_ADMIN)
            . '</h3>';
        $html .= '<p><strong>'
            . $this->trans('Current module version', [], self::TRANS_ADMIN)
            . ':</strong> ' . htmlspecialchars($this->version, ENT_QUOTES, 'UTF-8') . '</p>';
        $html .= '<p><strong>'
            . $this->trans('Latest version on GitHub', [], self::TRANS_ADMIN)
            . ':</strong> ' . htmlspecialchars($latest, ENT_QUOTES, 'UTF-8') . '</p>';
        if ($result['checked_at'] !== '') {
            $html .= '<p><strong>'
                . $this->trans('Last checked', [], self::TRANS_ADMIN)
                . ':</strong> ' . htmlspecialchars($result['checked_at'], ENT_QUOTES, 'UTF-8') . '</p>';
        }
        $html .= '<div class="' . $statusClass . '"><strong>'
            . $this->trans('Update status', [], self::TRANS_ADMIN)
            . ':</strong> ' . htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') . '</div>';
        $html .= '<p>'
            . $this->trans(
                'For PrestaShop installation, do not use GitHub automatic "Source code ZIP". Use only the installation ZIP file from the release assets.',
                [],
                self::TRANS_ADMIN
            )
            . '</p>';
        $html .= '<p><a class="btn btn-primary" href="'
            . htmlspecialchars(self::GITHUB_RELEASES_URL, ENT_QUOTES, 'UTF-8')
            . '" target="_blank" rel="noopener">'
            . $this->trans('Open GitHub Releases', [], self::TRANS_ADMIN)
            . '</a> <a class="btn btn-default" href="'
            . htmlspecialchars($refreshUrl, ENT_QUOTES, 'UTF-8')
            . '">'
            . $this->trans('Refresh update status', [], self::TRANS_ADMIN)
            . '</a></p>';
        $html .= '</div>';

        return $html;
    }

    protected function getLatestGithubReleaseVersion(bool $forceRefresh = false): array
    {
        $ttl = 21600;
        $checkedAt = (string) Configuration::get(self::CONFIG_GITHUB_CHECKED_AT);

        if (!$forceRefresh && $checkedAt !== '' && (time() - (int) strtotime($checkedAt)) < $ttl) {
            return [
                'version' => (string) Configuration::get(self::CONFIG_GITHUB_LATEST_VERSION),
                'checked_at' => $checkedAt,
                'error' => (string) Configuration::get(self::CONFIG_GITHUB_ERROR),
            ];
        }

        $response = $this->downloadGithubJson(self::GITHUB_API_LATEST_RELEASE_URL);
        $version = '';
        $error = '';

        if ($response['success']) {
            $data = json_decode($response['body'], true);
            if (is_array($data) && !empty($data['tag_name'])) {
                $version = (string) preg_replace('/^v/i', '', (string) $data['tag_name']);
            } else {
                $error = 'Invalid GitHub API response.';
            }
        } else {
            $error = $response['error'];
        }

        $now = date('Y-m-d H:i:s');
        Configuration::updateValue(self::CONFIG_GITHUB_LATEST_VERSION, $version);
        Configuration::updateValue(self::CONFIG_GITHUB_CHECKED_AT, $now);
        Configuration::updateValue(self::CONFIG_GITHUB_ERROR, $error);

        return ['version' => $version, 'checked_at' => $now, 'error' => $error];
    }

    protected function downloadGithubJson(string $url): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 6);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
            curl_setopt($ch, CURLOPT_USERAGENT, 'jooim-withdrawal-update-checker');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github+json']);
            $body = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($body !== false && $httpCode >= 200 && $httpCode < 300) {
                return ['success' => true, 'body' => (string) $body, 'error' => ''];
            }

            $error = $curlError !== '' ? $curlError : 'HTTP ' . $httpCode;
            return ['success' => false, 'body' => '', 'error' => $error];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 6,
                'header' => "User-Agent: jooim-withdrawal-update-checker\r\nAccept: application/vnd.github+json\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            return [
                'success' => false,
                'body' => '',
                'error' => 'Unable to download GitHub release information.',
            ];
        }

        return ['success' => true, 'body' => (string) $body, 'error' => ''];
    }

    /**
     * Provides an English fallback route set. PrestaShop copies moduleRoutes
     * output to every language. hookActionAfterLoadRoutes() then replaces each
     * route for each language with its localized rule.
     *
     * @return array<string,array<string,mixed>>
     */
    public function hookModuleRoutes($params)
    {
        unset($params);

        return $this->buildModuleRouteDefinitions($this->localizedRouteSlugs('en'));
    }

    /**
     * Registers genuine per-language friendly routes after PrestaShop has
     * loaded its non-multilingual moduleRoutes output.
     *
     * @param array<string,mixed> $params
     */
    public function hookActionAfterLoadRoutes(array $params): void
    {
        $dispatcher = $params['dispatcher'] ?? null;
        if (!is_object($dispatcher) || !method_exists($dispatcher, 'addRoute')) {
            return;
        }

        $idShop = (int) ($params['id_shop'] ?? 0);
        if ($idShop <= 0 && isset($this->context->shop->id)) {
            $idShop = (int) $this->context->shop->id;
        }
        if ($idShop <= 0) {
            $idShop = (int) Configuration::get('PS_SHOP_DEFAULT');
        }

        $languages = Language::getLanguages(true, $idShop > 0 ? $idShop : false);

        foreach ($languages as $language) {
            $idLang = (int) ($language['id_lang'] ?? 0);
            if ($idLang <= 0) {
                continue;
            }

            $isoCode = Tools::strtolower((string) ($language['iso_code'] ?? 'en'));
            $slugs = $this->localizedRouteSlugs($isoCode);
            $definitions = $this->buildModuleRouteDefinitions($slugs);

            foreach ($definitions as $routeId => $definition) {
                $dispatcher->addRoute(
                    $routeId,
                    (string) $definition['rule'],
                    (string) $definition['controller'],
                    $idLang,
                    (array) $definition['keywords'],
                    (array) $definition['params'],
                    $idShop
                );
            }
        }
    }

    /**
     * @param array<string,string> $slugs
     * @return array<string,array<string,mixed>>
     */
    private function buildModuleRouteDefinitions(array $slugs): array
    {
        $base = [
            'keywords' => [],
            'params' => [
                'fc' => 'module',
                'module' => $this->name,
            ],
        ];

        $controllers = [
            'direct',
            'access',
            'verify',
            'form',
            'review',
            'submit',
            'confirmation',
            'document',
            'status',
            'identify',
        ];
        $routes = [];
        foreach ($controllers as $controller) {
            $rule = $slugs['base'];
            if ($controller !== 'identify') {
                $rule .= '/' . $slugs[$controller];
            }
            $routes['module-' . $this->name . '-' . $controller] = array_merge($base, [
                'controller' => $controller,
                'rule' => $rule,
            ]);
        }

        return $routes;
    }

    /**
     * URL slugs use readable ASCII so they remain stable across web servers,
     * browsers, imports and third-party link-list modules.
     *
     * @return array<string,string>
     */
    private function localizedRouteSlugs(string $isoCode): array
    {
        $routes = [
            'lb' => ['base' => 'recktrett-vum-kontrakt', 'direct' => 'direkt', 'access' => 'zougang', 'verify' => 'iwwerpreiwung', 'form' => 'formular', 'review' => 'kontroll', 'submit' => 'ofschicken', 'confirmation' => 'bestaetegung', 'document' => 'dokument', 'status' => 'status'],
            'nb' => ['base' => 'angre-pa-avtalen', 'direct' => 'direkte', 'access' => 'tilgang', 'verify' => 'bekreftelse', 'form' => 'skjema', 'review' => 'gjennomgang', 'submit' => 'send', 'confirmation' => 'kvittering', 'document' => 'dokument', 'status' => 'status'],
            'nn' => ['base' => 'angre-pa-avtalen', 'direct' => 'direkte', 'access' => 'tilgang', 'verify' => 'stadfesting', 'form' => 'skjema', 'review' => 'gjennomgang', 'submit' => 'send', 'confirmation' => 'kvittering', 'document' => 'dokument', 'status' => 'status'],
            'no' => ['base' => 'angre-pa-avtalen', 'direct' => 'direkte', 'access' => 'tilgang', 'verify' => 'bekreftelse', 'form' => 'skjema', 'review' => 'gjennomgang', 'submit' => 'send', 'confirmation' => 'kvittering', 'document' => 'dokument', 'status' => 'status'],
            'bg' => ['base' => 'otkaz-ot-dogovora', 'direct' => 'direktno', 'access' => 'dostap', 'verify' => 'proverka', 'form' => 'formular', 'review' => 'pregled', 'submit' => 'izprashtane', 'confirmation' => 'potvarzhdenie', 'document' => 'dokument', 'status' => 'status'],
            'cs' => ['base' => 'odstoupeni-od-smlouvy', 'direct' => 'primo', 'access' => 'pristup', 'verify' => 'overeni', 'form' => 'formular', 'review' => 'kontrola', 'submit' => 'odeslat', 'confirmation' => 'potvrzeni', 'document' => 'dokument', 'status' => 'stav'],
            'da' => ['base' => 'fortrydelse-af-aftale', 'direct' => 'direkte', 'access' => 'adgang', 'verify' => 'verificering', 'form' => 'formular', 'review' => 'gennemgang', 'submit' => 'indsend', 'confirmation' => 'kvittering', 'document' => 'dokument', 'status' => 'status'],
            'de' => ['base' => 'vertragswiderruf', 'direct' => 'direkt', 'access' => 'zugang', 'verify' => 'verifizierung', 'form' => 'formular', 'review' => 'pruefung', 'submit' => 'absenden', 'confirmation' => 'bestaetigung', 'document' => 'dokument', 'status' => 'status'],
            'el' => ['base' => 'ypanachorisi-apo-symvasi', 'direct' => 'amesa', 'access' => 'prosvasi', 'verify' => 'epalithefsi', 'form' => 'forma', 'review' => 'elegchos', 'submit' => 'ypovoli', 'confirmation' => 'epivevaiosi', 'document' => 'engrafo', 'status' => 'katastasi'],
            'en' => ['base' => 'contract-withdrawal', 'direct' => 'direct', 'access' => 'access', 'verify' => 'verification', 'form' => 'form', 'review' => 'review', 'submit' => 'submit', 'confirmation' => 'confirmation', 'document' => 'document', 'status' => 'status'],
            'es' => ['base' => 'desistimiento-del-contrato', 'direct' => 'directo', 'access' => 'acceso', 'verify' => 'verificacion', 'form' => 'formulario', 'review' => 'revision', 'submit' => 'enviar', 'confirmation' => 'confirmacion', 'document' => 'documento', 'status' => 'estado'],
            'et' => ['base' => 'lepingust-taganemine', 'direct' => 'otse', 'access' => 'juurdepaas', 'verify' => 'kinnitamine', 'form' => 'vorm', 'review' => 'ulevaatus', 'submit' => 'esita', 'confirmation' => 'kinnitus', 'document' => 'dokument', 'status' => 'staatus'],
            'fi' => ['base' => 'sopimuksen-peruuttaminen', 'direct' => 'suoraan', 'access' => 'paasy', 'verify' => 'vahvistus', 'form' => 'lomake', 'review' => 'tarkistus', 'submit' => 'laheta', 'confirmation' => 'kuittaus', 'document' => 'asiakirja', 'status' => 'tila'],
            'fr' => ['base' => 'retractation-du-contrat', 'direct' => 'direct', 'access' => 'acces', 'verify' => 'verification', 'form' => 'formulaire', 'review' => 'recapitulatif', 'submit' => 'envoyer', 'confirmation' => 'confirmation', 'document' => 'document', 'status' => 'statut'],
            'ga' => ['base' => 'tarraingt-siar-on-gconradh', 'direct' => 'direach', 'access' => 'rochtain', 'verify' => 'fioru', 'form' => 'foirm', 'review' => 'athbhreithniu', 'submit' => 'seol', 'confirmation' => 'deimhniu', 'document' => 'doicimead', 'status' => 'stadas'],
            'hr' => ['base' => 'odustajanje-od-ugovora', 'direct' => 'izravno', 'access' => 'pristup', 'verify' => 'provjera', 'form' => 'obrazac', 'review' => 'pregled', 'submit' => 'posalji', 'confirmation' => 'potvrda', 'document' => 'dokument', 'status' => 'status'],
            'hu' => ['base' => 'szerzodestol-elallas', 'direct' => 'kozvetlen', 'access' => 'hozzaferes', 'verify' => 'ellenorzes', 'form' => 'urlap', 'review' => 'attekintes', 'submit' => 'bekuldes', 'confirmation' => 'megerosites', 'document' => 'dokumentum', 'status' => 'allapot'],
            'it' => ['base' => 'recesso-dal-contratto', 'direct' => 'diretto', 'access' => 'accesso', 'verify' => 'verifica', 'form' => 'modulo', 'review' => 'riepilogo', 'submit' => 'invia', 'confirmation' => 'conferma', 'document' => 'documento', 'status' => 'stato'],
            'lt' => ['base' => 'sutarties-atsisakymas', 'direct' => 'tiesiogiai', 'access' => 'prieiga', 'verify' => 'patvirtinimas', 'form' => 'forma', 'review' => 'perziura', 'submit' => 'pateikti', 'confirmation' => 'gavimo-patvirtinimas', 'document' => 'dokumentas', 'status' => 'busena'],
            'lv' => ['base' => 'atteikums-no-liguma', 'direct' => 'tiesi', 'access' => 'piekluve', 'verify' => 'parbaude', 'form' => 'veidlapa', 'review' => 'parskats', 'submit' => 'iesniegt', 'confirmation' => 'apstiprinajums', 'document' => 'dokuments', 'status' => 'statuss'],
            'mt' => ['base' => 'irtirar-mill-kuntratt', 'direct' => 'dirett', 'access' => 'access', 'verify' => 'verifika', 'form' => 'formola', 'review' => 'revizjoni', 'submit' => 'ibghat', 'confirmation' => 'konferma', 'document' => 'dokument', 'status' => 'status'],
            'nl' => ['base' => 'herroeping-van-de-overeenkomst', 'direct' => 'direct', 'access' => 'toegang', 'verify' => 'verificatie', 'form' => 'formulier', 'review' => 'controle', 'submit' => 'indienen', 'confirmation' => 'bevestiging', 'document' => 'document', 'status' => 'status'],
            'pl' => ['base' => 'odstapienie-od-umowy', 'direct' => 'bezposrednio', 'access' => 'dostep', 'verify' => 'weryfikacja', 'form' => 'formularz', 'review' => 'podsumowanie', 'submit' => 'wyslij', 'confirmation' => 'potwierdzenie', 'document' => 'dokument', 'status' => 'status'],
            'pt' => ['base' => 'resolucao-do-contrato', 'direct' => 'direto', 'access' => 'acesso', 'verify' => 'verificacao', 'form' => 'formulario', 'review' => 'revisao', 'submit' => 'enviar', 'confirmation' => 'confirmacao', 'document' => 'documento', 'status' => 'estado'],
            'ro' => ['base' => 'retragere-din-contract', 'direct' => 'direct', 'access' => 'acces', 'verify' => 'verificare', 'form' => 'formular', 'review' => 'revizuire', 'submit' => 'trimite', 'confirmation' => 'confirmare', 'document' => 'document', 'status' => 'stare'],
            'sk' => ['base' => 'odstupenie-od-zmluvy', 'direct' => 'priamo', 'access' => 'pristup', 'verify' => 'overenie', 'form' => 'formular', 'review' => 'kontrola', 'submit' => 'odoslat', 'confirmation' => 'potvrdenie', 'document' => 'dokument', 'status' => 'stav'],
            'sl' => ['base' => 'odstop-od-pogodbe', 'direct' => 'neposredno', 'access' => 'dostop', 'verify' => 'preverjanje', 'form' => 'obrazec', 'review' => 'pregled', 'submit' => 'oddaj', 'confirmation' => 'potrditev', 'document' => 'dokument', 'status' => 'stanje'],
            'sv' => ['base' => 'frantrade-fran-avtal', 'direct' => 'direkt', 'access' => 'atkomst', 'verify' => 'verifiering', 'form' => 'formular', 'review' => 'granskning', 'submit' => 'skicka', 'confirmation' => 'bekraftelse', 'document' => 'dokument', 'status' => 'status'],
            'uk' => ['base' => 'vidmova-vid-dohovoru', 'direct' => 'bezposeredno', 'access' => 'dostup', 'verify' => 'perevirka', 'form' => 'forma', 'review' => 'perehliad', 'submit' => 'nadislaty', 'confirmation' => 'pidtverdzhennia', 'document' => 'dokument', 'status' => 'status'],
        ];

        $isoCode = Tools::strtolower(trim($isoCode));

        return $routes[$isoCode] ?? $routes['en'];
    }



    public function hookDisplayFooter($params)
    {
        $this->jooimWithdrawalRegisterFrontAssets();

        // Accessible no-JavaScript fallback. The normal storefront script
        // relocates this link into ps_linklist immediately after Stores.
        $output = $this->jooimWithdrawalRenderFooterLink('displayFooter');

        // Shared Joobox attribution remains independent from the legal link.
        $output .= $this->renderFooterAttribution(is_array($params) ? $params : []);

        return $output;
    }



    public function hookDisplayFooterPart1($params)
    {
        // Compatibility shim only. Some commercial themes execute this hook
        // inside the page-content footer rather than the real site footer.
        // Rendering here caused the legal link to appear far above #footer.
        $this->jooimWithdrawalRegisterFrontAssets();
        return '';
    }



    public function hookDisplayFooterAfter($params)
    {
        // Compatibility shim for installations that still have the module
        // attached to this older test-build hook. Rendering here would place the
        // legal link outside the normal footer-link area and create duplicates.
        $this->jooimWithdrawalRegisterFrontAssets();
        return '';
    }



    protected function renderFooterAttribution(array $params = []): string
    {
        unset($params);

        if (!$this->active) {
            return '';
        }

        $this->context->smarty->assign([
            'joobox_home_url' => self::JOOBOX_HOME_URL,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/footer-attribution.tpl');
    }

    public function hookDisplayCustomerAccount(): string
    {
        $config = new ModuleConfig($this->context);
        if (!$config->bool('JOOIM_WITHDRAWAL_ACCOUNT_LINK')) {
            return '';
        }
        $this->context->smarty->assign([
            'withdrawal_url' => $this->context->link->getModuleLink($this->name, 'identify', [], true),
        ]);
        return $this->display(__FILE__, 'views/templates/hook/customer-account.tpl');
    }

    public function hookDisplayOrderDetail(array $params): string
    {
        $config = new ModuleConfig($this->context);
        if (!$config->bool('JOOIM_WITHDRAWAL_ORDER_BUTTON')) {
            return '';
        }
        $order = $params['order'] ?? null;
        if (!$order instanceof Order) {
            return '';
        }
        if (!$this->context->customer->isLogged() || (int) $order->id_customer !== (int) $this->context->customer->id) {
            return '';
        }
        $this->context->smarty->assign([
            'withdrawal_url' => $this->context->link->getModuleLink($this->name, 'form', ['id_order' => (int) $order->id], true),
        ]);
        return $this->display(__FILE__, 'views/templates/hook/order-button.tpl');
    }

    public function hookDisplayAdminOrderMain(array $params): string
    {
        return $this->renderAdminOrderPanel($params);
    }

    public function hookDisplayAdminOrderSide(array $params): string
    {
        return $this->renderAdminOrderPanel($params);
    }

    private function renderAdminOrderPanel(array $params): string
    {
        $idOrder = (int) ($params['id_order'] ?? 0);
        if ($idOrder <= 0) {
            return '';
        }
        $rows = ServiceContainer::get($this)->withdrawalRepository()->findByOrder($idOrder, (int) $this->context->shop->id);
        $rows = WithdrawalStatusPresenter::decorateRows($this, $rows);
        $this->context->smarty->assign([
            'withdrawals' => $rows,
            'withdrawal_admin_link' => $this->context->link->getAdminLink('AdminJooimWithdrawal'),
        ]);
        return $this->display(__FILE__, 'views/templates/hook/admin-order-panel.tpl');
    }

    public function hookActionValidateOrder(array $params): void
    {
        $order = $params['order'] ?? null;
        if ($order instanceof Order) {
            (new OrderIdentityService($this->context))->capture($order);
        }
    }

    public function hookActionObjectOrderAddAfter(array $params): void
    {
        $order = $params['object'] ?? null;
        if ($order instanceof Order) {
            (new OrderIdentityService($this->context))->capture($order);
        }
    }

    public function hookActionOrderStatusPostUpdate(array $params): void
    {
        $idOrder = (int) ($params['id_order'] ?? 0);
        $newStatus = $params['newOrderStatus'] ?? null;
        if ($idOrder > 0 && $newStatus instanceof OrderState && (bool) $newStatus->delivery) {
            Db::getInstance()->update(
                'jooim_withdrawal',
                ['delivery_date' => gmdate('Y-m-d H:i:s')],
                'id_order=' . $idOrder . ' AND delivery_date IS NULL'
            );
        }
    }

    public function hookActionFrontControllerSetMedia($params)
    {
        $this->jooimWithdrawalRegisterFrontAssets();
    }



    public function hookActionAdminControllerSetMedia(): void
    {
        $controller = (string) Tools::getValue('controller');
        $isModuleConfiguration = $controller === 'AdminModules'
            && (string) Tools::getValue('configure') === $this->name;

        if ($controller === 'AdminJooimWithdrawal' || $isModuleConfiguration) {
            $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
        }
    }


    private function jooimWithdrawalIsFooterLinkEnabled(): bool
    {
        $idShop = isset($this->context->shop) ? (int) $this->context->shop->id : null;
        $value = Configuration::get('JOOIM_WITHDRAWAL_FOOTER_LINK', null, null, $idShop);
        if ($value === false || $value === null || $value === '') {
            $value = Configuration::get('JOOIM_WITHDRAWAL_FOOTER_LINK');
        }
        if ($value === false || $value === null || $value === '') {
            return true;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }



    private function jooimWithdrawalUniversalUrl(string $target = 'direct'): string
    {
        if (!in_array($target, ['direct', 'identify'], true)) {
            $target = 'direct';
        }
    
        $idShop = isset($this->context->shop) ? (int) $this->context->shop->id : null;
        $base = '';
        if (isset($this->context->link) && method_exists($this->context->link, 'getBaseLink')) {
            try {
                $base = (string) $this->context->link->getBaseLink($idShop, true);
            } catch (Throwable $e) {
                $base = '';
            }
        }
        if ($base === '') {
            $base = Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__;
        }
    
        return rtrim($base, '/') . '/index.php?' . http_build_query([
            'fc' => 'module',
            'module' => $this->name,
            'controller' => 'entry',
            'target' => $target,
        ], '', '&', PHP_QUERY_RFC3986);
    }



    private function jooimWithdrawalFooterUrl(): string
    {
        $idShop = isset($this->context->shop) ? (int) $this->context->shop->id : null;
        $idLang = isset($this->context->language) ? (int) $this->context->language->id : null;

        if (isset($this->context->link) && method_exists($this->context->link, 'getModuleLink')) {
            try {
                $url = (string) $this->context->link->getModuleLink(
                    $this->name,
                    'identify',
                    [],
                    true,
                    $idLang,
                    $idShop
                );
                if ($url !== '') {
                    return $url;
                }
            } catch (Throwable $e) {
                // Fall back to the language-neutral entry controller below.
            }
        }

        return $this->jooimWithdrawalUniversalUrl('identify');
    }


    private function jooimWithdrawalFooterLabel(): string
    {
        return $this->trans(
            'Withdraw from the contract here',
            [],
            'Modules.Jooimwithdrawal.Shop'
        );
    }


    private function jooimWithdrawalStoresUrl(): string
    {
        if (!isset($this->context->link) || !method_exists($this->context->link, 'getPageLink')) {
            return '';
        }

        try {
            return (string) $this->context->link->getPageLink(
                'stores',
                true,
                isset($this->context->language->id) ? (int) $this->context->language->id : null
            );
        } catch (Throwable $e) {
            return '';
        }
    }



    private function jooimWithdrawalRenderFooterLink(string $hookName): string
    {
        if (!$this->active || !$this->jooimWithdrawalIsFooterLinkEnabled()) {
            return '';
        }

        $url = $this->jooimWithdrawalFooterUrl();
        $label = $this->jooimWithdrawalFooterLabel();

        try {
            $this->context->smarty->assign([
                'jooim_withdrawal_footer_enabled' => true,
                'jooim_withdrawal_footer_url' => $url,
                'jooim_withdrawal_footer_label' => $label,
                'jooim_withdrawal_footer_hook' => $hookName,
            ]);

            return $this->fetch('module:' . $this->name . '/views/templates/hook/footer_withdrawal_link.tpl');
        } catch (Throwable $e) {
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog(
                    '[jooim_withdrawal] Footer template fallback: ' . $e->getMessage(),
                    2,
                    null,
                    'Module',
                    (int) $this->id,
                    true
                );
            }

            return '<div class="jooim-withdrawal-footer-link-wrapper" data-jooim-withdrawal-footer-link="1"'
                . ' data-jooim-withdrawal-footer-fallback="1" data-jooim-withdrawal-footer-hook="'
                . htmlspecialchars($hookName, ENT_QUOTES, 'UTF-8') . '">'
                . '<a class="jooim-withdrawal-footer-link" href="'
                . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a></div>';
        }
    }



    private function jooimWithdrawalRegisterFrontAssets(): void
    {
        if (!$this->active || !isset($this->context->controller)) {
            return;
        }
    
        $controller = $this->context->controller;
        $cssPath = 'modules/' . $this->name . '/views/css/' . self::FRONT_ASSET_BASENAME . '.css';
        $jsPath = 'modules/' . $this->name . '/views/js/' . self::FRONT_ASSET_BASENAME . '.js';
    
        try {
            if (method_exists($controller, 'registerStylesheet')) {
                $controller->registerStylesheet(
                    'module-' . $this->name . '-front',
                    $cssPath,
                    ['media' => 'all', 'priority' => 10]
                );
            } elseif (method_exists($controller, 'addCSS')) {
                $controller->addCSS($this->_path . 'views/css/' . self::FRONT_ASSET_BASENAME . '.css');
            }
    
            if (method_exists($controller, 'registerJavascript')) {
                $controller->registerJavascript(
                    'module-' . $this->name . '-front',
                    $jsPath,
                    ['position' => 'bottom', 'priority' => 10]
                );
            } elseif (method_exists($controller, 'addJS')) {
                $controller->addJS($this->_path . 'views/js/' . self::FRONT_ASSET_BASENAME . '.js');
            }
        } catch (Throwable $e) {
            if (class_exists('PrestaShopLogger')) {
                PrestaShopLogger::addLog('[jooim_withdrawal] Front asset registration: ' . $e->getMessage(), 2);
            }
        }
    
        if (class_exists('Media')) {
            Media::addJsDef([
                'jooimWithdrawalFooter' => [
                    'enabled' => $this->jooimWithdrawalIsFooterLinkEnabled(),
                    'url' => $this->jooimWithdrawalFooterUrl(),
                    'label' => $this->jooimWithdrawalFooterLabel(),
                    'placement' => 'after-static-stores-link',
                    'storesUrl' => $this->jooimWithdrawalStoresUrl(),
                    'storesLinkIdPrefix' => 'link-static-page-stores-',
                ],
            ]);
        }
    }



    public function hookDisplayFooterBefore($params)
    {
        // Kept only as a no-output compatibility shim for earlier 1.0.0 test
        // builds. The link now uses displayFooter as a no-JS fallback and
        // is moved directly below the stores entry by the footer normalizer.
        $this->jooimWithdrawalRegisterFrontAssets();
        return '';
    }



    public function hookDisplayBeforeBodyClosingTag($params)
    {
        // Asset-only fallback. Rendering a visual block here would place it
        // outside the theme's footer-link area.
        $this->jooimWithdrawalRegisterFrontAssets();
        return '';
    }



    public function hookDisplayHeader($params)
    {
        $this->jooimWithdrawalRegisterFrontAssets();
        return '';
    }



    /** @return array<string,string> */
    private function jooimWithdrawalAdminTabLabels(): array
    {
        return [
            'en' => 'Contract withdrawals',
            'sk' => 'Odstúpenia od zmluvy',
            'cs' => 'Odstoupení od smlouvy',
            'de' => 'Vertragswiderrufe',
            'fr' => 'Rétractations de contrat',
            'pl' => 'Odstąpienia od umowy',
            'bg' => 'Откази от договор',
            'hr' => 'Odustajanja od ugovora',
            'da' => 'Fortrydelser af aftaler',
            'nl' => 'Herroepingen van overeenkomsten',
            'et' => 'Lepingust taganemised',
            'fi' => 'Sopimuksen peruuttamiset',
            'el' => 'Υπαναχωρήσεις από σύμβαση',
            'hu' => 'Szerződéstől való elállások',
            'ga' => 'Aistarraingtí ó chonradh',
            'it' => 'Recessi dal contratto',
            'lv' => 'Atteikumi no līguma',
            'lt' => 'Sutarties atsisakymai',
            'mt' => 'Irtirar mill-kuntratt',
            'pt' => 'Livre resolução de contratos',
            'ro' => 'Retrageri din contract',
            'sl' => 'Odstopi od pogodbe',
            'es' => 'Desistimientos del contrato',
            'sv' => 'Frånträden från avtal',
            'uk' => 'Відмови від договору',
            'lb' => 'Récktrëtter vu Kontrakter',
            'nb' => 'Angrerettsaker',
            'nn' => 'Angrerettsaker',
            'no' => 'Angrerettsaker',
        ];
    }

    private function jooimWithdrawalAdminTabLabel(string $isoCode): string
    {
        $labels = $this->jooimWithdrawalAdminTabLabels();
        $isoCode = Tools::strtolower(trim($isoCode));

        return $labels[$isoCode] ?? $labels['en'];
    }

    private function jooimWithdrawalSynchronizeAdminTabNames(): void
    {
        if (!class_exists('Tab') || !class_exists('Language')) {
            return;
        }

        $idTab = (int) Tab::getIdFromClassName('AdminJooimWithdrawal');
        if ($idTab <= 0) {
            return;
        }

        $tab = new Tab($idTab);
        $names = is_array($tab->name) ? $tab->name : [];
        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $names[$idLang] = $this->jooimWithdrawalAdminTabLabel(
                (string) ($language['iso_code'] ?? 'en')
            );
        }
        $tab->name = $names;
        if (!$tab->save()) {
            throw new RuntimeException('Unable to synchronize the AdminJooimWithdrawal tab names.');
        }
    }



    private function jooimWithdrawalEnsureRuntimeIntegration(): void
    {
        $idShop = isset($this->context->shop)
            ? (int) $this->context->shop->id
            : (int) Configuration::get('PS_SHOP_DEFAULT');
    
        $hooks = [
            'moduleRoutes',
            'actionAfterLoadRoutes',
            'displayHeader',
            'actionFrontControllerSetMedia',
            'displayFooter',
            'displayCustomerAccount',
            'displayOrderDetail',
        ];
        $hooks = array_values(array_unique($hooks));
    
        foreach ($hooks as $hookName) {
            $idHook = (int) Hook::getIdByName($hookName);
            if ($idHook <= 0) {
                continue;
            }
    
            $isLinkedToShop = (bool) Db::getInstance()->getValue(
                'SELECT 1 FROM `' . _DB_PREFIX_ . 'hook_module`'
                . ' WHERE `id_module` = ' . (int) $this->id
                . ' AND `id_hook` = ' . $idHook
                . ' AND `id_shop` = ' . $idShop
            );
            if ($isLinkedToShop) {
                continue;
            }
    
            try {
                $this->registerHook($hookName, [$idShop]);
            } catch (ArgumentCountError $e) {
                $this->registerHook($hookName);
            }
    
            $isLinkedToShop = (bool) Db::getInstance()->getValue(
                'SELECT 1 FROM `' . _DB_PREFIX_ . 'hook_module`'
                . ' WHERE `id_module` = ' . (int) $this->id
                . ' AND `id_hook` = ' . $idHook
                . ' AND `id_shop` = ' . $idShop
            );
    
            if (!$isLinkedToShop) {
                $position = 1 + (int) Db::getInstance()->getValue(
                    'SELECT COALESCE(MAX(`position`), 0) FROM `' . _DB_PREFIX_ . 'hook_module`'
                    . ' WHERE `id_hook` = ' . $idHook
                    . ' AND `id_shop` = ' . $idShop
                );
                Db::getInstance()->execute(
                    'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'hook_module`'
                    . ' (`id_module`, `id_shop`, `id_hook`, `position`) VALUES ('
                    . (int) $this->id . ', ' . $idShop . ', ' . $idHook . ', ' . $position . ')'
                );
            }
    
            $isLinkedToShop = (bool) Db::getInstance()->getValue(
                'SELECT 1 FROM `' . _DB_PREFIX_ . 'hook_module`'
                . ' WHERE `id_module` = ' . (int) $this->id
                . ' AND `id_hook` = ' . $idHook
                . ' AND `id_shop` = ' . $idShop
            );
            if (!$isLinkedToShop) {
                throw new RuntimeException('Unable to register hook for shop ' . $idShop . ': ' . $hookName);
            }
        }
    
        // Remove stale high-position registrations left by earlier test
        // builds. The compatibility methods return no content even if a hosting
        // restriction prevents unregistration.
        foreach (['displayFooterBefore', 'displayFooterAfter', 'displayBeforeBodyClosingTag', 'displayFooterPart1'] as $legacyHook) {
            if (method_exists($this, 'isRegisteredInHook') && $this->isRegisteredInHook($legacyHook)) {
                $this->unregisterHook($legacyHook);
            }
        }

        if (!$this->moveFooterLinkHookToEnd('displayFooter')) {
            throw new RuntimeException('Unable to place the displayFooter fallback after the existing footer modules.');
        }

        $this->jooimWithdrawalSynchronizeAdminTabNames();
    }


}
