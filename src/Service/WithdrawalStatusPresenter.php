<?php

namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

/**
 * Presents stable withdrawal status/event codes as localized human labels.
 *
 * Database values remain immutable technical identifiers. Only presentation
 * layers (Back Office, customer status page and email) use translated labels.
 */
final class WithdrawalStatusPresenter
{
    public const ADMIN_DOMAIN = 'Modules.Jooimwithdrawal.Admin';
    public const SHOP_DOMAIN = 'Modules.Jooimwithdrawal.Shop';
    public const EMAIL_DOMAIN = 'Modules.Jooimwithdrawal.Email';

    /** @var array<string,string> */
    private const LABEL_SOURCES = [
        'submitted' => 'Withdrawal received',
        'acknowledged' => 'Confirmation sent',
        'awaiting_goods' => 'Waiting for returned goods',
        'goods_received' => 'Returned goods received',
        'under_review' => 'Under review',
        'refund_pending' => 'Refund pending',
        'partially_refunded' => 'Partially refunded',
        'refunded' => 'Refund completed',
        'rejected' => 'Withdrawal rejected',
        'closed' => 'Case closed',
        'duplicate' => 'Duplicate submission',
    ];

    /** @var array<string,string> */
    private const BADGE_CLASSES = [
        'submitted' => 'info',
        'acknowledged' => 'primary',
        'awaiting_goods' => 'warning',
        'goods_received' => 'info',
        'under_review' => 'warning',
        'refund_pending' => 'warning',
        'partially_refunded' => 'info',
        'refunded' => 'success',
        'rejected' => 'danger',
        'closed' => 'default',
        'duplicate' => 'default',
    ];

    /** @var array<string,string> */
    private const EVENT_LABEL_SOURCES = [
        'withdrawal_submitted' => 'Withdrawal submitted',
        'admin_status_changed' => 'Status changed by administrator',
        'refund_recorded' => 'Refund recorded',
        'acknowledgement_retry_sent' => 'Acknowledgement sent again',
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::LABEL_SOURCES);
    }

    public static function isValid(string $code): bool
    {
        return isset(self::LABEL_SOURCES[$code]);
    }

    public static function labelSource(string $code): string
    {
        if ($code === '') {
            return '';
        }

        return self::LABEL_SOURCES[$code] ?? self::humanizeUnknownCode($code);
    }

    public static function label(
        \Module $module,
        string $code,
        string $domain = self::ADMIN_DOMAIN,
        ?string $locale = null
    ): string {
        $source = self::labelSource($code);
        if ($source === '') {
            return '';
        }

        if (method_exists($module, 'jooimTranslate')) {
            return (string) $module->jooimTranslate($source, [], $domain, $locale);
        }

        return $source;
    }

    /**
     * @return list<array{code:string,label:string,badge_class:string}>
     */
    public static function options(
        \Module $module,
        string $domain = self::ADMIN_DOMAIN,
        ?string $locale = null
    ): array {
        $options = [];
        foreach (self::codes() as $code) {
            $options[] = [
                'code' => $code,
                'label' => self::label($module, $code, $domain, $locale),
                'badge_class' => self::badgeClass($code),
            ];
        }

        return $options;
    }

    public static function badgeClass(string $code): string
    {
        return self::BADGE_CLASSES[$code] ?? 'default';
    }

    public static function eventLabel(
        \Module $module,
        string $eventCode,
        string $domain = self::ADMIN_DOMAIN,
        ?string $locale = null
    ): string {
        if ($eventCode === '') {
            return '';
        }

        $source = self::EVENT_LABEL_SOURCES[$eventCode] ?? self::humanizeUnknownCode($eventCode);
        if (method_exists($module, 'jooimTranslate')) {
            return (string) $module->jooimTranslate($source, [], $domain, $locale);
        }

        return $source;
    }

    /** @param array<string,mixed> $row
     *  @return array<string,mixed>
     */
    public static function decorateRow(
        \Module $module,
        array $row,
        string $domain = self::ADMIN_DOMAIN,
        ?string $locale = null
    ): array {
        $code = (string) ($row['status_code'] ?? '');
        $row['status_label'] = self::label($module, $code, $domain, $locale);
        $row['status_badge_class'] = self::badgeClass($code);

        return $row;
    }

    /** @param array<int,array<string,mixed>> $rows
     *  @return array<int,array<string,mixed>>
     */
    public static function decorateRows(
        \Module $module,
        array $rows,
        string $domain = self::ADMIN_DOMAIN,
        ?string $locale = null
    ): array {
        foreach ($rows as $index => $row) {
            $rows[$index] = self::decorateRow($module, $row, $domain, $locale);
        }

        return $rows;
    }

    /** @param array<int,array<string,mixed>> $rows
     *  @return array<int,array<string,mixed>>
     */
    public static function decorateHistoryRows(
        \Module $module,
        array $rows,
        string $domain = self::ADMIN_DOMAIN,
        ?string $locale = null
    ): array {
        foreach ($rows as $index => $row) {
            $rows[$index]['event_label'] = self::eventLabel(
                $module,
                (string) ($row['event_code'] ?? ''),
                $domain,
                $locale
            );
            $rows[$index]['old_status_label'] = self::label(
                $module,
                (string) ($row['old_status_code'] ?? ''),
                $domain,
                $locale
            );
            $rows[$index]['new_status_label'] = self::label(
                $module,
                (string) ($row['new_status_code'] ?? ''),
                $domain,
                $locale
            );
        }

        return $rows;
    }

    private static function humanizeUnknownCode(string $code): string
    {
        $label = trim(str_replace(['_', '-'], ' ', $code));

        return $label === '' ? $code : ucfirst($label);
    }
}
