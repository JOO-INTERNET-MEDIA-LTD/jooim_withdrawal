<?php
/**
 * In-memory runtime simulation of the final order-based withdrawal POST.
 *
 * No PrestaShop installation or database server is required. The test proves
 * that the transaction commits exactly one immutable withdrawal, survives
 * post-commit PDF/hook failures without output, and returns the existing record
 * on an idempotent retry.
 */

namespace {
    if (!defined('_DB_PREFIX_')) {
        define('_DB_PREFIX_', 'ps_');
    }

    if (!function_exists('pSQL')) {
        function pSQL($value, $htmlOk = false)
        {
            return addslashes((string) $value);
        }
    }

    if (!function_exists('mb_substr')) {
        function mb_substr($value, $start, $length = null, $encoding = null)
        {
            return $length === null
                ? substr((string) $value, (int) $start)
                : substr((string) $value, (int) $start, (int) $length);
        }
    }

    class Context
    {
        public object $shop;
        public object $language;
    }

    class Module
    {
        public int $id = 132;
        public string $version = '1.1.1';
    }

    class Configuration
    {
        public static function get($key)
        {
            return match ((string) $key) {
                'PS_TIMEZONE' => 'Europe/Bratislava',
                'PS_COUNTRY_DEFAULT' => 37,
                default => '',
            };
        }
    }

    class Country
    {
        public string $iso_code = 'SK';
        public function __construct($id)
        {
        }
    }

    class Order
    {
        public int $id;
        public int $id_shop = 1;
        public function __construct($id)
        {
            $this->id = (int) $id;
        }
    }

    class OrderDetail
    {
        public int $id;
        public int $id_order = 79;
        public int $product_quantity = 2;
        public int $product_quantity_refunded = 0;
        public int $product_quantity_return = 0;
        public function __construct($id)
        {
            $this->id = (int) $id;
        }
    }

    class Validate
    {
        public static function isLoadedObject($object): bool
        {
            return is_object($object) && isset($object->id) && (int) $object->id > 0;
        }
    }

    class Hook
    {
        public static function exec($name, $params = [])
        {
            echo 'HOOK_OUTPUT_MUST_NOT_ESCAPE';
            throw new \RuntimeException('Simulated third-party hook failure.');
        }
    }

    class PrestaShopLogger
    {
        public static array $messages = [];
        public static function addLog($message, $severity = 1, $errorCode = null, $objectType = null, $objectId = null, $allowDuplicate = false): bool
        {
            self::$messages[] = (string) $message;
            return true;
        }
    }

    final class JooimWithdrawalRuntimeDiagnostics
    {
        public static array $events = [];
        public static function report(\Throwable $exception, string $context, ?string $reference = null): void
        {
            self::$events[] = [$context, get_class($exception), $exception->getMessage()];
        }
    }

    class Db
    {
        public static ?self $instance = null;
        public array $tables = [];
        public array $executeQueries = [];
        public array $selectQueries = [];
        public array $updateCalls = [];
        private int $lastInsertId = 0;
        private int $nextId = 1000;
        private string $error = '';
        private int $affectedRows = 0;

