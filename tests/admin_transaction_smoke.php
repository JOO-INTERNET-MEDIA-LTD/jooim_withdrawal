<?php
/** Dependency-free runtime model of real administrative and upgrade orchestration. */
declare(strict_types=1);

namespace {
    define('_PS_VERSION_', getenv('JOOIM_TEST_PS_VERSION') ?: '8.0.0');
    define('_DB_PREFIX_', 'ps_');
    define('_MYSQL_ENGINE_', 'InnoDB');
    function pSQL($value, $html = false): string { return addslashes((string) $value); }

    class Module {
        public object $context;
        public int $id = 77;
        public string $name = 'jooim_withdrawal';
        public array $_errors = [];
        public static int $uninstalls = 0;
        public function install() { return true; }
        public function uninstall() { ++self::$uninstalls; return true; }
        public function registerHook($name, $shops = null) { return true; }
        public function isRegisteredInHook($name) { return false; }
        public function trans($source, $parameters = [], $domain = null, $locale = null) { return strtr($source, $parameters); }
    }
    class ModuleAdminController {
        public object $context;
        public object $module;
        public array $errors = [];
        public array $confirmations = [];
        public bool $allowed = true;
        public function access($permission) { return $this->allowed; }
    }
    class Tools {
        public static array $input = [];
        public static function strtolower($value): string { return strtolower((string) $value); }
        public static function getValue($key, $default = false) { return self::$input[$key] ?? $default; }
        public static function isSubmit($key): bool { return array_key_exists($key, self::$input); }
        public static function getAdminTokenLite($controller): string { return 'isolated-request-marker'; }
    }
    class Shop { public static function getContextListShopID(): array { return [1]; } }
    class Validate { public static function isDate($value): bool { return $value === '2026-10-14 10:00:00'; } }
    class Configuration { public static function get($key) { return 'existing-configuration'; } }
    class Language { public static function getLanguages($active = true): array { return []; } }
    class Tab {
        public int $active = 0;
        public string $class_name = '';
        public string $module = '';
        public int $id_parent = 0;
        public array $name = [];
        public static function getIdFromClassName($name): int { return 0; }
        public function add(): bool { return true; }
        public function delete(): bool { return true; }
    }
    class PrestaShopLogger { public static function addLog(...$args): void {} }
    class Hook {
        public static bool $fail = false;
        public static int $calls = 0;
        public static function getIdByName($name): int { return 1; }
        public static function exec($name, $args = []) {
            ++self::$calls;
            echo 'hook output must be buffered';
            if (self::$fail) { throw new \RuntimeException('isolated hook failure'); }
        }
    }

    class Db {
        private static ?self $instance = null;
        public array $queries = [];
        public array $state = [];
        public string $failure = '';
        public bool $missingLockedRow = false;
        private ?array $snapshot = null;
        public static function getInstance(): self { return self::$instance ??= new self(); }
        public function reset(string $failure = ''): void {
            $this->queries = [];
            $this->state = ['status' => 'submitted', 'history' => [], 'refunds' => [], 'retained_legal_record' => 123];
            $this->failure = $failure;
            $this->missingLockedRow = false;
            $this->snapshot = null;
            Hook::$calls = 0;
            Hook::$fail = false;
        }
        public function execute($sql, ...$args): bool {
            $this->queries[] = $sql;
            if (($this->failure === 'start' && $sql === 'START TRANSACTION')
                || ($this->failure === 'commit' && $sql === 'COMMIT')
                || ($this->failure === 'schema' && strpos($sql, 'CREATE TABLE') === 0)) {
                return false;
            }
            if ($sql === 'START TRANSACTION') { $this->snapshot = $this->state; }
            if ($sql === 'ROLLBACK' && $this->snapshot !== null) { $this->state = $this->snapshot; $this->snapshot = null; }
            if ($sql === 'COMMIT') { $this->snapshot = null; }
            if (preg_match('/\b(?:DROP|TRUNCATE|DELETE)\b/i', $sql)) { unset($this->state['retained_legal_record']); }
            return true;
        }
        public function executeS($sql, ...$args) {
            $this->queries[] = $sql;
            if (strpos($sql, 'hook_module') !== false) { return [['id_module' => 77, 'position' => 1]]; }
            if ($this->failure === 'lock') { return false; }
            return $this->missingLockedRow ? [] : [['status_code' => $this->state['status']]];
        }
        public function getValue($sql, ...$args) { $this->queries[] = $sql; return 1; }
        public function update($table, $data, $where, ...$args): bool {
            $this->queries[] = 'UPDATE ' . $table . ' WHERE ' . $where;
            if ($this->failure === 'update') { return false; }
            if (isset($data['status_code'])) { $this->state['status'] = $data['status_code']; }
            return true;
        }
        public function insert($table, $data, ...$args): bool {
            $this->queries[] = 'INSERT ' . $table;
            if ($this->failure === 'insert:' . $table) { return false; }
            if ($table === 'jooim_withdrawal_history') { $this->state['history'][] = $data; }
            if ($table === 'jooim_withdrawal_refund') { $this->state['refunds'][] = $data; }
            return true;
        }
    }
    class ModelRepository {
        public int $shop = 1;
        public function find($id): ?array {
            return $id === 123 ? ['id_jooim_withdrawal' => 123, 'id_shop' => $this->shop, 'status_code' => Db::getInstance()->state['status']] : null;
        }
    }
    class ModelMail {
        public int $calls = 0;
        public function sendStatusNotification($row, $message): bool { ++$this->calls; return true; }
    }
    class ModelStorage { public function ensureStorage(): void {} }
}

namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service {
    class ServiceContainer {
        private static ?self $instance = null;
        public \ModelRepository $repo;
        public \ModelMail $mailer;
        public function __construct() { $this->repo = new \ModelRepository(); $this->mailer = new \ModelMail(); }
        public static function get($module): self { return self::$instance ??= new self(); }
        public function withdrawalRepository(): \ModelRepository { return $this->repo; }
        public function mail(): \ModelMail { return $this->mailer; }
        public function storage(): \ModelStorage { return new \ModelStorage(); }
    }
    class ModuleConfig { public function __construct($context) {} public function has($key): bool { return true; } }
    class TranslationCatalogInstaller {
        public function __construct($directory) {}
        public function synchronize(): array { return ['ok' => true, 'changed' => false, 'errors' => []]; }
    }
}

namespace {
    require dirname(__DIR__) . '/jooim_withdrawal.php';
    require dirname(__DIR__) . '/controllers/admin/AdminJooimWithdrawalController.php';
    require dirname(__DIR__) . '/upgrade/upgrade-1.1.0.php';
    $assertions = 0;
    $assert = static function (bool $condition, string $message) use (&$assertions): void {
        ++$assertions;
        if (!$condition) { throw new RuntimeException($message); }
    };
    $module = (new ReflectionClass(Jooim_Withdrawal::class))->newInstanceWithoutConstructor();
    $module->context = (object) ['shop' => (object) ['id' => 1, 'id_shop_group' => 1], 'employee' => (object) ['id' => 9]];
    $method = new ReflectionMethod(AdminJooimWithdrawalController::class, 'processActions');
    $method->setAccessible(true);
    $db = Db::getInstance();
    $services = \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\ServiceContainer::get($module);
    $run = static function (array $input, string $failure = '', bool $hookFailure = false, bool $missingLock = false, bool $permission = true) use ($module, $method, $db, $services): array {
        $db->reset($failure);
        $db->missingLockedRow = $missingLock;
        Hook::$fail = $hookFailure;
        $services->mailer->calls = 0;
        Tools::$input = $input + ['token' => Tools::getAdminTokenLite('AdminJooimWithdrawal')];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $controller = (new ReflectionClass(AdminJooimWithdrawalController::class))->newInstanceWithoutConstructor();
        $controller->context = $module->context;
        $controller->module = $module;
        $controller->allowed = $permission;
        ob_start();
        $method->invoke($controller);
        return [$controller, ob_get_clean()];
    };
    $statusInput = ['changeWithdrawalStatus' => '1', 'id_jooim_withdrawal' => 123, 'new_status_code' => 'under_review'];
    foreach (['start', 'lock', 'update', 'insert:jooim_withdrawal_history', 'commit'] as $failure) {
        [$controller, $output] = $run($statusInput, $failure);
        $assert($db->state['status'] === 'submitted' && $db->state['history'] === [], 'Failed status mutation must be rolled back: ' . $failure);
        $assert($controller->confirmations === [] && count($controller->errors) === 1, 'Failed status mutation must not report success: ' . $failure);
        $assert(in_array('ROLLBACK', $db->queries, true) && Hook::$calls === 0 && $services->mailer->calls === 0, 'Failed status mutation must rollback without notifications: ' . $failure);
    }
    [$controller, $output] = $run($statusInput, '', true);
    $assert($db->state['status'] === 'under_review' && count($db->state['history']) === 1, 'Hook exception cannot undo committed status/history.');
    $assert(count($controller->confirmations) === 1 && $controller->errors === [] && $output === '', 'Committed status reports success and buffers failing hook output.');
    $locks = array_filter($db->queries, static fn($q) => strpos($q, 'FOR UPDATE') !== false);
    $assert(count($locks) === 1 && strpos(reset($locks), 'id_shop IN (1)') !== false, 'Status lock must be scoped to allowed shops.');
    [$controller] = $run($statusInput, '', false, true);
    $assert($db->state['status'] === 'submitted' && $controller->confirmations === [] && in_array('ROLLBACK', $db->queries, true), 'Disappearing/inaccessible locked record fails safely.');
    $services->repo->shop = 2;
    [$controller] = $run($statusInput);
    $assert($controller->confirmations === [] && count($controller->errors) === 1 && $db->queries === [], 'Another shop record must be refused before a transaction.');
    $services->repo->shop = 1;
    [$controller] = $run(array_replace($statusInput, ['id_jooim_withdrawal' => 999]));
    $assert($controller->confirmations === [] && count($controller->errors) === 1 && $db->queries === [], 'Unknown withdrawal must be refused without writes.');
    [$controller] = $run($statusInput, '', false, false, false);
    $assert($controller->confirmations === [] && $db->queries === [], 'Missing edit permission must prevent writes.');
    [$controller] = $run($statusInput + ['token' => 'incorrect-request-marker']);
    $assert($controller->confirmations === [] && $db->queries === [], 'Invalid request token must prevent writes.');

