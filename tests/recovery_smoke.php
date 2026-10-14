<?php
/** Runtime recovery model: real orchestration, isolated DB/PDF/hook adapters. */
namespace {
    define('_DB_PREFIX_', 'ps_');
    function pSQL($value) { return addslashes((string) $value); }
    class Context { public object $shop; public object $language; }
    class Shop { public int $id; public function __construct($id) { $this->id = $id; } public static function isFeatureActive() { return true; } public static function getShops(...$args) { return [1, 2]; } }
    class Language { public int $id; public function __construct($id) { $this->id = $id; } }
    class Validate { public static function isLoadedObject($o) { return $o->id > 0; } }
    class Hook {
        public static array $calls = [];
        public static bool $fail = false;
        public static function exec($name, $args) {
            self::$calls[] = [$name, $args];
            echo 'discarded hook output';
            if (self::$fail) { throw new \RuntimeException('simulated hook failure'); }
        }
    }
    class Db {
        private static ?self $instance = null;
        public array $queries = [];
        public array $inserts = [];
        public array $updates = [];
        public static function getInstance() { return self::$instance ??= new self(); }
        public function executeS($sql, ...$args) {
            $this->queries[] = $sql;
            preg_match('/w.id_shop=(\d+)/', $sql, $m);
            $shop = (int) $m[1];
            if (strpos($sql, 'SELECT w.*') === 0) { return [['id_shop' => $shop, 'id_jooim_withdrawal' => $shop, 'id_lang' => $shop + 10, 'reference' => 'WD-S' . $shop, 'payload_json' => '{"items":[]}']]; }
            return [['id_event' => $shop, 'aggregate_id' => $shop]];
        }
        public function insert($table, $data, ...$args) { $this->inserts[] = [$table, $data]; return true; }
        public function update($table, $data, $where, ...$args) { $this->updates[] = [$table, $data, $where]; return true; }
    }
}
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Repository {
    class WithdrawalRepository {
        public function items($id) { return []; }
        public function find($id, $shop) { if ($id !== $shop) { throw new \RuntimeException('Cross-shop lookup.'); } return ['id_shop' => $shop, 'id_lang' => $shop + 10, 'payload_json' => '{}']; }
    }
}
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service {
    class ModuleConfig { public int $retry = 3; public function int($key, $default) { return $this->retry; } }
    class WithdrawalPdfService {
        public bool $fail = false;
        public function generate($w, $items, $snapshot) { if ($this->fail) { throw new \RuntimeException('Simulated PDF failure.'); } return ['path' => '/isolated-model/document.pdf', 'size' => 10, 'hash' => str_repeat('a', 64)]; }
    }
}
namespace {
    require dirname(__DIR__) . '/src/Service/WithdrawalRecoveryService.php';
    $context = new Context(); $context->shop = new Shop(9); $context->language = new Language(99);
    $config = new \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\ModuleConfig();
    $pdf = new \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\WithdrawalPdfService();
    $repo = new \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Repository\WithdrawalRepository();
    $service = new \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\WithdrawalRecoveryService($context, $config, $repo, $pdf);
    $assert = static function ($ok, $message) { if (!$ok) { throw new RuntimeException($message); } };
    ob_start(); $result = $service->run(); $output = ob_get_clean();
    $assert($result === ['documents_recovered' => 2, 'events_retried' => 2], 'Each shop recovers its own document/event.');
    $assert($output === '', 'Hook output must be discarded.');
    $assert($context->shop->id === 9 && $context->language->id === 99, 'Original context restored.');
    foreach (Db::getInstance()->queries as $query) {
        $assert(strpos($query, 'w.id_shop=') !== false && strpos($query, 'LIMIT 20') !== false, 'Queries scoped and bounded.');
    }
    $assert(count(Hook::$calls) === 2 && Hook::$calls[0][1]['withdrawal']['id_shop'] === 1 && Hook::$calls[1][1]['withdrawal']['id_shop'] === 2, 'Hook receives correct shop withdrawal.');
    $pdf->fail = true; Hook::$fail = true;
    $result = $service->run();
    $assert($result['documents_recovered'] === 0, 'PDF failures do not count as recovery.');
    $events = array_slice(Db::getInstance()->updates, -2);
    $assert($events[0][1]['status'] === 'failed' && isset($events[0][1]['next_attempt_at']), 'Hook failure is rescheduled.');
    $failedHistory = array_filter(Db::getInstance()->inserts, static fn($r) => ($r[1]['event_code'] ?? '') === 'document_recovery_failed');
    $assert(count($failedHistory) === 2, 'Document failures recorded for bounded retries.');
    $config->retry = 0; $queryCount = count(Db::getInstance()->queries);
    $assert($service->run() === ['documents_recovered' => 0, 'events_retried' => 0] && count(Db::getInstance()->queries) === $queryCount, 'Disabled retries do no recovery work.');
    echo 'PASS: recovery success, failure, shop isolation, output buffering, context restoration and disabled retries' . PHP_EOL;
}
