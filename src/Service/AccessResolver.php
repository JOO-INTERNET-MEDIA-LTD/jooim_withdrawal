<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

final class AccessResolver
{
    private \Context $context;
    private GuestSessionService $guestSessions;

    public function __construct(\Context $context, GuestSessionService $guestSessions)
    {
        $this->context = $context;
        $this->guestSessions = $guestSessions;
    }

    public function forOrder(int $idOrder): ?array
    {
        $order = new \Order($idOrder);
        if (!\Validate::isLoadedObject($order) || (int) $order->id_shop !== (int) $this->context->shop->id) {
            return null;
        }
        if ($this->context->customer->isLogged() && (int) $order->id_customer === (int) $this->context->customer->id) {
            return ['order' => $order, 'source' => 'customer_account', 'guest_session' => null];
        }
        $session = $this->guestSessions->resolve($idOrder);
        if ($session && (int) $session['id_customer'] === (int) $order->id_customer) {
            return ['order' => $order, 'source' => 'guest_order_number', 'guest_session' => $session];
        }
        return null;
    }
}
