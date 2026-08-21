<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Security\TokenService;

final class GuestSessionService
{
    public const COOKIE = 'jooim_withdrawal_guest_session';

    private \Context $context;
    private ModuleConfig $config;
    private TokenService $tokens;

    public function __construct(\Context $context, ModuleConfig $config, TokenService $tokens)
    {
        $this->context = $context;
        $this->config = $config;
        $this->tokens = $tokens;
    }

    public function create(\Order $order): array
    {
        $plain = $this->tokens->randomToken(32);
        $hash = $this->tokens->hash($plain, 'guest_session');
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $idle = $now->modify('+' . $this->config->int('JOOIM_WITHDRAWAL_SESSION_IDLE_MINUTES', 30) . ' minutes');
        $absolute = $now->modify('+' . $this->config->int('JOOIM_WITHDRAWAL_SESSION_MAX_MINUTES', 120) . ' minutes');
        \Db::getInstance()->insert('jooim_withdrawal_guest_session', [
            'id_shop' => (int) $this->context->shop->id,
            'id_order' => (int) $order->id,
            'id_customer' => (int) $order->id_customer,
            'session_hash' => pSQL($hash),
            'purpose' => 'withdrawal_access',
            'last_activity_at' => $now->format('Y-m-d H:i:s'),
            'absolute_expires_at' => $absolute->format('Y-m-d H:i:s'),
            'expires_at' => $idle->format('Y-m-d H:i:s'),
            'created_at' => $now->format('Y-m-d H:i:s'),
        ]);
        $id = (int) \Db::getInstance()->Insert_ID();
        $this->setCookie($plain, $absolute->getTimestamp());
        return ['id_guest_session' => $id, 'plain' => $plain];
    }

    public function resolve(?int $requiredOrderId = null): ?array
    {
        $plain = (string) ($_COOKIE[self::COOKIE] ?? '');
        if ($plain === '') {
            return null;
        }
        $hash = $this->tokens->hash($plain, 'guest_session');
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_guest_session` WHERE session_hash=\'' . pSQL($hash) . '\''
            . ' AND id_shop=' . (int) $this->context->shop->id
            . ' AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP() AND absolute_expires_at > UTC_TIMESTAMP()';
        if ($requiredOrderId !== null) {
            $sql .= ' AND id_order=' . (int) $requiredOrderId;
        }
        $row = \Db::getInstance()->getRow($sql);
        if (!$row) {
            return null;
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $next = $now->modify('+' . $this->config->int('JOOIM_WITHDRAWAL_SESSION_IDLE_MINUTES', 30) . ' minutes');
        $absolute = new \DateTimeImmutable((string) $row['absolute_expires_at'], new \DateTimeZone('UTC'));
        if ($next > $absolute) {
            $next = $absolute;
        }
        \Db::getInstance()->update('jooim_withdrawal_guest_session', [
            'last_activity_at' => $now->format('Y-m-d H:i:s'),
            'expires_at' => $next->format('Y-m-d H:i:s'),
        ], 'id_guest_session=' . (int) $row['id_guest_session']);
        return $row;
    }

    public function revokeCurrent(): void
    {
        $plain = (string) ($_COOKIE[self::COOKIE] ?? '');
        if ($plain !== '') {
            $hash = $this->tokens->hash($plain, 'guest_session');
            \Db::getInstance()->update('jooim_withdrawal_guest_session', ['revoked_at' => gmdate('Y-m-d H:i:s')], "session_hash='" . pSQL($hash) . "'");
        }
        $this->setCookie('', time() - 3600);
    }

    private function setCookie(string $value, int $expires): void
    {
        if (headers_sent()) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) \Configuration::get('PS_SSL_ENABLED') === 1;
        setcookie(self::COOKIE, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        if ($value === '') {
            unset($_COOKIE[self::COOKIE]);
        } else {
            $_COOKIE[self::COOKIE] = $value;
        }
    }
}
