<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

/** Validates the complete configuration before any value is persisted. */
final class ConfigurationValidator
{
    public function isValid(array $values): bool
    {
        foreach (['ENABLED', 'FOOTER_LINK', 'ACCOUNT_LINK', 'ORDER_BUTTON', 'DIRECT_FUNCTION', 'ATTACH_PDF'] as $name) {
            if (!in_array($values['JOOIM_WITHDRAWAL_' . $name] ?? '', ['0', '1'], true)) {
                return false;
            }
        }
        $ranges = [
            'MAGIC_TTL_MINUTES' => [5, 240], 'SESSION_IDLE_MINUTES' => [5, 240],
            'SESSION_MAX_MINUTES' => [15, 1440], 'RESEND_COOLDOWN_SECONDS' => [30, 3600],
            'IP_15M_LIMIT' => [1, 1000], 'IP_DAY_LIMIT' => [1, 10000],
            'ORDER_HOUR_LIMIT' => [1, 100], 'ORDER_DAY_LIMIT' => [1, 1000],
            'NOTE_MAX_LENGTH' => [250, 10000], 'RETRY_COUNT' => [0, 10],
        ];
        foreach ($ranges as $name => [$min, $max]) {
            $value = $values['JOOIM_WITHDRAWAL_' . $name] ?? '';
            if (!is_string($value) || !preg_match('/^\d+$/D', $value)
                || (int) $value < $min || (int) $value > $max) {
                return false;
            }
        }
        foreach ([['SESSION_IDLE_MINUTES', 'SESSION_MAX_MINUTES'], ['IP_15M_LIMIT', 'IP_DAY_LIMIT'], ['ORDER_HOUR_LIMIT', 'ORDER_DAY_LIMIT']] as [$smaller, $larger]) {
            if ((int) $values['JOOIM_WITHDRAWAL_' . $smaller] > (int) $values['JOOIM_WITHDRAWAL_' . $larger]) {
                return false;
            }
        }
        $prefix = $values['JOOIM_WITHDRAWAL_REFERENCE_PREFIX'] ?? '';
        if (!preg_match('/^[A-Za-z0-9-]{1,12}$/D', $prefix)) {
            return false;
        }
        $email = $values['JOOIM_WITHDRAWAL_MERCHANT_EMAIL'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            return false;
        }
        $url = $values['JOOIM_WITHDRAWAL_PRIVACY_URL'] ?? '';
        if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https' || strlen($url) > 2048)) {
            return false;
        }
        foreach (['MERCHANT_NAME' => 255, 'MERCHANT_ADDRESS' => 1000, 'MERCHANT_ID' => 255, 'MERCHANT_PHONE' => 64, 'RETURN_ADDRESS' => 2000] as $name => $max) {
            if (mb_strlen((string) ($values['JOOIM_WITHDRAWAL_' . $name] ?? '')) > $max) {
                return false;
            }
        }
        return trim((string) ($values['JOOIM_WITHDRAWAL_MERCHANT_NAME'] ?? '')) !== '';
    }
}
