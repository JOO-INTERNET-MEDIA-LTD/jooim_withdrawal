<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

final class OrderIdentityService
{
    private \Context $context;

    public function __construct(\Context $context)
    {
        $this->context = $context;
    }

    public function capture(\Order $order): void
    {
        $customer = new \Customer((int) $order->id_customer);
        if (!\Validate::isLoadedObject($customer)) {
            return;
        }
        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'jooim_withdrawal_order_identity` '
            . '(id_shop,id_order,id_customer,order_reference,firstname_snapshot,lastname_snapshot,email_snapshot,created_at) VALUES ('
            . (int) $order->id_shop . ','
            . (int) $order->id . ','
            . (int) $order->id_customer . ",'" . pSQL((string) $order->reference) . "','"
            . pSQL((string) $customer->firstname) . "','"
            . pSQL((string) $customer->lastname) . "','"
            . pSQL((string) $customer->email) . "','"
            . pSQL(gmdate('Y-m-d H:i:s')) . "') "
            . 'ON DUPLICATE KEY UPDATE order_reference=VALUES(order_reference)';
        \Db::getInstance()->execute($sql);
    }

    public function resolve(\Order $order): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT firstname_snapshot,lastname_snapshot,email_snapshot FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_order_identity`'
            . ' WHERE id_shop=' . (int) $order->id_shop . ' AND id_order=' . (int) $order->id
        );
        if ($row && \Validate::isEmail((string) $row['email_snapshot'])) {
            return [
                'firstname' => (string) $row['firstname_snapshot'],
                'lastname' => (string) $row['lastname_snapshot'],
                'email' => (string) $row['email_snapshot'],
                'source' => 'order_snapshot',
            ];
        }
        $customer = new \Customer((int) $order->id_customer);
        if (\Validate::isLoadedObject($customer) && \Validate::isEmail((string) $customer->email)) {
            return [
                'firstname' => (string) $customer->firstname,
                'lastname' => (string) $customer->lastname,
                'email' => (string) $customer->email,
                'source' => 'customer_current',
            ];
        }
        return null;
    }
}