        public function __construct()
        {
            $payload = [
                'schema_version' => '1.0',
                'source' => 'guest_order_number',
                'scope' => 'partial',
                'id_shop' => 1,
                'id_order' => 79,
                'id_customer' => 55,
                'id_lang' => 1,
                'id_currency' => 1,
                'order_reference' => 'ABC123XYZ',
                'order_date' => '2026-08-21 09:30:00',
                'customer' => [
                    'firstname' => 'Ján',
                    'lastname' => "O'Brien",
                    'email' => 'customer@example.test',
                    'phone' => '',
                ],
                'reason_code' => 'not_suitable',
                'customer_note' => "O'Brien note with 'quotes'",
                'statement_text' => 'I unequivocally withdraw from the selected part of the contract.',
                'items' => [[
                    'id_order_detail' => 501,
                    'id_product' => 2049,
                    'id_product_attribute' => 37487,
                    'product_reference' => "SKU'501",
                    'product_name' => 'Test product',
                    'product_attribute_name' => 'Black',
                    'customization_snapshot' => '',
                    'ordered_quantity' => 2,
                    'available_quantity' => 2,
                    'withdrawal_quantity' => 1,
                    'unit_price_tax_excl' => 20.0,
                    'unit_price_tax_incl' => 24.0,
                    'currency_iso' => 'EUR',
                ]],
                'privacy' => ['version' => 'privacy-1.0'],
                'legal_text_version' => 'eu-crd-11a-2026-06',
                'form_version' => '1.0',
            ];
            $this->tables['jooim_withdrawal_draft'] = [[
                'id_draft' => 900,
                'id_shop' => 1,
                'id_order' => 79,
                'id_customer' => 55,
                'source' => 'guest_order_number',
                'scope' => 'partial',
                'draft_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'idempotency_key_hash' => 'HASHED-KEY',
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 1800),
                'submitted_at' => null,
                'created_at' => gmdate('Y-m-d H:i:s'),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ]];
        }

