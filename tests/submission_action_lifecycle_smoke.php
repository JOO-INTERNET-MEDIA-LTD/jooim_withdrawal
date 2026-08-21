<?php
/**
 * Runtime model of the PrestaShop 8 final-action lifecycle.
 *
 * The final POST must be committed in postProcess() and redirected before
 * Controller::run() can call initHeader()/initContent(), because initContent()
 * executes third-party displayHeader hooks.
 */

namespace {
    define('_PS_VERSION_', '8.2.3-test');

    final class LifecycleRedirect extends \RuntimeException
    {
        public string $target;

        public function __construct(string $target)
        {
            parent::__construct('redirect');
            $this->target = $target;
        }
    }

    final class LifecycleLink
    {
        public function getModuleLink($module, $controller, array $params = [], $ssl = false): string
        {
            $query = $params === [] ? '' : '?' . http_build_query($params);
            return '/module/' . $module . '/' . $controller . $query;
        }
    }

    final class LifecycleConfig
    {
        public function bool(string $key, bool $default = false): bool
        {
            return true;
        }
    }

    final class LifecycleDrafts
    {
        public function getForSubmission(int $idDraft, string $key): array
        {
            return [
                'id_draft' => $idDraft,
                'id_order' => 0,
                'source' => 'guest_direct',
                'submitted_at' => null,
            ];
        }
    }

    final class LifecycleCsrf
    {
        public function validate(string $purpose, string $token, bool $consume = false): bool
        {
            return true;
        }
    }

    final class LifecycleAccess
    {
        public function forOrder(int $idOrder): bool
        {
            return true;
        }
    }

    final class LifecycleWithdrawals
    {
        public int $submissions = 0;

        public function submit(int $idDraft, string $key): array
        {
            ++$this->submissions;
            return ['receipt_token' => 'receipt-token'];
        }
    }

    final class LifecycleServices
    {
        public LifecycleWithdrawals $withdrawalsService;

        public function __construct()
        {
            $this->withdrawalsService = new LifecycleWithdrawals();
        }

        public function config(): LifecycleConfig
        {
            return new LifecycleConfig();
        }

        public function drafts(): LifecycleDrafts
        {
            return new LifecycleDrafts();
        }

        public function csrf(): LifecycleCsrf
        {
            return new LifecycleCsrf();
        }

        public function accessResolver(): LifecycleAccess
        {
            return new LifecycleAccess();
        }

        public function withdrawals(): LifecycleWithdrawals
        {
            return $this->withdrawalsService;
        }
    }

    class Tools
    {
        public static array $values = [
            'id_draft' => 7,
            'draft_key' => 'high-entropy-test-key',
            'csrf_token' => 'csrf-test-token',
        ];

        public static function getValue($key, $default = false)
        {
            return self::$values[$key] ?? $default;
        }

        public static function getShopDomainSsl($http = false): string
        {
            return $http ? 'https://shop.example.test' : 'shop.example.test';
        }

        public static function redirect($url): void
        {
            throw new LifecycleRedirect((string) $url);
        }
    }

    class ModuleFrontController
    {
        public bool $display_header = true;
        public bool $display_header_javascript = true;
        public bool $display_footer = true;
        public string $redirect_after = '';
        public object $context;
        public object $module;
        public int $setMediaCalls = 0;
        public int $initHeaderCalls = 0;
        public int $initContentCalls = 0;

        public function __construct()
        {
            $this->context = (object) ['link' => new LifecycleLink()];
            $this->module = (object) ['name' => 'jooim_withdrawal'];
        }

        public function init()
        {
        }

        public function displayHeader($display = true): void
        {
            $this->display_header = (bool) $display;
        }

        public function displayHeaderJavaScript($display = true): void
        {
            $this->display_header_javascript = (bool) $display;
        }

        public function displayFooter($display = true): void
        {
            $this->display_footer = (bool) $display;
        }

        public function setRedirectAfter($url): void
        {
            $this->redirect_after = (string) $url;
        }

        public function setMedia(): void
        {
            ++$this->setMediaCalls;
        }

        public function initHeader(): void
        {
            ++$this->initHeaderCalls;
            throw new \RuntimeException('displayHeader must not execute for a final POST action');
        }

        public function initContent()
        {
            ++$this->initContentCalls;
            throw new \RuntimeException('initContent must not execute for a final POST action');
        }

        protected function redirect(): void
        {
            throw new LifecycleRedirect($this->redirect_after);
        }

        public function run(): void
        {
            $this->init();
            if ($this->display_header) {
                $this->setMedia();
            }
            $this->postProcess();
            if ($this->redirect_after !== '') {
                $this->redirect();
            }
            if ($this->display_header) {
                $this->initHeader();
            }
            $this->initContent();
        }
    }
}

namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Compatibility {
    abstract class FrontControllerBase extends \ModuleFrontController
    {
        public \LifecycleServices $testServices;

        public function __construct()
        {
            parent::__construct();
            $this->testServices = new \LifecycleServices();
        }

        protected function services(): \LifecycleServices
        {
            return $this->testServices;
        }

        protected function executeWithoutOutput(callable $callback)
        {
            return $callback();
        }

        protected function reportFrontFailure(\Throwable $exception, string $origin = ''): string
        {
            return 'WDERR-20260821-000000-APP-00000000';
        }

        protected function submissionFailureUrl(string $controller, string $reason, string $reference = ''): string
        {
            return '/module/jooim_withdrawal/' . $controller . '?withdrawal_error=' . $reason;
        }
    }
}

namespace {
    require_once dirname(__DIR__) . '/controllers/front/submit.php';
    require_once dirname(__DIR__) . '/controllers/front/directsubmit.php';

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $failures = [];
    $passes = [];

    $assert = static function (bool $condition, string $label) use (&$failures, &$passes): void {
        if ($condition) {
            $passes[] = $label;
        } else {
            $failures[] = $label;
        }
    };

    foreach ([
        \Jooim_WithdrawalSubmitModuleFrontController::class,
        \Jooim_WithdrawalDirectsubmitModuleFrontController::class,
    ] as $controllerClass) {
        $controller = new $controllerClass();
        $redirect = null;
        try {
            $controller->run();
        } catch (LifecycleRedirect $exception) {
            $redirect = $exception->target;
        }

        $assert($controller->testServices->withdrawalsService->submissions === 1, $controllerClass . ' commits one submission in postProcess');
        $assert(is_string($redirect) && str_contains($redirect, '/confirmation?'), $controllerClass . ' redirects to confirmation');
        $assert($controller->display_header === false, $controllerClass . ' disables header rendering');
        $assert($controller->display_footer === false, $controllerClass . ' disables footer rendering');
        $assert($controller->display_header_javascript === false, $controllerClass . ' disables header JavaScript rendering');
        $assert($controller->setMediaCalls === 0, $controllerClass . ' does not initialize theme media');
        $assert($controller->initHeaderCalls === 0, $controllerClass . ' does not initialize the header');
        $assert($controller->initContentCalls === 0, $controllerClass . ' does not initialize content/displayHeader hooks');
    }

    if ($failures !== []) {
        echo 'FAIL: ' . count($failures) . PHP_EOL;
        foreach ($failures as $failure) {
            echo ' - ' . $failure . PHP_EOL;
        }
        exit(1);
    }

    echo 'PASS: ' . count($passes) . " submission action lifecycle checks\n";
}
