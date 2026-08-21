<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

final class WithdrawalReferenceGenerator
{
    private ModuleConfig $config;

    public function __construct(ModuleConfig $config)
    {
        $this->config = $config;
    }

    /** Must be called inside a transaction. */
    public function next(int $shopId, string $shopCode = ''): string
    {
        $year = (int) gmdate('Y');
        $db = \Db::getInstance();

        if (!$db->execute(
            'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'jooim_withdrawal_sequence` (id_shop,sequence_year,last_value) VALUES ('
            . (int) $shopId . ',' . $year . ',0)',
            false
        )) {
            throw $this->databaseFailure($db, 'initialize_reference_sequence');
        }

        // Db::getRow()/getValue() append LIMIT 1 after the supplied query in
        // PrestaShop 8. A query ending in FOR UPDATE would therefore become
        // "FOR UPDATE LIMIT 1", which is invalid on MariaDB/MySQL. executeS()
        // preserves the lock clause and cache is explicitly disabled.
        $rows = $db->executeS(
            'SELECT last_value FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_sequence` WHERE id_shop=' . (int) $shopId
            . ' AND sequence_year=' . $year . ' FOR UPDATE',
            true,
            false
        );
        if ($rows === false || !isset($rows[0]['last_value'])) {
            throw $this->databaseFailure($db, 'lock_reference_sequence');
        }

        $next = (int) $rows[0]['last_value'] + 1;
        if (!$db->update(
            'jooim_withdrawal_sequence',
            ['last_value' => $next],
            'id_shop=' . (int) $shopId . ' AND sequence_year=' . $year,
            1,
            false,
            false
        )) {
            throw $this->databaseFailure($db, 'advance_reference_sequence');
        }

        $prefix = strtoupper(preg_replace('/[^A-Z0-9-]/', '', $this->config->get('JOOIM_WITHDRAWAL_REFERENCE_PREFIX', 'WD')) ?: 'WD');
        $shopCode = strtoupper(preg_replace('/[^A-Z0-9]/', '', $shopCode));
        if ($shopCode === '') {
            $shopCode = 'S' . $shopId;
        }

        return sprintf('%s-%s-%04d-%06d', $prefix, $shopCode, $year, $next);
    }

    private function databaseFailure(\Db $db, string $stage): \RuntimeException
    {
        $message = trim((string) $db->getMsgError());
        if ($message === '') {
            $message = 'unknown database error';
        }

        return new \RuntimeException('Withdrawal reference database failure at ' . $stage . ': ' . $message);
    }
}
