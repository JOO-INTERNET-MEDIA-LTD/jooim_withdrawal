<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Compatibility;

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\ServiceContainer;

abstract class FrontControllerBase extends \ModuleFrontController
{
    public $ssl = true;
    public $auth = false;
    public $guestAllowed = true;
    public $display_column_left = false;
    public $display_column_right = false;

    protected function services(): ServiceContainer
    {
        if (method_exists($this->module, 'ensureRuntimeSecurity')
            && !$this->module->ensureRuntimeSecurity()) {
            throw new \RuntimeException('JOOIM withdrawal security initialization failed.');
        }

        return ServiceContainer::get($this->module);
    }

    /**
     * Executes a normal HTML front controller through a common failure guard.
     *
     * A customer must never receive an unexplained blank page. The technical
     * exception is logged with a short reference, while the storefront receives
     * a neutral message without stack traces, file paths or other internals.
     */
    protected function runPage(callable $callback): void
    {
        try {
            if (!headers_sent()) {
                header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
            }
            parent::initContent();
            $callback();
        } catch (\Throwable $exception) {
            $this->renderFrontFailure($exception);
        }
    }

    /**
     * Executes a callback in a private output buffer. Third-party hooks, mail
     * transports or PDF libraries must not send bytes before a redirect.
     *
     * @return mixed
     */
    protected function executeWithoutOutput(callable $callback)
    {
        $bufferLevel = ob_get_level();
        $bufferStarted = @ob_start();
        try {
            return $callback();
        } finally {
            if ($bufferStarted) {
                while (ob_get_level() > $bufferLevel) {
                    @ob_end_clean();
                }
            }
        }
    }

    /**
     * Creates and persists a short public error reference without rendering a
     * storefront page. POST action controllers use this method before a safe
     * redirect, while normal HTML controllers call it from renderFrontFailure().
     */
    protected function reportFrontFailure(\Throwable $exception, string $origin = ''): string
    {
        $component = $this->failureComponent($exception);
        $reference = 'WDERR-'
            . gmdate('Ymd-His')
            . '-'
            . $component
            . '-'
            . strtoupper(substr(hash(
                'sha256',
                get_class($exception) . '|' . $exception->getMessage() . '|' . $exception->getFile() . '|' . $exception->getLine()
            ), 0, 8));

        $logMessage = sprintf(
            'JOOIM Withdrawal front-office failure [%s]: %s: %s in %s:%d',
            $reference,
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        try {
            if (class_exists('PrestaShopLogger')) {
                \PrestaShopLogger::addLog(
                    $logMessage,
                    3,
                    null,
                    'Module',
                    isset($this->module->id) ? (int) $this->module->id : 0,
                    true
                );
            } else {
                error_log($logMessage);
            }
        } catch (\Throwable $loggingException) {
            error_log($logMessage);
        }

        try {
            if (class_exists('\JooimWithdrawalRuntimeDiagnostics')) {
                \JooimWithdrawalRuntimeDiagnostics::report(
                    $exception,
                    $origin !== '' ? $origin : static::class,
                    $reference
                );
            }
        } catch (\Throwable $diagnosticsException) {
            error_log($logMessage . ' | diagnostics: ' . $diagnosticsException->getMessage());
        }

        return $reference;
    }

    protected function renderFrontFailure(\Throwable $exception): void
    {
        $reference = $this->reportFrontFailure(
            $exception,
            static::class . '::renderFrontFailure'
        );

        http_response_code(503);
        $title = 'Online withdrawal temporarily unavailable';
        $text = 'The online withdrawal page could not be loaded. Please try again later or contact the trader.'
            . ' Error reference: ' . $reference;

        try {
            $title = $this->module->jooimTranslate(
                'Online withdrawal temporarily unavailable',
                [],
                'Modules.Jooimwithdrawal.Shop'
            );
            $text = $this->module->jooimTranslate(
                'The online withdrawal page could not be loaded. Please try again later or contact the trader.',
                [],
                'Modules.Jooimwithdrawal.Shop'
            ) . ' ' . $this->module->jooimTranslate(
                'Error reference: %reference%',
                ['%reference%' => $reference],
                'Modules.Jooimwithdrawal.Shop'
            );
        } catch (\Throwable $translationException) {
            // Keep the English fallback above when the translator itself is unavailable.
        }

        try {
            $this->context->smarty->assign([
                'message_title' => $title,
                'message_text' => $text,
            ]);
            $this->renderModule('message.tpl');
        } catch (\Throwable $renderException) {
            // Last-resort response if the active theme, Smarty or module template is the failure source.
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=UTF-8');
            }
            echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
                . '<meta name="robots" content="noindex,nofollow"><title>'
                . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
                . '</title></head><body><main><h1>'
                . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
                . '</h1><p>'
                . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
                . '</p></main></body></html>';
            exit;
        }
    }

