<?php

declare(strict_types=1);

if (!class_exists('Module')) {
    class Module
    {
    }
}

require_once dirname(__DIR__) . '/src/Service/WithdrawalStatusPresenter.php';

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\WithdrawalStatusPresenter;

final class StatusTestModule extends Module
{
    public function jooimTranslate($id, array $parameters = [], $domain = null, $locale = null): string
    {
        return '[' . (string) ($locale ?? 'context') . '][' . (string) $domain . '] ' . (string) $id;
    }
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$module = new StatusTestModule();
$codes = WithdrawalStatusPresenter::codes();
$assert(count($codes) === 11, 'expected 11 stable status codes');
$assert(count(array_unique($codes)) === 11, 'status codes must be unique');
$assert($codes[0] === 'submitted' && $codes[10] === 'duplicate', 'status order changed unexpectedly');

foreach ($codes as $code) {
    $source = WithdrawalStatusPresenter::labelSource($code);
    $assert($source !== '' && $source !== $code, 'raw code leaked as source label: ' . $code);
    $assert(WithdrawalStatusPresenter::isValid($code), 'known status rejected: ' . $code);
    $assert(WithdrawalStatusPresenter::badgeClass($code) !== '', 'badge class missing: ' . $code);
}

$options = WithdrawalStatusPresenter::options(
    $module,
    WithdrawalStatusPresenter::ADMIN_DOMAIN,
    'sk-SK'
);
$assert(count($options) === 11, 'localized status options incomplete');
$assert(($options[0]['code'] ?? '') === 'submitted', 'option code changed');
$assert(str_contains((string) ($options[0]['label'] ?? ''), 'Withdrawal received'), 'option label source missing');
$assert(str_contains((string) ($options[0]['label'] ?? ''), 'sk-SK'), 'explicit locale not passed');

$row = WithdrawalStatusPresenter::decorateRow($module, ['status_code' => 'refunded']);
$assert(($row['status_badge_class'] ?? '') === 'success', 'refunded badge must be success');
$assert(str_contains((string) ($row['status_label'] ?? ''), 'Refund completed'), 'decorated row label missing');

$history = WithdrawalStatusPresenter::decorateHistoryRows($module, [[
    'event_code' => 'admin_status_changed',
    'old_status_code' => 'submitted',
    'new_status_code' => 'under_review',
]]);
$assert(str_contains((string) ($history[0]['event_label'] ?? ''), 'Status changed by administrator'), 'event label missing');
$assert(str_contains((string) ($history[0]['old_status_label'] ?? ''), 'Withdrawal received'), 'old status label missing');
$assert(str_contains((string) ($history[0]['new_status_label'] ?? ''), 'Under review'), 'new status label missing');

$assert(WithdrawalStatusPresenter::labelSource('') === '', 'empty status must remain empty');
$assert(WithdrawalStatusPresenter::labelSource('custom_future_status') === 'Custom future status', 'unknown status fallback is unreadable');

if ($failures !== []) {
    fwrite(STDERR, "FAIL: " . count($failures) . PHP_EOL);
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . PHP_EOL);
    }
    exit(1);
}

fwrite(STDOUT, "PASS: localized status presenter, options, rows and history\n");
