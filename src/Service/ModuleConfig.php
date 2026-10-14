<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

final class ModuleConfig
{
    private \Context $context;

    public function __construct(\Context $context)
    {
        $this->context = $context;
    }

    private function scope(): array
    {
        // PrestaShop stores single-shop configuration in the global context.
        if (!\Shop::isFeatureActive()) {
            return [0, 0];
        }
        return [(int) $this->context->shop->id_shop_group, (int) $this->context->shop->id];
    }

    public function get(string $key, $default = null): string
    {
        [$groupId, $shopId] = $this->scope();
        $value = \Configuration::get(
            $key,
            null,
            $groupId,
            $shopId
        );
        return $value === false ? (string) $default : (string) $value;
    }

    public function has(string $key): bool
    {
        [$groupId, $shopId] = $this->scope();
        return \Configuration::hasKey(
            $key,
            null,
            $groupId,
            $shopId
        );
    }

    public function set(string $key, string $value): bool
    {
        [$groupId, $shopId] = $this->scope();
        return (bool) \Configuration::updateValue(
            $key,
            $value,
            false,
            $groupId,
            $shopId
        );
    }

    /** Save one validated configuration atomically, restoring the core cache after rollback. */
    public function saveAll(array $values): bool
    {
        $previous = [];
        foreach ($values as $key => $value) {
            if (!$this->has((string) $key)) {
                return false;
            }
            $previous[$key] = $this->get((string) $key);
        }
        $db = \Db::getInstance();
        $started = false;
        try {
            if (!$db->execute('START TRANSACTION', false)) {
                return false;
            }
            $started = true;
            foreach ($values as $key => $value) {
                if (!$this->set((string) $key, (string) $value)) {
                    throw new \RuntimeException('Configuration write failed.');
                }
            }
            if (!$db->execute('COMMIT', false)) {
                throw new \RuntimeException('Configuration commit failed.');
            }
            return true;
        } catch (\Throwable $exception) {
            if ($started) {
                $db->execute('ROLLBACK', false);
            }
            // Configuration::set changes only the in-process core cache, not the database.
            foreach ($previous as $key => $value) {
                [$groupId, $shopId] = $this->scope();
                \Configuration::set($key, $value, $groupId, $shopId);
            }
            return false;
        }
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default ? '1' : '0');
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public function int(string $key, int $default): int
    {
        return (int) $this->get($key, (string) $default);
    }

    public function all(): array
    {
        $keys = [
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
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->get($key, '');
        }
        return $out;
    }

    public function merchantSnapshot(): array
    {
        return [
            'name' => $this->get('JOOIM_WITHDRAWAL_MERCHANT_NAME', (string) \Configuration::get('PS_SHOP_NAME')),
            'address' => $this->get('JOOIM_WITHDRAWAL_MERCHANT_ADDRESS', ''),
            'identifier' => $this->get('JOOIM_WITHDRAWAL_MERCHANT_ID', ''),
            'email' => $this->get('JOOIM_WITHDRAWAL_MERCHANT_EMAIL', (string) \Configuration::get('PS_SHOP_EMAIL')),
            'phone' => $this->get('JOOIM_WITHDRAWAL_MERCHANT_PHONE', ''),
            'return_address' => $this->get('JOOIM_WITHDRAWAL_RETURN_ADDRESS', ''),
        ];
    }
}
