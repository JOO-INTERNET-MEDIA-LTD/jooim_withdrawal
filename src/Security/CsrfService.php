<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Security;

/**
 * Browser-bound CSRF protection that deliberately does not use PrestaShop's
 * encrypted main cookie.
 *
 * Large shops can already be close to PrestaShop's hard cookie-size limit.
 * Adding a token to Context::cookie may therefore make even a simple form
 * fail before rendering. This service uses one small, host-only HttpOnly
 * cookie and a short-lived token signed with the module secret instead.
 */
final class CsrfService
{
    private const COOKIE_NAME = 'jooim_withdrawal_csrf';
    private const COOKIE_TTL_SECONDS = 14400;
    private const TOKEN_TTL_SECONDS = 1800;
    private const TOKEN_VERSION = 1;

    private \Context $context;
    private TokenService $tokens;

    public function __construct(\Context $context, TokenService $tokens)
    {
        $this->context = $context;
        $this->tokens = $tokens;
    }

    public function issue(string $purpose): string
    {
        $seed = $this->browserSeed();
        $now = time();
        $payload = json_encode([
            'v' => self::TOKEN_VERSION,
            'p' => hash('sha256', $purpose),
            's' => $this->shopId(),
            'iat' => $now,
            'exp' => $now + self::TOKEN_TTL_SECONDS,
            'n' => bin2hex(random_bytes(16)),
        ], JSON_UNESCAPED_SLASHES);

        if (!is_string($payload)) {
            throw new \RuntimeException('Unable to encode the withdrawal CSRF token.');
        }

        $encoded = $this->base64UrlEncode($payload);
        $signature = $this->tokens->hash($seed . "\0" . $encoded, 'csrf:v2');

        return 'v1.' . $encoded . '.' . $signature;
    }

    public function validate(string $purpose, string $plain, bool $consume = false): bool
    {
        // $consume is retained for call-site compatibility. The token itself is
        // short-lived and browser-bound. Submission idempotency and access-link
        // one-time semantics are enforced separately by their domain services.
        unset($consume);

        $seed = $this->readBrowserSeed();
        if ($seed === '' || $plain === '') {
            return false;
        }

        $parts = explode('.', $plain, 3);
        if (count($parts) !== 3 || $parts[0] !== 'v1') {
            return false;
        }

        [$version, $encoded, $signature] = $parts;
        unset($version);

        if (!preg_match('/^[A-Za-z0-9_-]{20,2048}$/D', $encoded)
            || !preg_match('/^[a-f0-9]{64}$/D', $signature)) {
            return false;
        }

        $expected = $this->tokens->hash($seed . "\0" . $encoded, 'csrf:v2');
        if (!hash_equals($expected, $signature)) {
            return false;
        }

        $decoded = $this->base64UrlDecode($encoded);
        if ($decoded === null) {
            return false;
        }

        $payload = json_decode($decoded, true);
        if (!is_array($payload)) {
            return false;
        }

        $now = time();
        $issuedAt = (int) ($payload['iat'] ?? 0);
        $expiresAt = (int) ($payload['exp'] ?? 0);

        return (int) ($payload['v'] ?? 0) === self::TOKEN_VERSION
            && hash_equals(hash('sha256', $purpose), (string) ($payload['p'] ?? ''))
            && (int) ($payload['s'] ?? 0) === $this->shopId()
            && $issuedAt > 0
            && $issuedAt <= ($now + 60)
            && $expiresAt >= $now
            && $expiresAt <= ($issuedAt + self::TOKEN_TTL_SECONDS + 60)
            && isset($payload['n'])
            && preg_match('/^[a-f0-9]{32}$/D', (string) $payload['n']) === 1;
    }

    private function browserSeed(): string
    {
        $seed = $this->readBrowserSeed();
        if ($seed !== '') {
            // Sliding expiry prevents a newly issued 30-minute token from
            // outliving a nearly expired browser-binding cookie.
            if (!headers_sent()) {
                $this->writeBrowserSeed($seed);
            }
            return $seed;
        }

        if (headers_sent($file, $line)) {
            throw new \RuntimeException(sprintf(
                'Unable to initialize the withdrawal CSRF cookie because headers were already sent at %s:%d.',
                basename((string) $file),
                (int) $line
            ));
        }

        $seed = bin2hex(random_bytes(32));
        $this->writeBrowserSeed($seed);

        // Make the new value available to additional forms created during the
        // same request; the browser will return it on the following request.
        $_COOKIE[self::COOKIE_NAME] = $seed;

        return $seed;
    }

    private function writeBrowserSeed(string $seed): void
    {
        $written = setcookie(self::COOKIE_NAME, $seed, [
            'expires' => time() + self::COOKIE_TTL_SECONDS,
            'path' => '/',
            'secure' => $this->isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (!$written) {
            throw new \RuntimeException('Unable to initialize the withdrawal CSRF cookie.');
        }
    }

    private function readBrowserSeed(): string
    {
        $seed = isset($_COOKIE[self::COOKIE_NAME]) ? (string) $_COOKIE[self::COOKIE_NAME] : '';
        return preg_match('/^[a-f0-9]{64}$/D', $seed) === 1 ? $seed : '';
    }

    private function shopId(): int
    {
        return isset($this->context->shop) ? (int) $this->context->shop->id : 0;
    }

    private function isSecureRequest(): bool
    {
        if (class_exists('Tools') && method_exists('Tools', 'usingSecureMode')) {
            return (bool) \Tools::usingSecureMode();
        }

        return isset($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        return is_string($decoded) ? $decoded : null;
    }
}
