<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Repository\WithdrawalRepository;

/** Bounded recovery of post-commit work; called under the maintenance lock. */
final class WithdrawalRecoveryService
{
    private \Context $context;
    private ModuleConfig $config;
    private WithdrawalRepository $repo;
    private WithdrawalPdfService $pdf;

    public function __construct(\Context $context, ModuleConfig $config, WithdrawalRepository $repo, WithdrawalPdfService $pdf)
    {
        $this->context = $context;
        $this->config = $config;
        $this->repo = $repo;
        $this->pdf = $pdf;
    }

    public function run(): array
    {
        $result = ['documents_recovered' => 0, 'events_retried' => 0];
        $originalShop = $this->context->shop;
        $originalLanguage = $this->context->language;
        $shops = \Shop::isFeatureActive()
            ? array_map('intval', (array) \Shop::getShops(false, null, true))
            : [(int) $originalShop->id];
        try {
            foreach (array_unique(array_filter($shops)) as $idShop) {
                $shop = new \Shop($idShop);
                if (!\Validate::isLoadedObject($shop)) {
                    continue;
                }
                $this->context->shop = $shop;
                $retryCount = max(0, min(10, $this->config->int('JOOIM_WITHDRAWAL_RETRY_COUNT', 3)));
                if ($retryCount === 0) {
                    continue;
                }
                $result['documents_recovered'] += $this->recoverDocuments($idShop, $retryCount);
                $result['events_retried'] += $this->retryEvents($idShop, $retryCount);
            }
        } finally {
            $this->context->shop = $originalShop;
            $this->context->language = $originalLanguage;
        }
        return $result;
    }

    private function recoverDocuments(int $idShop, int $retries): int
    {
        $db = \Db::getInstance();
        $rows = $db->executeS(
            'SELECT w.* FROM `' . _DB_PREFIX_ . 'jooim_withdrawal` w WHERE w.id_shop=' . $idShop
            . ' AND w.created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)'
            . ' AND NOT EXISTS (SELECT 1 FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_document` d WHERE d.id_jooim_withdrawal=w.id_jooim_withdrawal)'
            . ' AND (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_history` h WHERE h.id_jooim_withdrawal=w.id_jooim_withdrawal AND h.event_code=\'document_recovery_failed\') < ' . $retries
            . ' AND NOT EXISTS (SELECT 1 FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_history` h WHERE h.id_jooim_withdrawal=w.id_jooim_withdrawal AND h.event_code=\'document_recovery_failed\' AND h.created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE))'
            . ' ORDER BY w.id_jooim_withdrawal LIMIT 20', true, false
        );
        if ($rows === false) {
            throw new \RuntimeException('Document recovery lookup failed.');
        }
        $recovered = 0;
        foreach ($rows as $withdrawal) {
            $id = (int) $withdrawal['id_jooim_withdrawal'];
            try {
                $this->setLanguage((int) $withdrawal['id_lang']);
                $snapshot = json_decode((string) $withdrawal['payload_json'], true);
                if (!is_array($snapshot)) {
                    throw new \RuntimeException('Invalid stored snapshot.');
                }
                $document = $this->pdf->generate($withdrawal, $this->repo->items($id), $snapshot);
                $ok = $db->insert('jooim_withdrawal_document', [
                    'id_jooim_withdrawal' => $id, 'document_type' => 'acknowledgement_pdf',
                    'document_reference' => pSQL($withdrawal['reference'] . '-ACK'),
                    'storage_path' => pSQL($document['path']), 'mime_type' => 'application/pdf',
                    'file_size' => (int) $document['size'], 'file_hash' => pSQL($document['hash']),
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ], true, false);
                if (!$ok) {
                    throw new \RuntimeException('Document recovery write failed.');
                }
                ++$recovered;
                $this->history($id, 'document_recovered');
            } catch (\Throwable $exception) {
                $this->history($id, 'document_recovery_failed');
            }
        }
        return $recovered;
    }

    private function retryEvents(int $idShop, int $retries): int
    {
        $db = \Db::getInstance();
        $rows = $db->executeS(
            'SELECT e.* FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_event_outbox` e'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'jooim_withdrawal` w ON w.id_jooim_withdrawal=e.aggregate_id'
            . ' WHERE w.id_shop=' . $idShop . " AND e.aggregate_type='withdrawal' AND e.event_name='actionJooimWithdrawalCreated'"
            . " AND e.status IN ('pending','failed') AND e.attempt_count < " . (1 + $retries)
            . ' AND e.created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE)'
            . ' AND (e.next_attempt_at IS NULL OR e.next_attempt_at <= UTC_TIMESTAMP())'
            . ' ORDER BY e.id_event LIMIT 20', true, false
        );
        if ($rows === false) {
            throw new \RuntimeException('Event recovery lookup failed.');
        }
        $retried = 0;
        foreach ($rows as $event) {
            $id = (int) $event['id_event'];
            ++$retried;
            try {
                $withdrawal = $this->repo->find((int) $event['aggregate_id'], $idShop);
                if (!$withdrawal) {
                    throw new \RuntimeException('Event withdrawal not found.');
                }
                $this->setLanguage((int) $withdrawal['id_lang']);
                $snapshot = json_decode((string) $withdrawal['payload_json'], true);
                if (!is_array($snapshot)) {
                    throw new \RuntimeException('Invalid stored snapshot.');
                }
                // Delivery is at least once; integrations must be idempotent by withdrawal ID.
                $level = ob_get_level();
                ob_start();
                try {
                    \Hook::exec('actionJooimWithdrawalCreated', ['withdrawal' => $withdrawal, 'snapshot' => $snapshot]);
                } finally {
                    while (ob_get_level() > $level) {
                        ob_end_clean();
                    }
                }
                $ok = $db->update('jooim_withdrawal_event_outbox', [
                    'status' => 'processed', 'processed_at' => gmdate('Y-m-d H:i:s'),
                    'next_attempt_at' => null,
                    'attempt_count' => ['type' => 'sql', 'value' => '`attempt_count` + 1'],
                ], 'id_event=' . $id, 0, true, false);
                if (!$ok) {
                    throw new \RuntimeException('Event recovery write failed.');
                }
            } catch (\Throwable $exception) {
                if (!$db->update('jooim_withdrawal_event_outbox', [
                    'status' => 'failed', 'next_attempt_at' => gmdate('Y-m-d H:i:s', time() + 300),
                    'attempt_count' => ['type' => 'sql', 'value' => '`attempt_count` + 1'],
                ], 'id_event=' . $id, 0, true, false)) {
                    throw new \RuntimeException('Cannot persist event retry failure.');
                }
            }
        }
        return $retried;
    }

    private function setLanguage(int $id): void
    {
        $language = new \Language($id);
        if (\Validate::isLoadedObject($language)) {
            $this->context->language = $language;
        }
    }

    private function history(int $id, string $event): void
    {
        if (!\Db::getInstance()->insert('jooim_withdrawal_history', [
            'id_jooim_withdrawal' => $id, 'event_code' => $event, 'created_at' => gmdate('Y-m-d H:i:s'),
        ], true, false)) {
            throw new \RuntimeException('Cannot persist document recovery history.');
        }
    }
}
