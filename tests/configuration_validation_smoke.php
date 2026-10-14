<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/Service/ConfigurationValidator.php';
use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\ConfigurationValidator;
$values = [];
foreach (['ENABLED', 'FOOTER_LINK', 'ACCOUNT_LINK', 'ORDER_BUTTON', 'DIRECT_FUNCTION', 'ATTACH_PDF'] as $key) {
    $values['JOOIM_WITHDRAWAL_' . $key] = '1';
}
foreach (['MAGIC_TTL_MINUTES' => '30', 'SESSION_IDLE_MINUTES' => '30', 'SESSION_MAX_MINUTES' => '120',
    'RESEND_COOLDOWN_SECONDS' => '60', 'IP_15M_LIMIT' => '5', 'IP_DAY_LIMIT' => '20',
    'ORDER_HOUR_LIMIT' => '3', 'ORDER_DAY_LIMIT' => '10', 'NOTE_MAX_LENGTH' => '2000', 'RETRY_COUNT' => '3',
    'REFERENCE_PREFIX' => 'WD', 'MERCHANT_EMAIL' => 'returns@example.com', 'MERCHANT_NAME' => 'Test trader',
    'PRIVACY_URL' => 'https://example.com/privacy'] as $key => $value) {
    $values['JOOIM_WITHDRAWAL_' . $key] = $value;
}
$v = new ConfigurationValidator();
if (!$v->isValid($values)) { throw new RuntimeException('Valid settings rejected.'); }
$invalid = [
    ['SESSION_IDLE_MINUTES', '121'], ['SESSION_MAX_MINUTES', '-1'], ['RETRY_COUNT', '11'],
    ['MAGIC_TTL_MINUTES', '5e1'], ['IP_DAY_LIMIT', '4'], ['ORDER_DAY_LIMIT', '2'],
    ['ENABLED', 'yes'], ['REFERENCE_PREFIX', str_repeat('A', 13)], ['MERCHANT_EMAIL', 'invalid'],
    ['MERCHANT_NAME', ' '], ['PRIVACY_URL', 'http://example.com/privacy'], ['RETURN_ADDRESS', str_repeat('a', 2001)],
];
foreach ($invalid as [$key, $value]) {
    $case = $values;
    $case['JOOIM_WITHDRAWAL_' . $key] = $value;
    if ($v->isValid($case)) { throw new RuntimeException('Invalid settings accepted: ' . $key); }
}
echo 'PASS: valid configuration and ' . count($invalid) . ' invalid configurations' . PHP_EOL;

// Exercise actual persistence with a failing second write, including core cache restoration.
class Context { public object $shop; }
class Shop { public static bool $active = true; public static function isFeatureActive(): bool { return self::$active; } }
class Db
{
    private static ?self $instance = null;
    public array $values = [];
    private array $before = [];
    public bool $failCommit = false;
    public static function getInstance(): self { return self::$instance ??= new self(); }
    public function execute($sql, ...$args): bool
    {
        if ($sql === 'START TRANSACTION') { $this->before = $this->values; }
        if ($sql === 'ROLLBACK') { $this->values = $this->before; }
        return !($sql === 'COMMIT' && $this->failCommit);
    }
}
class Configuration
{
    public static array $cache = [];
    public static ?string $failKey = null;
    public static function get($key, ...$args) { return self::$cache[$key] ?? false; }
    public static function hasKey($key, ...$args) {
        if (!Shop::$active && (int) end($args) !== 0) { return false; }
        return array_key_exists($key, self::$cache);
    }
    public static function set($key, $value, ...$args): void {
        // Unlike updateValue, the core temporary-cache API does not normalize scope.
        if (!Shop::$active && (int) end($args) !== 0) { return; }
        self::$cache[$key] = $value;
    }
    public static function updateValue($key, $value, ...$args): bool
    {
        self::$cache[$key] = $value;
        if (self::$failKey === $key) { return false; }
        Db::getInstance()->values[$key] = $value;
        return true;
    }
}
require dirname(__DIR__) . '/src/Service/ModuleConfig.php';
$c = new Context(); $c->shop = (object) ['id' => 1, 'id_shop_group' => 1];
$config = new \JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\ModuleConfig($c);
$before = ['IDLE' => '30', 'MAX' => '120'];
Db::getInstance()->values = Configuration::$cache = $before;
Configuration::$failKey = 'MAX';
if ($config->saveAll(['IDLE' => '180', 'MAX' => '240']) || Db::getInstance()->values !== $before || Configuration::$cache !== $before) {
    throw new RuntimeException('Partial write must roll back database and core cache.');
}
Configuration::$failKey = null; Db::getInstance()->failCommit = true;
if ($config->saveAll(['IDLE' => '180', 'MAX' => '240']) || Db::getInstance()->values !== $before || Configuration::$cache !== $before) {
    throw new RuntimeException('Commit failure must restore database and core cache.');
}
Db::getInstance()->failCommit = false;
if (!$config->saveAll(['IDLE' => '180', 'MAX' => '240']) || Db::getInstance()->values !== ['IDLE' => '180', 'MAX' => '240']) {
    throw new RuntimeException('Successful save must persist all values.');
}
echo 'PASS: atomic configuration write, commit failure and cache restoration' . PHP_EOL;

Shop::$active = false;
Db::getInstance()->values = Configuration::$cache = $before;
if (!$config->saveAll(['IDLE' => '180', 'MAX' => '240'])) {
    throw new RuntimeException('Single-shop global configuration must be saved.');
}
Db::getInstance()->values = Configuration::$cache = $before;
Configuration::$failKey = 'MAX';
if ($config->saveAll(['IDLE' => '180', 'MAX' => '240']) || Db::getInstance()->values !== $before || Configuration::$cache !== $before) {
    throw new RuntimeException('Single-shop rollback must restore global cache.');
}
echo 'PASS: single-shop global save and rollback cache scope' . PHP_EOL;
