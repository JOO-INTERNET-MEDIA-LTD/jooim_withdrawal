<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Security;

final class TokenService
{
    private string $secret;

    public function __construct()
    {
        $secret = (string) \Configuration::get('JOOIM_WITHDRAWAL_SECRET');
        if ($secret === '') {
            $generated = bin2hex(random_bytes(32));
            $updated = method_exists(\Configuration::class, 'updateGlobalValue')
                ? \Configuration::updateGlobalValue('JOOIM_WITHDRAWAL_SECRET', $generated)
                : \Configuration::updateValue('JOOIM_WITHDRAWAL_SECRET', $generated, false, 0, 0);
            $secret = $updated ? (string) \Configuration::get('JOOIM_WITHDRAWAL_SECRET') : '';
            if ($secret === '') {
                $updated = \Configuration::updateValue('JOOIM_WITHDRAWAL_SECRET', $generated);
                $secret = $updated ? (string) \Configuration::get('JOOIM_WITHDRAWAL_SECRET') : '';
            }
        }
        if ($secret === '' && defined('_COOKIE_KEY_') && (string) _COOKIE_KEY_ !== '') {
            $secret = hash_hmac(
                'sha256',
                'jooim_withdrawal:module-secret:v1',
                (string) _COOKIE_KEY_
            );
        }
        if ($secret === '') {
            throw new \RuntimeException('JOOIM withdrawal module secret is missing and could not be initialized.');
        }
        $this->secret = $secret;
    }

    public function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public function hash(string $plain, string $purpose = 'generic'): string
    {
        return hash_hmac('sha256', $purpose . "\0" . $plain, $this->secret);
    }

    public function referenceHmac(string $normalizedReference, int $shopId): string
    {
        return $this->hash($shopId . ':' . $normalizedReference, 'order_reference');
    }

    public function ipHash(string $ip, ?\DateTimeImmutable $date = null): string
    {
        $date = $date ?: new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $dailyKey = hash_hmac('sha256', 'ip:' . $date->format('Y-m-d'), $this->secret, true);
        return hash_hmac('sha256', $ip, $dailyKey);
    }

    public function userAgentHash(string $ua): string
    {
        return $this->hash(substr($ua, 0, 1024), 'ua');
    }

    public function issueReceipt(int $withdrawalId, int $shopId, int $ttlSeconds = 7200): string
    {
        $payload = json_encode([
            'wid' => $withdrawalId,
            'sid' => $shopId,
            'exp' => time() + $ttlSeconds,
            'rnd' => bin2hex(random_bytes(8)),
        ], JSON_UNESCAPED_SLASHES);
        $encoded = rtrim(strtr(base64_encode((string) $payload), '+/', '-_'), '=');
        return $encoded . '.' . $this->hash($encoded, 'receipt');
    }

    public function validateReceipt(string $token, int $shopId): ?int
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || !hash_equals($this->hash($parts[0], 'receipt'), $parts[1])) {
            return null;
        }
        $payload = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/')), true);
        if (!is_array($payload) || (int) ($payload['sid'] ?? 0) !== $shopId || (int) ($payload['exp'] ?? 0) < time()) {
            return null;
        }
        return (int) ($payload['wid'] ?? 0) ?: null;
    }
}