        public static function getInstance($master = true): self
        {
            if (!self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function execute($sql, $useCache = true): bool
        {
            $this->executeQueries[] = (string) $sql;
            return true;
        }

        public function executeS($sql, $array = true, $useCache = true)
        {
            $sql = (string) $sql;
            $this->selectQueries[] = ['sql' => $sql, 'use_cache' => (bool) $useCache];

            if (str_contains($sql, 'jooim_withdrawal_draft')) {
                $row = $this->tables['jooim_withdrawal_draft'][0] ?? null;
                return $row ? [$row] : [];
            }
            if (str_contains($sql, '`ps_orders`')) {
                return [['id_order' => 79]];
            }
            if (str_contains($sql, 'jooim_withdrawal`') && str_contains($sql, 'id_source_draft=')) {
                $rows = array_values(array_filter(
                    $this->tables['jooim_withdrawal'] ?? [],
                    static fn(array $row): bool => (int) ($row['id_source_draft'] ?? 0) === 900
                ));
                return $rows;
            }
            return [];
        }

        public function getValue($sql, $useCache = true)
        {
            $this->selectQueries[] = ['sql' => (string) $sql, 'use_cache' => (bool) $useCache];
            return false;
        }

        public function insert($table, $data, $nullValues = false, $useCache = true, $type = 1, $addPrefix = true): bool
        {
            $table = (string) $table;
            $row = [];
            foreach ((array) $data as $key => $value) {
                if (is_array($value) && ($value['type'] ?? '') === 'sql') {
                    $row[$key] = $value['value'];
                } elseif (is_string($value)) {
                    $row[$key] = stripslashes($value);
                } else {
                    $row[$key] = $value;
                }
            }

            $idColumns = [
                'jooim_withdrawal' => 'id_jooim_withdrawal',
                'jooim_withdrawal_item' => 'id_jooim_withdrawal_item',
                'jooim_withdrawal_history' => 'id_history',
                'jooim_withdrawal_event_outbox' => 'id_event',
                'jooim_withdrawal_document' => 'id_document',
            ];
            if (isset($idColumns[$table])) {
                $this->lastInsertId = ++$this->nextId;
                $row[$idColumns[$table]] = $this->lastInsertId;
            } else {
                $this->lastInsertId = 0;
            }
            $this->tables[$table][] = $row;
            return true;
        }

        public function update($table, $data, $where = '', $limit = 0, $nullValues = false, $useCache = true, $addPrefix = true): bool
        {
            $table = (string) $table;
            $this->affectedRows = 0;
            $this->updateCalls[] = compact('table', 'data', 'where', 'limit', 'nullValues', 'useCache', 'addPrefix');
            if (!isset($this->tables[$table])) {
                return true;
            }
            foreach (array_keys($this->tables[$table]) as $index) {
                $row = $this->tables[$table][$index];
                $match = true;
                if (preg_match('/id_draft=(\d+)/', (string) $where, $m)) {
                    $match = (int) ($row['id_draft'] ?? 0) === (int) $m[1];
                } elseif (preg_match('/id_jooim_withdrawal=(\d+)/', (string) $where, $m)) {
                    $match = (int) ($row['id_jooim_withdrawal'] ?? 0) === (int) $m[1];
                } elseif (preg_match('/aggregate_id=(\d+)/', (string) $where, $m)) {
                    $match = (int) ($row['aggregate_id'] ?? 0) === (int) $m[1];
                }
                if (str_contains((string) $where, "status_code='submitted'") && ($row['status_code'] ?? '') !== 'submitted') {
                    $match = false;
                }
                if (!$match) {
                    continue;
                }
                ++$this->affectedRows;
                foreach ((array) $data as $key => $value) {
                    if (is_array($value) && ($value['type'] ?? '') === 'sql') {
                        $row[$key] = (int) ($row[$key] ?? 0) + 1;
                    } elseif (is_string($value)) {
                        $row[$key] = stripslashes($value);
                    } else {
                        $row[$key] = $value;
                    }
                }
                $this->tables[$table][$index] = $row;
            }
            return true;
        }

        public function Affected_Rows(): int
        {
            return $this->affectedRows;
        }

        public function Insert_ID(): int
        {
            return $this->lastInsertId;
        }

        public function getMsgError(): string
        {
            return $this->error;
        }
    }
}

namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Security {
    final class TokenService
    {
        public function hash(string $plain, string $purpose): string
        {
            return $purpose === 'draft_idempotency' ? 'HASHED-KEY' : hash('sha256', $purpose . '|' . $plain);
        }

        public function issueReceipt(int $withdrawalId, int $shopId, int $ttlSeconds = 7200): string
        {
            return 'receipt-' . $shopId . '-' . $withdrawalId;
        }
    }
}

namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Repository {
    final class WithdrawalRepository
    {
        public function find(int $id, ?int $shopId = null): ?array
        {
            foreach (\Db::getInstance()->tables['jooim_withdrawal'] ?? [] as $row) {
                if ((int) ($row['id_jooim_withdrawal'] ?? 0) === $id
                    && ($shopId === null || (int) ($row['id_shop'] ?? 0) === $shopId)
                ) {
                    return $row;
                }
            }
            return null;
        }

        public function items(int $withdrawalId): array
        {
            return array_values(array_filter(
                \Db::getInstance()->tables['jooim_withdrawal_item'] ?? [],
                static fn(array $row): bool => (int) ($row['id_jooim_withdrawal'] ?? 0) === $withdrawalId
            ));
        }
    }
}

namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service {
    final class ModuleConfig
    {
        public function merchantSnapshot(): array
        {
            return [
                'name' => 'Test merchant',
                'address' => 'Test address',
                'identifier' => 'TEST-1',
                'email' => 'merchant@example.test',
                'return_address' => 'Returns address',
            ];
        }
    }

    final class WithdrawalQuantityCalculator
    {
        public function activeWithdrawalQuantity(int $idOrderDetail): int
        {
            return 0;
        }
    }

    final class WithdrawalPeriodEvaluator
    {
        public function evaluate(?string $deliveryDateUtc, string $submittedAtUtc, int $days, string $shopTimezone): string
        {
            return 'unknown';
        }
    }

    final class WithdrawalReferenceGenerator
    {
        public function next(int $shopId, string $shopCode = ''): string
        {
            return 'WD-SK-2026-000001';
        }
    }

    final class WithdrawalPdfService
    {
        public function generate(array $withdrawal, array $items, array $snapshot): array
        {
            echo 'PDF_OUTPUT_MUST_NOT_ESCAPE';
            throw new \RuntimeException('Simulated PDF failure.');
        }
    }

    final class WithdrawalMailService
    {
        public static string $mode = 'failure';
        public function sendAcknowledgement(array $withdrawal, array $snapshot, ?string $pdfContent = null): bool
        {
            echo 'MAIL_OUTPUT_MUST_NOT_ESCAPE';
            if (self::$mode === 'race') {
                \Db::getInstance()->update('jooim_withdrawal', ['status_code' => 'refunded'], 'id_jooim_withdrawal=' . (int) $withdrawal['id_jooim_withdrawal']);
            }
            return self::$mode !== 'failure';
        }

        public function sendAdminNotification(array $withdrawal): void
        {
            echo 'ADMIN_MAIL_OUTPUT_MUST_NOT_ESCAPE';
        }
    }
}

namespace {
    $root = dirname(__DIR__);
    require_once $root . '/src/Service/WithdrawalSubmissionService.php';

