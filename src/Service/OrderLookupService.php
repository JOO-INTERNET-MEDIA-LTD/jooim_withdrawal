<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

final class OrderLookupService
{
    private \Context $context;
    private OrderReferenceNormalizer $normalizer;

    public function __construct(\Context $context, OrderReferenceNormalizer $normalizer)
    {
        $this->context = $context;
        $this->normalizer = $normalizer;
    }

    public function findByPublicReference(string $input): ?\Order
    {
        $reference = $this->normalizer->normalize($input);
        if ($reference === null) {
            return null;
        }
        $id = (int) \Db::getInstance()->getValue(
            'SELECT id_order FROM `' . _DB_PREFIX_ . 'orders` WHERE id_shop=' . (int) $this->context->shop->id .
            " AND UPPER(reference)='" . pSQL($reference) . "' ORDER BY id_order DESC"
        );
        if ($id <= 0) {
            return null;
        }
        $order = new \Order($id);
        return \Validate::isLoadedObject($order) ? $order : null;
    }

    public function normalize(string $input): ?string
    {
        return $this->normalizer->normalize($input);
    }
}