    /**
     * Builds a localized safe redirect target for a failed final POST.
     * Only non-sensitive reason codes and validated short references are sent.
     */
    protected function submissionFailureUrl(
        string $controller,
        string $reason,
        string $reference = ''
    ): string {
        if (!in_array($controller, ['identify', 'direct'], true)) {
            $controller = 'identify';
        }
        if (!in_array($reason, ['session', 'access', 'technical'], true)) {
            $reason = 'technical';
        }

        $params = ['withdrawal_error' => $reason];
        if ($reason === 'technical'
            && preg_match('/^WDERR-[0-9]{8}-[0-9]{6}-[A-Z0-9]{2,12}-[A-F0-9]{8}$/', $reference)
        ) {
            $params['withdrawal_error_reference'] = $reference;
        }

        return $this->context->link->getModuleLink(
            $this->module->name,
            $controller,
            $params,
            true
        );
    }

    /**
     * Resolves a generic customer-facing message after the POST controller has
     * redirected to a normal GET page. Query data is never treated as trusted
     * technical detail.
     */
    protected function submissionErrorMessage(): ?string
    {
        $reason = (string) \Tools::getValue('withdrawal_error');
        if ($reason === 'session') {
            return $this->module->jooimTranslate(
                'The confirmation session expired. Please start again.',
                [],
                'Modules.Jooimwithdrawal.Validation'
            );
        }
        if ($reason === 'access') {
            return $this->module->jooimTranslate(
                'Your secure order session expired. Please request a new access link.',
                [],
                'Modules.Jooimwithdrawal.Validation'
            );
        }
        if ($reason !== 'technical') {
            return null;
        }

        $message = $this->module->jooimTranslate(
            'The online withdrawal page could not be loaded. Please try again later or contact the trader.',
            [],
            'Modules.Jooimwithdrawal.Shop'
        );
        $reference = strtoupper((string) \Tools::getValue('withdrawal_error_reference'));
        if (preg_match('/^WDERR-[0-9]{8}-[0-9]{6}-[A-Z0-9]{2,12}-[A-F0-9]{8}$/', $reference)) {
            $message .= ' ' . $this->module->jooimTranslate(
                'Error reference: %reference%',
                ['%reference%' => $reference],
                'Modules.Jooimwithdrawal.Shop'
            );
        }

        return $message;
    }

    private function failureComponent(\Throwable $exception): string
    {
        $file = str_replace('\\', '/', $exception->getFile());
        $class = get_class($exception);

        if (strpos($file, '/Security/CsrfService.php') !== false) {
            return 'CSRF';
        }
        if (strpos($file, '/Security/') !== false) {
            return 'SEC';
        }
        if (strpos($file, '/Service/ModuleConfig.php') !== false
            || strpos($file, '/classes/Configuration.php') !== false) {
            return 'CONF';
        }
        if (strpos($file, '/classes/Cookie.php') !== false) {
            return 'COOKIE';
        }
        if (strpos($file, '/Repository/') !== false
            || stripos($class, 'Database') !== false
            || stripos($exception->getMessage(), 'database failure at') !== false
            || strpos($file, '/classes/db/') !== false) {
            return 'DB';
        }
        // A failure in another module must be distinguishable from a defect
        // in jooim_withdrawal itself. This is especially important for shared
        // hooks such as displayHeader, where one third-party module can abort
        // the complete page lifecycle before our controller code is reached.
        $ownModulePath = '/modules/' . strtolower((string) $this->module->name) . '/';
        $normalizedFile = strtolower($file);
        if (strpos($normalizedFile, '/modules/') !== false
            && strpos($normalizedFile, $ownModulePath) === false
        ) {
            return 'EXT';
        }
        if (stripos($class, 'Smarty') !== false
            || strpos($file, '/smarty/') !== false
            || strpos($file, '/views/templates/') !== false) {
            return 'VIEW';
        }

        return 'APP';
    }