    $failures = [];
    $passes = [];
    $assert = static function (bool $condition, string $label) use (&$failures, &$passes): void {
        if ($condition) {
            $passes[] = $label;
        } else {
            $failures[] = $label;
        }
    };

    $context = new \Context();
    $context->shop = (object) ['id' => 1, 'id_shop_group' => 1, 'name' => 'Test shop'];
    $context->language = (object) ['id' => 1, 'locale' => 'sk-SK', 'iso_code' => 'sk'];
    $module = new \Module();

    $service = new \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\WithdrawalSubmissionService(
        $context,
        $module,
        new \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\ModuleConfig(),
        new \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Security\TokenService(),
        new \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Repository\WithdrawalRepository(),
        new \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\WithdrawalQuantityCalculator(),
        new \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\WithdrawalPeriodEvaluator(),
        new \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\WithdrawalReferenceGenerator(),
        new \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\WithdrawalPdfService(),
        new \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\WithdrawalMailService()
    );

    ob_start();
    $first = $service->submit(900, 'plain-draft-key');
    $escapedOutput = (string) ob_get_clean();

    $db = \Db::getInstance();
    $withdrawals = $db->tables['jooim_withdrawal'] ?? [];
    $items = $db->tables['jooim_withdrawal_item'] ?? [];
    $draft = $db->tables['jooim_withdrawal_draft'][0] ?? [];
    $outbox = $db->tables['jooim_withdrawal_event_outbox'][0] ?? [];

    $assert($escapedOutput === '', 'post-commit PDF, mail and hook output is fully discarded');
    $assert(count($withdrawals) === 1, 'one withdrawal is committed');
    $assert(count($items) === 1 && (int) ($items[0]['withdrawal_quantity'] ?? 0) === 1, 'selected partial item is committed');
    $assert(!empty($draft['submitted_at']), 'draft is marked submitted in the same transaction');
    $assert(in_array('COMMIT', $db->executeQueries, true), 'transaction commits');
    $assert(!in_array('ROLLBACK', $db->executeQueries, true), 'successful transaction does not roll back');
    $assert(($first['receipt_token'] ?? '') === 'receipt-1-' . (int) ($withdrawals[0]['id_jooim_withdrawal'] ?? 0), 'receipt token references committed withdrawal');
    $assert(($outbox['status'] ?? '') === 'failed', 'failed integration hook is retained in outbox for retry');
    $assert((int) ($outbox['attempt_count'] ?? 0) === 1, 'failed hook increments outbox attempt counter');
    $assert(count(\JooimWithdrawalRuntimeDiagnostics::$events) >= 2, 'post-commit failures are diagnosed without invalidating submission');

    $snapshot = json_decode((string) ($withdrawals[0]['payload_json'] ?? ''), true);
    $assert(is_array($snapshot), 'immutable payload JSON remains valid after SQL escaping');
    $assert(($snapshot['customer']['lastname'] ?? '') === "O'Brien", 'apostrophes survive snapshot persistence');
    $assert(($snapshot['customer_note'] ?? '') === "O'Brien note with 'quotes'", 'quoted customer note survives snapshot persistence');

