<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

final class WithdrawalQuantityCalculator
{
    private const ACTIVE = [
        'submitted','acknowledged','awaiting_goods','goods_received','under_review',
        'refund_pending','partially_refunded','refunded'
    ];

    public function orderItems(\Order $order): array
    {
        $currency = new \Currency((int) $order->id_currency);
        $items = [];
        foreach ($order->getProducts() as $row) {
            $idDetail = (int) $row['id_order_detail'];
            $ordered = (int) $row['product_quantity'];
            $refunded = (int) ($row['product_quantity_refunded'] ?? 0);
            $returned = (int) ($row['product_quantity_return'] ?? 0);
            $already = $this->activeWithdrawalQuantity($idDetail);
            $available = max(0, $ordered - $refunded - $returned - $already);
            $items[] = [
                'id_order_detail' => $idDetail,
                'id_product' => (int) ($row['product_id'] ?? $row['id_product'] ?? 0),
                'id_product_attribute' => (int) ($row['product_attribute_id'] ?? $row['id_product_attribute'] ?? 0),
                'product_reference' => (string) ($row['product_reference'] ?? ''),
                'product_name' => (string) ($row['product_name'] ?? ''),
                'product_attribute_name' => (string) ($row['product_name'] ?? ''),
                'ordered_quantity' => $ordered,
                'available_quantity' => $available,
                'unit_price_tax_excl' => (float) ($row['unit_price_tax_excl'] ?? 0),
                'unit_price_tax_incl' => (float) ($row['unit_price_tax_incl'] ?? 0),
                'currency_iso' => (string) $currency->iso_code,
            ];
        }
        usort($items, static fn(array $a, array $b): int => $a['id_order_detail'] <=> $b['id_order_detail']);
        return $items;
    }

    public function activeWithdrawalQuantity(int $idOrderDetail): int
    {
        $quoted = array_map(static fn(string $s): string => "'" . pSQL($s) . "'", self::ACTIVE);
        return (int) \Db::getInstance()->getValue(
            'SELECT COALESCE(SUM(i.withdrawal_quantity),0) FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_item` i '
            . 'INNER JOIN `' . _DB_PREFIX_ . 'jooim_withdrawal` w ON w.id_jooim_withdrawal=i.id_jooim_withdrawal '
            . 'WHERE i.id_order_detail=' . (int) $idOrderDetail . ' AND w.status_code IN (' . implode(',', $quoted) . ')'
        );
    }
}
