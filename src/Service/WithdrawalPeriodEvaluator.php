<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

final class WithdrawalPeriodEvaluator
{
    /**
     * Advisory only. The delivery day itself is not counted; the deadline is treated
     * as the end of the Nth following calendar day in the shop timezone.
     */
    public function evaluate(
        ?string $deliveryDateUtc,
        ?string $submittedAtUtc,
        int $commercialDays = 14,
        string $shopTimezone = 'UTC'
    ): string {
        if (!$deliveryDateUtc || !$submittedAtUtc) {
            return 'unknown';
        }
        try {
            $utc = new \DateTimeZone('UTC');
            $localTz = new \DateTimeZone($shopTimezone ?: 'UTC');
            $delivery = (new \DateTimeImmutable($deliveryDateUtc, $utc))->setTimezone($localTz);
            $submitted = (new \DateTimeImmutable($submittedAtUtc, $utc))->setTimezone($localTz);
        } catch (\Throwable $e) {
            return 'unknown';
        }

        $days = max(14, $commercialDays);
        $deadline = $delivery->setTime(23, 59, 59)->modify('+' . $days . ' days');
        if ($submitted <= $deadline) {
            return $days > 14 ? 'commercial_extension' : 'within_period';
        }
        return 'possibly_expired';
    }
}