    $lockingQueries = array_values(array_filter(
        $db->selectQueries,
        static fn(array $entry): bool => str_contains((string) ($entry['sql'] ?? ''), 'FOR UPDATE')
    ));
    $assert(count($lockingQueries) === 2, 'draft and order rows are locked');
    $assert(count(array_filter(
        $lockingQueries,
        static fn(array $entry): bool => !str_contains((string) $entry['sql'], 'LIMIT') && ($entry['use_cache'] ?? true) === false
    )) === 2, 'locking reads preserve terminal FOR UPDATE and bypass cache');

    $beforeRetryCount = count($db->tables['jooim_withdrawal'] ?? []);
    ob_start();
    $second = $service->submit(900, 'plain-draft-key');
    $retryOutput = (string) ob_get_clean();
    $afterRetryCount = count($db->tables['jooim_withdrawal'] ?? []);

    $assert($retryOutput === '', 'idempotent retry emits no output');
    $assert($beforeRetryCount === 1 && $afterRetryCount === 1, 'idempotent retry creates no duplicate withdrawal');
    $assert(($second['withdrawal']['reference'] ?? '') === 'WD-SK-2026-000001', 'idempotent retry returns the original reference');

    // A manual transcription must retain its original receipt metadata in the final evidence.
    \Db::$instance = null;
    $db = \Db::getInstance();
    $manual = json_decode($db->tables['jooim_withdrawal_draft'][0]['draft_payload'], true);
    $manual['source'] = 'admin_manual';
    $manual['admin_employee_id'] = 7;
    $manual['received_method'] = 'email';
    $manual['received_at'] = '2026-08-20T10:00:00Z';
    $db->tables['jooim_withdrawal_draft'][0]['draft_payload'] = json_encode($manual);
    ob_start(); $service->submit(900, 'plain-draft-key'); ob_end_clean();
    $manualSnapshot = json_decode($db->tables['jooim_withdrawal'][0]['payload_json'], true);
    $assert($manualSnapshot['admin_employee_id'] === 7 && $manualSnapshot['received_method'] === 'email'
        && $manualSnapshot['received_at'] === '2026-08-20T10:00:00Z', 'manual original receipt metadata survives into final snapshot');
    $assert($db->tables['jooim_withdrawal_history'][0]['id_employee'] === 7, 'manual initial history identifies the responsible employee');
    $assert($manualSnapshot['module']['version'] === '1.1.1', 'snapshot uses installed module version');

    // Model an administrator changing status during a slow acknowledgement transport.
    \Db::$instance = null;
    \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\WithdrawalMailService::$mode = 'race';
    ob_start(); $service->submit(900, 'plain-draft-key'); ob_end_clean();
    $db = \Db::getInstance();
    $assert($db->tables['jooim_withdrawal'][0]['status_code'] === 'refunded', 'successful acknowledgement cannot overwrite a concurrent administrator status');
    $ackHistory = array_filter($db->tables['jooim_withdrawal_history'], static fn(array $row): bool => $row['event_code'] === 'acknowledgement_sent');
    $assert($ackHistory === [], 'no misleading status transition history when conditional update did not affect a row');

    \Db::$instance = null;
    \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\WithdrawalMailService::$mode = 'success';
    ob_start(); $service->submit(900, 'plain-draft-key'); ob_end_clean();
    $assert(\Db::getInstance()->tables['jooim_withdrawal'][0]['status_code'] === 'acknowledged', 'successful acknowledgement advances an unchanged submitted case');

    if ($failures !== []) {
        echo 'FAIL: ' . count($failures) . PHP_EOL;
        foreach ($failures as $failure) {
            echo ' - ' . $failure . PHP_EOL;
        }
        exit(1);
    }

    echo 'PASS: ' . count($passes) . " transaction runtime checks\n";
}
