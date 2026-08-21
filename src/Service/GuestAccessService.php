<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Security\TokenService;

final class GuestAccessService
{
    private \Context $context;
    private \Module $module;
    private ModuleConfig $config;
    private TokenService $tokens;
    private OrderLookupService $lookup;
    private OrderIdentityService $identities;
    private RateLimitService $rateLimit;
    private GuestSessionService $sessions;
    private WithdrawalMailService $mail;

    public function __construct(\Context $context, \Module $module, ModuleConfig $config, TokenService $tokens, OrderLookupService $lookup, OrderIdentityService $identities, RateLimitService $rateLimit, GuestSessionService $sessions, WithdrawalMailService $mail)
    {
        $this->context = $context;
        $this->module = $module;
        $this->config = $config;
        $this->tokens = $tokens;
        $this->lookup = $lookup;
        $this->identities = $identities;
        $this->rateLimit = $rateLimit;
        $this->sessions = $sessions;
        $this->mail = $mail;
    }

    public function request(string $input, string $ip, string $ua): void
    {
        $normalized = $this->lookup->normalize($input) ?? strtoupper(trim(substr($input, 0, 64)));
        $shopId = (int) $this->context->shop->id;
        $refHmac = $this->tokens->referenceHmac($normalized, $shopId);
        $allowed = $this->rateLimit->isAllowed($ip, $refHmac);
        $order = $allowed ? $this->lookup->findByPublicReference($input) : null;
        $identity = $order ? $this->identities->resolve($order) : null;
        $plainToken = ($order && $identity) ? $this->tokens->randomToken(32) : null;
        $tokenHash = $plainToken ? $this->tokens->hash($plainToken, 'withdrawal_access') : null;
        $expires = $plainToken ? gmdate('Y-m-d H:i:s', time() + $this->config->int('JOOIM_WITHDRAWAL_MAGIC_TTL_MINUTES', 30) * 60) : null;

        \Db::getInstance()->insert('jooim_withdrawal_access_request', [
            'id_shop' => $shopId,
            'id_order' => $order ? (int) $order->id : null,
            'order_reference_hmac' => pSQL($refHmac),
            'token_hash' => $tokenHash ? pSQL($tokenHash) : null,
            'purpose' => 'withdrawal_access',
            'email_source' => $identity['source'] ?? null,
            'expires_at' => $expires,
            'request_ip_hash' => pSQL($this->tokens->ipHash($ip)),
            'request_user_agent_hash' => pSQL($this->tokens->userAgentHash($ua)),
            'mail_status' => $plainToken ? 'pending' : ($allowed ? 'not_required' : 'rate_limited'),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ], true);
        $idAccess = (int) \Db::getInstance()->Insert_ID();

        if ($plainToken && $identity && $order) {
            $url = $this->context->link->getModuleLink($this->module->name, 'verify', ['t' => $plainToken], true);
            $ok = $this->mail->sendAccessLink((int) $order->id_lang, $shopId, (string) $identity['email'], (string) $identity['firstname'], $url, $idAccess);
            \Db::getInstance()->update('jooim_withdrawal_access_request', ['mail_status' => $ok ? 'sent' : 'failed'], 'id_access_request=' . $idAccess);
        }
    }

    public function peek(string $plainToken): ?array
    {
        $hash = $this->tokens->hash($plainToken, 'withdrawal_access');
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_access_request` WHERE id_shop=' . (int) $this->context->shop->id
            . " AND token_hash='" . pSQL($hash) . "' AND purpose='withdrawal_access' AND used_at IS NULL AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP()"
            . ' ORDER BY id_access_request DESC'
        );
        if (!$row) {
            return null;
        }
        if (empty($row['opened_at'])) {
            \Db::getInstance()->update('jooim_withdrawal_access_request', ['opened_at' => gmdate('Y-m-d H:i:s')], 'id_access_request=' . (int) $row['id_access_request']);
        }
        return $row;
    }

    public function consume(string $plainToken): ?array
    {
        $row = $this->peek($plainToken);
        if (!$row || empty($row['id_order'])) {
            return null;
        }
        $db = \Db::getInstance();
        $db->execute('START TRANSACTION');
        try {
            $updated = $db->execute(
                'UPDATE `' . _DB_PREFIX_ . 'jooim_withdrawal_access_request` SET used_at=UTC_TIMESTAMP() WHERE id_access_request=' . (int) $row['id_access_request']
                . ' AND used_at IS NULL AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP()'
            );
            if (!$updated || $db->Affected_Rows() !== 1) {
                throw new \RuntimeException('Token is no longer valid.');
            }
            $order = new \Order((int) $row['id_order']);
            if (!\Validate::isLoadedObject($order) || (int) $order->id_shop !== (int) $this->context->shop->id) {
                throw new \RuntimeException('Order access is invalid.');
            }
            $session = $this->sessions->create($order);
            $db->execute('COMMIT');
            return ['order' => $order, 'session' => $session];
        } catch (\Throwable $e) {
            $db->execute('ROLLBACK');
            return null;
        }
    }
}
