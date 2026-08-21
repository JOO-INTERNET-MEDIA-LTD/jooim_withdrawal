<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Repository\WithdrawalRepository;

final class WithdrawalMaintenanceService
{
    private \Context $context;
    private ModuleConfig $config;
    private WithdrawalRepository $repo;
    private WithdrawalMailService $mail;
    private StorageService $storage;

    public function __construct(\Context $context, ModuleConfig $config, WithdrawalRepository $repo, WithdrawalMailService $mail, StorageService $storage)
    {
        $this->context = $context;
        $this->config = $config;
        $this->repo = $repo;
        $this->mail = $mail;
        $this->storage = $storage;
    }

    public function run(): array
    {
        $db = \Db::getInstance();
        $result = [];

        // Technical/security records only. Legally relevant submissions are never deleted here.
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_access_request` WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY) AND (used_at IS NULL OR used_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY))');
        $result['access_requests_deleted'] = $db->Affected_Rows();
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_guest_session` WHERE absolute_expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)');
        $result['guest_sessions_deleted'] = $db->Affected_Rows();
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_draft` WHERE expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY) AND submitted_at IS NULL');
        $result['drafts_deleted'] = $db->Affected_Rows();

        $result['acknowledgements_retried'] = $this->retryAcknowledgements();
        return $result;
    }

    private function retryAcknowledgements(): int
    {
        $retried = 0;
        $originalShop = $this->context->shop;
        $originalLanguage = $this->context->language;
        $shopIds = \Shop::isFeatureActive()
            ? array_map('intval', (array) \Shop::getShops(false, null, true))
            : [(int) $this->context->shop->id];

        try {
            foreach (array_values(array_unique(array_filter($shopIds))) as $shopId) {
                $shop = new \Shop((int) $shopId);
                if (!\Validate::isLoadedObject($shop)) {
                    continue;
                }
                $this->context->shop = $shop;
                $retryCount = max(0, $this->config->int('JOOIM_WITHDRAWAL_RETRY_COUNT', 3));
                if ($retryCount === 0) {
                    continue;
                }
                $maxAttempts = 1 + $retryCount;
                $rows = \Db::getInstance()->executeS(
                    'SELECT w.* FROM `' . _DB_PREFIX_ . 'jooim_withdrawal` w '
                    . 'WHERE w.id_shop=' . (int) $shopId . ' AND w.acknowledgement_sent_at IS NULL '
                    . 'AND w.created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 5 MINUTE) '
                    . 'AND (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_mail` m WHERE m.id_jooim_withdrawal=w.id_jooim_withdrawal AND m.template_code=\'withdrawal_acknowledgement\') < ' . $maxAttempts
                    . 'AND NOT EXISTS (SELECT 1 FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_mail` mr WHERE mr.id_jooim_withdrawal=w.id_jooim_withdrawal AND mr.template_code=\'withdrawal_acknowledgement\' AND mr.status=\'failed\' AND mr.next_attempt_at > UTC_TIMESTAMP()) '
                    . ' ORDER BY w.id_jooim_withdrawal ASC LIMIT 20'
                ) ?: [];

                foreach ($rows as $withdrawal) {
                    $snapshot = json_decode((string) $withdrawal['payload_json'], true) ?: [];
                    $pdfContent = null;
                    $docs = $this->repo->documents((int) $withdrawal['id_jooim_withdrawal']);
                    if ($docs) {
                        try {
                            $pdfContent = $this->storage->read((string) $docs[0]['storage_path']);
                        } catch (\Throwable $e) {
                            $pdfContent = null;
                        }
                    }
                    $language = new \Language((int) $withdrawal['id_lang']);
                    if (\Validate::isLoadedObject($language)) {
                        $this->context->language = $language;
                    }
                    $ok = $this->mail->sendAcknowledgement($withdrawal, $snapshot, $pdfContent);
                    ++$retried;
                    if ($ok) {
                        $db = \Db::getInstance();
                        if ((string) $withdrawal['status_code'] === 'submitted') {
                            $db->update('jooim_withdrawal', ['status_code' => 'acknowledged', 'updated_at' => gmdate('Y-m-d H:i:s')], 'id_jooim_withdrawal=' . (int) $withdrawal['id_jooim_withdrawal']);
                        }
                        $db->insert('jooim_withdrawal_history', [
                            'id_jooim_withdrawal' => (int) $withdrawal['id_jooim_withdrawal'],
                            'event_code' => 'acknowledgement_retry_sent',
                            'created_at' => gmdate('Y-m-d H:i:s'),
                        ], true);
                    }
                }
            }
        } finally {
            $this->context->shop = $originalShop;
            $this->context->language = $originalLanguage;
        }

        return $retried;
    }}
