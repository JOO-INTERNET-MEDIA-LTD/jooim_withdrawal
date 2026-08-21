<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Repository;

final class WithdrawalRepository
{
    public function find(int $id, ?int $shopId = null): ?array
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'jooim_withdrawal` WHERE id_jooim_withdrawal=' . (int) $id;
        if ($shopId !== null) {
            $sql .= ' AND id_shop=' . (int) $shopId;
        }
        $row = \Db::getInstance()->getRow($sql);
        return $row ?: null;
    }

    public function findByOrder(int $idOrder, int $shopId): array
    {
        return \Db::getInstance()->executeS(
            'SELECT id_jooim_withdrawal,reference,status_code,scope,submitted_at_utc,payload_hash FROM `' . _DB_PREFIX_ . 'jooim_withdrawal`'
            . ' WHERE id_order=' . (int) $idOrder . ' AND id_shop=' . (int) $shopId . ' ORDER BY id_jooim_withdrawal DESC'
        ) ?: [];
    }

    public function items(int $withdrawalId): array
    {
        return \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_item` WHERE id_jooim_withdrawal=' . (int) $withdrawalId . ' ORDER BY id_jooim_withdrawal_item'
        ) ?: [];
    }

    public function history(int $withdrawalId): array
    {
        return \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_history` WHERE id_jooim_withdrawal=' . (int) $withdrawalId . ' ORDER BY id_history'
        ) ?: [];
    }

    public function documents(int $withdrawalId): array
    {
        return \Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_document` WHERE id_jooim_withdrawal=' . (int) $withdrawalId . ' ORDER BY id_document'
        ) ?: [];
    }
}