    protected function ip(): string
    {
        $ip = (string) \Tools::getRemoteAddr();
        return $ip !== '' ? $ip : '0.0.0.0';
    }

    protected function ua(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1024);
    }

    protected function statement(): string
    {
        return $this->module->jooimTranslate(
            'I hereby unequivocally notify you that I withdraw from the identified contract or its selected part.',
            [],
            'Modules.Jooimwithdrawal.Shop'
        );
    }

    protected function privacyText(): string
    {
        return $this->module->jooimTranslate(
            'We process the personal data entered in this form for the purpose of receiving, recording and handling your contract withdrawal. Details are available in our privacy policy.',
            [],
            'Modules.Jooimwithdrawal.Shop'
        );
    }

    protected function commonAssign(): void
    {
        $config = $this->services()->config();
        $themePageTemplate = 'page.tpl';

        // Signed-in customers should see the withdrawal process in the same
        // visual shell as their account pages whenever the active theme
        // provides the standard customer/page.tpl contract. Guest and public
        // flows use page.tpl. Both parents inherit the active theme layout,
        // header, navigation, notifications and footer.
        try {
            $customer = $this->context->customer ?? null;
            $customerPageAvailable = false;

            // Ask Smarty first so child themes can inherit customer/page.tpl
            // from their declared parent. The physical-file check remains a
            // conservative fallback for themes whose Smarty wrapper does not
            // expose templateExists().
            if (isset($this->context->smarty)
                && method_exists($this->context->smarty, 'templateExists')
            ) {
                $customerPageAvailable = (bool) $this->context->smarty->templateExists('customer/page.tpl');
            }
            if (!$customerPageAvailable && defined('_PS_THEME_DIR_')) {
                $customerTemplate = rtrim((string) _PS_THEME_DIR_, '/\\')
                    . '/templates/customer/page.tpl';
                $customerPageAvailable = is_file($customerTemplate);
            }

            if ($customer
                && method_exists($customer, 'isLogged')
                && $customer->isLogged()
                && $customerPageAvailable
            ) {
                $themePageTemplate = 'customer/page.tpl';
            }
        } catch (\Throwable $exception) {
            // Theme inheritance must never block the legal withdrawal flow.
            $themePageTemplate = 'page.tpl';
        }

        $this->context->smarty->assign([
            'jooim_theme_page_template' => $themePageTemplate,
            'jooim_merchant' => $config->merchantSnapshot(),
            'jooim_privacy_text' => $this->privacyText(),
            'jooim_privacy_url' => $config->get('JOOIM_WITHDRAWAL_PRIVACY_URL', ''),
            'jooim_statement' => $this->statement(),
            'jooim_identify_url' => $this->context->link->getModuleLink($this->module->name, 'identify', [], true),
            'jooim_direct_url' => $this->context->link->getModuleLink($this->module->name, 'direct', [], true),
        ]);
    }

    protected function ensureEnabled(): bool
    {
        if (!$this->services()->config()->bool('JOOIM_WITHDRAWAL_ENABLED', true)) {
            http_response_code(503);
            $this->context->smarty->assign([
                'message_title' => $this->module->jooimTranslate(
                    'Online withdrawal temporarily unavailable',
                    [],
                    'Modules.Jooimwithdrawal.Shop'
                ),
                'message_text' => $this->module->jooimTranslate(
                    'Online withdrawal is temporarily unavailable.',
                    [],
                    'Modules.Jooimwithdrawal.Shop'
                ),
            ]);
            return false;
        }
        return true;
    }

    protected function renderModule(string $template): void
    {
        $this->commonAssign();
        $this->setTemplate('module:' . $this->module->name . '/views/templates/front/' . $template);
    }
}
