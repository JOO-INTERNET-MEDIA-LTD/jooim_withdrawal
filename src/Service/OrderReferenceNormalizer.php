<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

final class OrderReferenceNormalizer
{
    public function normalize(string $value): ?string
    {
        $value = strtoupper(trim($value));
        if ($value === '' || strlen($value) > 64) {
            return null;
        }
        if (!preg_match('/^[A-Z0-9._-]+$/', $value)) {
            return null;
        }
        return $value;
    }
}
