<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Security\TokenService;

final class RateLimitService
{
    private \Context $context;
    private ModuleConfig $config;
    private TokenService $tokens;

    public function __construct(\Context $context, ModuleConfig $config, TokenService $tokens)
    {
        $this->context = $context;
        $this->config = $config;
        $this->tokens = $tokens;
    }

    public function isAllowed(string $ip, string $referenceHmac): bool
    {
        $ipHash = $this->tokens->ipHash($ip);
        $shop = (int) $this->context->shop->id;
        $db = \Db::getInstance();

        $ip15 = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_access_request` WHERE id_shop=' . $shop . " AND purpose='withdrawal_access'"
            . " AND request_ip_hash='" . pSQL($ipHash) . "' AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)"
        );
        $ipDay = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_access_request` WHERE id_shop=' . $shop . " AND purpose='withdrawal_access'"
            . " AND request_ip_hash='" . pSQL($ipHash) . "' AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)"
        );
        $orderHour = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_access_request` WHERE id_shop=' . $shop . " AND purpose='withdrawal_access'"
            . " AND order_reference_hmac='" . pSQL($referenceHmac) . "' AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)"
        );
        $orderDay = (int) $db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_access_request` WHERE id_shop=' . $shop . " AND purpose='withdrawal_access'"
            . " AND order_reference_hmac='" . pSQL($referenceHmac) . "' AND created_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)"
        );
        $last = (string) $db->getValue(
            'SELECT created_at FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_access_request` WHERE id_shop=' . $shop . " AND purpose='withdrawal_access'"
            . " AND order_reference_hmac='" . pSQL($referenceHmac) . "' ORDER BY id_access_request DESC"
        );
        $cooldown = $this->config->int('JOOIM_WITHDRAWAL_RESEND_COOLDOWN_SECONDS', 60);
        $cooldownOk = $last === '' || (time() - strtotime($last . ' UTC')) >= $cooldown;

        return $ip15 < $this->config->int('JOOIM_WITHDRAWAL_IP_15M_LIMIT', 5)
            && $ipDay < $this->config->int('JOOIM_WITHDRAWAL_IP_DAY_LIMIT', 20)
            && $orderHour < $this->config->int('JOOIM_WITHDRAWAL_ORDER_HOUR_LIMIT', 3)
            && $orderDay < $this->config->int('JOOIM_WITHDRAWAL_ORDER_DAY_LIMIT', 10)
            && $cooldownOk;
    }
}