    foreach (['not_a_status', 'rejected'] as $status) {
        [$controller] = $run(array_replace($statusInput, ['new_status_code' => $status]));
        $assert($controller->confirmations === [] && $db->queries === [], 'Invalid status or rejection without customer reason must prevent writes: ' . $status);
    }

    $refundInput = ['recordRefund' => '1', 'id_jooim_withdrawal' => 123, 'refund_amount' => '12,50', 'refund_currency' => 'EUR', 'refunded_at' => '2026-10-14 10:00:00'];
    foreach (['start', 'insert:jooim_withdrawal_refund', 'insert:jooim_withdrawal_history', 'commit'] as $failure) {
        [$controller] = $run($refundInput, $failure);
        $assert($db->state['refunds'] === [] && $db->state['history'] === [], 'Failed refund must rollback both records: ' . $failure);
        $assert($controller->confirmations === [] && count($controller->errors) === 1 && Hook::$calls === 0, 'Failed refund must not report success or dispatch: ' . $failure);
    }
    [$controller, $output] = $run($refundInput, '', true);
    $assert(count($db->state['refunds']) === 1 && count($db->state['history']) === 1 && $db->state['refunds'][0]['amount'] === '12.50', 'Committed refund preserves exact normalized decimal and audit record.');
    $assert(count($controller->confirmations) === 1 && $controller->errors === [] && $output === '', 'Refund survives failing postcommit hook without leaking output.');
    $services->repo->shop = 2;
    [$controller] = $run($refundInput);
    $assert($controller->confirmations === [] && $db->queries === [], 'Refund for another shop must be refused without writes.');
    $services->repo->shop = 1;
    foreach (['0', '-1', '1e3', '1.1234567'] as $amount) {
        [$controller] = $run(array_replace($refundInput, ['refund_amount' => $amount]));
        $assert($controller->confirmations === [] && $db->queries === [], 'Invalid refund amount must be refused: ' . $amount);
    }

    $db->reset('schema');
    $assert($module->jooimWithdrawalInstallCore() === false, 'Failed schema installation reports failure.');
    $assert($db->state['retained_legal_record'] === 123 && Module::$uninstalls === 1, 'Failed reinstall preserves legal records while unregistering the module.');
    $assert(!array_filter($db->queries, static fn($q) => preg_match('/\b(?:DROP|TRUNCATE|DELETE)\b/i', $q)), 'Installation rollback must not issue destructive SQL.');
    $db->reset();
    $assert(upgrade_module_1_1_0($module) === true && $db->state['retained_legal_record'] === 123, 'Real upgrade orchestration succeeds without deleting legal records.');
    $assert((bool) array_filter($db->queries, static fn($q) => strpos($q, 'CREATE TABLE IF NOT EXISTS') === 0), 'Upgrade performs idempotent schema synchronization.');
    $db->reset('schema');
    $assert(upgrade_module_1_1_0($module) === false && $db->state['retained_legal_record'] === 123, 'Upgrade synchronization failure reports false and retains records.');
    $labelMethod = new ReflectionMethod(Jooim_Withdrawal::class, 'jooimWithdrawalAdminTabLabel');
    $labelMethod->setAccessible(true);
    foreach (['lb', 'nb', 'nn', 'no'] as $iso) {
        $assert($labelMethod->invoke($module, $iso) !== 'Contract withdrawals', 'New language tab label is localized: ' . $iso);
    }

    echo 'PASS: ' . $assertions . ' administrative transaction, access, notification, rollback and upgrade runtime checks' . PHP_EOL;
}
