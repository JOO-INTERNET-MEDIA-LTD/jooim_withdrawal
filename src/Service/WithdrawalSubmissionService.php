<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Repository\WithdrawalRepository;
use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Security\TokenService;

final class WithdrawalSubmissionService
{
    private \Context $context;
    private \Module $module;
    private ModuleConfig $config;
    private TokenService $tokens;
    private WithdrawalRepository $repo;
    private WithdrawalQuantityCalculator $quantities;
    private WithdrawalPeriodEvaluator $period;
    private WithdrawalReferenceGenerator $references;
    private WithdrawalPdfService $pdf;
    private WithdrawalMailService $mail;

    public function __construct(\Context $context, \Module $module, ModuleConfig $config, TokenService $tokens, WithdrawalRepository $repo, WithdrawalQuantityCalculator $quantities, WithdrawalPeriodEvaluator $period, WithdrawalReferenceGenerator $references, WithdrawalPdfService $pdf, WithdrawalMailService $mail)
    {
        $this->context = $context;
        $this->module = $module;
        $this->config = $config;
        $this->tokens = $tokens;
        $this->repo = $repo;
        $this->quantities = $quantities;
        $this->period = $period;
        $this->references = $references;
        $this->pdf = $pdf;
        $this->mail = $mail;
    }

    public function submit(int $idDraft, string $plainKey): array
    {
        $db = \Db::getInstance();
        $keyHash = $this->tokens->hash($plainKey, 'draft_idempotency');
        $transactionStarted = false;
        $idWithdrawal = 0;
        $reference = '';
        $payload = [];
        $snapshot = [];
        $snapshotJson = '';
        $payloadHash = '';
        $nowUtc = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        try {
            $this->mustExecute($db, 'START TRANSACTION', 'start_transaction');
            $transactionStarted = true;

            // JOOIM_WITHDRAWAL_LOCK_SAFE: executeS preserves the terminal
            // FOR UPDATE clause. Db::getRow()/getValue() must never be used for
            // locking reads because PrestaShop appends LIMIT 1 after the query.
            $draft = $this->fetchOne(
                $db,
                'SELECT * FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_draft` WHERE id_draft=' . (int) $idDraft
                . ' AND id_shop=' . (int) $this->context->shop->id
                . " AND idempotency_key_hash='" . pSQL($keyHash) . "' FOR UPDATE",
                'lock_draft'
            );
            if (!$draft) {
                throw new \RuntimeException('Withdrawal draft not found.');
            }

            if (!empty($draft['submitted_at'])) {
                $existing = $this->fetchOne(
                    $db,
                    'SELECT * FROM `' . _DB_PREFIX_ . 'jooim_withdrawal` WHERE id_source_draft=' . (int) $idDraft
                    . ' AND id_shop=' . (int) $this->context->shop->id
                    . ' ORDER BY id_jooim_withdrawal DESC',
                    'load_idempotent_submission'
                );
                if ($existing) {
                    $this->mustExecute($db, 'COMMIT', 'commit_idempotent_submission');
                    $transactionStarted = false;

                    return $this->result($existing);
                }

                throw new \RuntimeException('This withdrawal was already submitted.');
            }

            if (strtotime((string) $draft['expires_at'] . ' UTC') < time()) {
                throw new \RuntimeException('Withdrawal draft expired.');
            }

            $payload = json_decode((string) $draft['draft_payload'], true);
            if (!is_array($payload)) {
                throw new \RuntimeException('Invalid withdrawal draft payload.');
            }
            if ((int) ($payload['id_shop'] ?? 0) !== (int) $this->context->shop->id) {
                throw new \RuntimeException('Shop mismatch.');
            }

            $order = null;
            if (!empty($payload['id_order'])) {
                $idOrder = (int) $payload['id_order'];
                $lockedOrder = $this->fetchOne(
                    $db,
                    'SELECT id_order FROM `' . _DB_PREFIX_ . 'orders` WHERE id_order=' . $idOrder . ' FOR UPDATE',
                    'lock_order'
                );
                if (!$lockedOrder) {
                    throw new \RuntimeException('Order is no longer available.');
                }

                $order = new \Order($idOrder);
                if (!\Validate::isLoadedObject($order) || (int) $order->id_shop !== (int) $this->context->shop->id) {
                    throw new \RuntimeException('Order is no longer available.');
                }
                $this->revalidateItems(is_array($payload['items'] ?? null) ? $payload['items'] : [], $idOrder);
            }

            $shopCode = $this->shopCode();
            $reference = $this->references->next((int) $this->context->shop->id, $shopCode);
            $timezoneName = (string) \Configuration::get('PS_TIMEZONE') ?: 'UTC';
            try {
                $timezone = new \DateTimeZone($timezoneName);
            } catch (\Throwable $exception) {
                $timezone = new \DateTimeZone('UTC');
                $timezoneName = 'UTC';
            }
            $local = $nowUtc->setTimezone($timezone);
            $merchant = $this->config->merchantSnapshot();
            $snapshot = [
                'schema_version' => '1.0',
                'module' => ['technical_name' => 'jooim_withdrawal', 'version' => (string) $this->module->version, 'vendor' => 'JOO INTERNET MEDIA LTD'],
                'draft_id' => $idDraft,
                'withdrawal_reference' => $reference,
                'shop' => ['id' => (int) $this->context->shop->id, 'name' => (string) $this->context->shop->name, 'timezone' => $timezoneName],
                'merchant' => $merchant,
                'order' => ['id' => $payload['id_order'] ?? null, 'reference' => (string) ($payload['order_reference'] ?? ''), 'order_date' => $payload['order_date'] ?? null],
                'customer' => $payload['customer'] ?? [],
                'scope' => (string) ($payload['scope'] ?? ''),
                'items' => is_array($payload['items'] ?? null) ? $payload['items'] : [],
                'manual_items_description' => (string) ($payload['manual_items_description'] ?? ''),
                'statement' => (string) ($payload['statement_text'] ?? ''),
                'reason_code' => $payload['reason_code'] ?? null,
                'customer_note' => (string) ($payload['customer_note'] ?? ''),
                'privacy' => is_array($payload['privacy'] ?? null) ? $payload['privacy'] : [],
                'legal_text_version' => (string) ($payload['legal_text_version'] ?? 'eu-crd-11a-2026-06'),
                'submitted_at' => $local->format(DATE_ATOM),
                'submitted_at_utc' => $nowUtc->format('Y-m-d\TH:i:s\Z'),
                'locale' => $this->languageLocale(),
                'form_version' => (string) ($payload['form_version'] ?? '1.0'),
                'requires_manual_review' => (bool) ($payload['requires_manual_review'] ?? false),
                'admin_employee_id' => isset($payload['admin_employee_id']) ? (int) $payload['admin_employee_id'] : null,
                'received_method' => (string) ($payload['received_method'] ?? ''),
                'received_at' => $payload['received_at'] ?? null,
            ];
            $snapshotJson = $this->encodeJson($snapshot, 'withdrawal_snapshot');
            $payloadHash = hash('sha256', $snapshotJson);
            $deliveryDate = $order ? $this->resolveDeliveryDateUtc((int) $order->id) : null;
            $assessment = $this->period->evaluate($deliveryDate, $nowUtc->format('Y-m-d H:i:s'), 14, $timezoneName);
            $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];

            $this->mustInsert($db, 'jooim_withdrawal', [
                'id_source_draft' => $idDraft,
                'id_shop_group' => (int) $this->context->shop->id_shop_group,
                'id_shop' => (int) $this->context->shop->id,
                'id_order' => !empty($payload['id_order']) ? (int) $payload['id_order'] : null,
                'id_customer' => !empty($payload['id_customer']) ? (int) $payload['id_customer'] : null,
                'id_lang' => (int) ($payload['id_lang'] ?? $this->context->language->id),
                'id_currency' => !empty($payload['id_currency']) ? (int) $payload['id_currency'] : null,
                'reference' => pSQL($reference),
                'order_reference' => pSQL((string) ($payload['order_reference'] ?? '')),
                'source' => pSQL((string) ($payload['source'] ?? 'guest_direct')),
                'scope' => pSQL((string) ($payload['scope'] ?? 'full')),
                'status_code' => 'submitted',
                'customer_firstname' => pSQL(mb_substr((string) ($customer['firstname'] ?? ''), 0, 128)),
                'customer_lastname' => pSQL(mb_substr((string) ($customer['lastname'] ?? ''), 0, 128)),
                'customer_email' => pSQL(mb_substr((string) ($customer['email'] ?? ''), 0, 254)),
                'customer_phone' => pSQL(mb_substr((string) ($customer['phone'] ?? ''), 0, 64)),
                'reason_code' => isset($payload['reason_code']) ? pSQL((string) $payload['reason_code']) : null,
                'customer_note' => pSQL((string) ($payload['customer_note'] ?? ''), true),
                'statement_text' => pSQL((string) ($payload['statement_text'] ?? ''), true),
                'order_date' => !empty($payload['order_date']) ? pSQL((string) $payload['order_date']) : null,
                'delivery_date' => $deliveryDate,
                'submitted_at_utc' => $nowUtc->format('Y-m-d H:i:s'),
                'shop_timezone' => pSQL($timezoneName),
                'form_version' => pSQL((string) ($payload['form_version'] ?? '1.0')),
                'module_version' => (string) $this->module->version,
                'legal_text_version' => pSQL((string) ($payload['legal_text_version'] ?? 'eu-crd-11a-2026-06')),
                'privacy_text_version' => pSQL((string) (($payload['privacy']['version'] ?? 'privacy-1.0'))),
                'payload_json' => pSQL($snapshotJson, true),
                'payload_hash' => pSQL($payloadHash),
                'period_assessment' => pSQL((string) $assessment),
                'created_at' => $nowUtc->format('Y-m-d H:i:s'),
                'updated_at' => $nowUtc->format('Y-m-d H:i:s'),
            ], 'insert_withdrawal');

            $idWithdrawal = (int) $db->Insert_ID();
            if ($idWithdrawal <= 0) {
                throw new \RuntimeException('Withdrawal database failure at insert_withdrawal: no insert identifier returned.');
            }

            foreach ((array) ($payload['items'] ?? []) as $index => $item) {
                if (!is_array($item)) {
                    throw new \RuntimeException('Invalid withdrawal item snapshot.');
                }
                $qty = (int) ($item['withdrawal_quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $itemSnapshot = $this->encodeJson($item, 'withdrawal_item_' . (int) $index);
                $this->mustInsert($db, 'jooim_withdrawal_item', [
                    'id_jooim_withdrawal' => $idWithdrawal,
                    'id_order_detail' => !empty($item['id_order_detail']) ? (int) $item['id_order_detail'] : null,
                    'id_product' => !empty($item['id_product']) ? (int) $item['id_product'] : null,
                    'id_product_attribute' => !empty($item['id_product_attribute']) ? (int) $item['id_product_attribute'] : null,
                    'product_reference' => pSQL((string) ($item['product_reference'] ?? '')),
                    'product_name' => pSQL((string) ($item['product_name'] ?? '')),
                    'product_attribute_name' => pSQL((string) ($item['product_attribute_name'] ?? '')),
                    'customization_snapshot' => pSQL((string) ($item['customization_snapshot'] ?? ''), true),
                    'ordered_quantity' => (int) ($item['ordered_quantity'] ?? 0),
                    'available_quantity' => (int) ($item['available_quantity'] ?? 0),
                    'withdrawal_quantity' => $qty,
                    'unit_price_tax_excl' => (float) ($item['unit_price_tax_excl'] ?? 0),
                    'unit_price_tax_incl' => (float) ($item['unit_price_tax_incl'] ?? 0),
                    'total_price_tax_incl' => (float) ($item['unit_price_tax_incl'] ?? 0) * $qty,
                    'currency_iso' => pSQL((string) ($item['currency_iso'] ?? '')),
                    'snapshot_json' => pSQL($itemSnapshot, true),
                    'created_at' => $nowUtc->format('Y-m-d H:i:s'),
                ], 'insert_withdrawal_item_' . (int) $index);
            }

            $historyMetadata = $this->encodeJson([
                'source' => (string) ($payload['source'] ?? ''),
                'payload_hash' => $payloadHash,
            ], 'submission_history_metadata');
            $this->mustInsert($db, 'jooim_withdrawal_history', [
                'id_jooim_withdrawal' => $idWithdrawal,
                'id_employee' => !empty($payload['admin_employee_id']) ? (int) $payload['admin_employee_id'] : null,
                'old_status_code' => null,
                'new_status_code' => 'submitted',
                'event_code' => 'withdrawal_submitted',
                'metadata_json' => pSQL($historyMetadata, true),
                'created_at' => $nowUtc->format('Y-m-d H:i:s'),
            ], 'insert_submission_history');

            $outboxPayload = $this->encodeJson([
                'withdrawal_id' => $idWithdrawal,
                'reference' => $reference,
                'payload_hash' => $payloadHash,
            ], 'submission_outbox_payload');
            $this->mustInsert($db, 'jooim_withdrawal_event_outbox', [
                'event_name' => 'actionJooimWithdrawalCreated',
                'aggregate_type' => 'withdrawal',
                'aggregate_id' => $idWithdrawal,
                'payload_json' => pSQL($outboxPayload, true),
                'status' => 'pending',
                'created_at' => $nowUtc->format('Y-m-d H:i:s'),
            ], 'insert_submission_outbox');

            $this->mustUpdate($db, 'jooim_withdrawal_draft', [
                'submitted_at' => $nowUtc->format('Y-m-d H:i:s'),
                'updated_at' => $nowUtc->format('Y-m-d H:i:s'),
            ], 'id_draft=' . (int) $idDraft, 'mark_draft_submitted');

            $this->mustExecute($db, 'COMMIT', 'commit_submission');
            $transactionStarted = false;
        } catch (\Throwable $exception) {
            if ($transactionStarted) {
                try {
                    $db->execute('ROLLBACK', false);
                } catch (\Throwable $rollbackException) {
                    $this->reportPostCommitFailure($rollbackException, 'rollback_submission', $idWithdrawal);
                }
            }
            throw $exception;
        }

        // The legal submission is final after COMMIT. PDF generation, e-mail,
        // third-party hooks and status decoration are best-effort post-commit
        // work and must never turn an accepted withdrawal into a blank page.
        $withdrawal = $this->postCommitStage(
            'reload_withdrawal',
            $idWithdrawal,
            fn(): ?array => $this->repo->find($idWithdrawal, (int) $this->context->shop->id),
            null
        );
        if (!is_array($withdrawal)) {
            $withdrawal = $this->minimalWithdrawal($idWithdrawal, $reference, $payload, $snapshotJson, $payloadHash, $nowUtc);
        }

        $items = $this->postCommitStage(
            'reload_withdrawal_items',
            $idWithdrawal,
            fn(): array => $this->repo->items($idWithdrawal),
            is_array($snapshot['items'] ?? null) ? $snapshot['items'] : []
        );
        if (!is_array($items)) {
            $items = is_array($snapshot['items'] ?? null) ? $snapshot['items'] : [];
        }

        $pdfContent = $this->postCommitStage(
            'generate_acknowledgement_pdf',
            $idWithdrawal,
            function () use ($db, $withdrawal, $items, $snapshot, $reference, $idWithdrawal): ?string {
                $document = $this->pdf->generate($withdrawal, $items, $snapshot);
                $this->mustInsert($db, 'jooim_withdrawal_document', [
                    'id_jooim_withdrawal' => $idWithdrawal,
                    'document_type' => 'acknowledgement_pdf',
                    'document_reference' => pSQL($reference . '-ACK'),
                    'storage_path' => pSQL((string) $document['path']),
                    'mime_type' => 'application/pdf',
                    'file_size' => (int) $document['size'],
                    'file_hash' => pSQL((string) $document['hash']),
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ], 'insert_acknowledgement_document');

                $content = @file_get_contents((string) $document['path']);
                return is_string($content) && $content !== '' ? $content : null;
            },
            null
        );
        if ($pdfContent === null) {
            $this->history($idWithdrawal, 'document_generation_failed', null, null, 'PDF generation or storage failed; the withdrawal remains valid.');
        }

        $ack = (bool) $this->postCommitStage(
            'send_acknowledgement',
            $idWithdrawal,
            fn(): bool => $this->mail->sendAcknowledgement($withdrawal, $snapshot, is_string($pdfContent) ? $pdfContent : null),
            false
        );
        if ($ack) {
            $statusUpdated = (bool) $this->postCommitStage(
                'mark_acknowledgement_sent',
                $idWithdrawal,
                function () use ($db, $idWithdrawal): bool {
                    $this->mustUpdate($db, 'jooim_withdrawal', [
                        'status_code' => 'acknowledged',
                        'updated_at' => gmdate('Y-m-d H:i:s'),
                    ], 'id_jooim_withdrawal=' . (int) $idWithdrawal
                        . ' AND id_shop=' . (int) $this->context->shop->id . " AND status_code='submitted'", 'mark_acknowledged');
                    return $db->Affected_Rows() === 1;
                },
                false
            );
            if ($statusUpdated) {
                $this->history($idWithdrawal, 'acknowledgement_sent', 'submitted', 'acknowledged', null);
                $withdrawal['status_code'] = 'acknowledged';
            }
        } else {
            $this->history($idWithdrawal, 'acknowledgement_failed', null, null, 'Acknowledgement email was not sent; submission remains valid.');
        }

        $this->postCommitStage(
            'send_admin_notification',
            $idWithdrawal,
            function () use ($withdrawal): bool {
                $this->mail->sendAdminNotification($withdrawal);
                return true;
            },
            false
        );

        $hookDelivered = (bool) $this->postCommitStage(
            'dispatch_created_hook',
            $idWithdrawal,
            function () use ($withdrawal, $snapshot): bool {
                \Hook::exec('actionJooimWithdrawalCreated', ['withdrawal' => $withdrawal, 'snapshot' => $snapshot]);
                return true;
            },
            false
        );

        $this->postCommitStage(
            'update_created_outbox',
            $idWithdrawal,
            function () use ($db, $idWithdrawal, $hookDelivered): bool {
                $data = $hookDelivered
                    ? ['status' => 'processed', 'processed_at' => gmdate('Y-m-d H:i:s')]
                    : [
                        'status' => 'failed',
                        'attempt_count' => ['type' => 'sql', 'value' => '`attempt_count` + 1'],
                        'next_attempt_at' => gmdate('Y-m-d H:i:s', time() + 300),
                    ];
                $this->mustUpdate(
                    $db,
                    'jooim_withdrawal_event_outbox',
                    $data,
                    'aggregate_type=\'withdrawal\' AND aggregate_id=' . (int) $idWithdrawal
                    . " AND event_name='actionJooimWithdrawalCreated'",
                    'update_created_outbox'
                );
                return true;
            },
            false
        );

        return $this->result($withdrawal);
    }

    private function revalidateItems(array $items, int $idOrder): void
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \RuntimeException('Invalid withdrawal item.');
            }
            $idDetail = (int) ($item['id_order_detail'] ?? 0);
            $qty = (int) ($item['withdrawal_quantity'] ?? 0);
            if ($idDetail <= 0 || $qty <= 0) {
                throw new \RuntimeException('Invalid withdrawal item quantity.');
            }
            $detail = new \OrderDetail($idDetail);
            if (!\Validate::isLoadedObject($detail) || (int) $detail->id_order !== $idOrder) {
                throw new \RuntimeException('Order item is no longer available.');
            }
            $ordered = (int) $detail->product_quantity;
            $refunded = (int) $detail->product_quantity_refunded;
            $returned = (int) $detail->product_quantity_return;
            $available = max(0, $ordered - $refunded - $returned - $this->quantities->activeWithdrawalQuantity($idDetail));
            if ($qty > $available) {
                throw new \RuntimeException('Selected withdrawal quantity is no longer available.');
            }
        }
    }

    private function result(array $withdrawal): array
    {
        return [
            'withdrawal' => $withdrawal,
            'receipt_token' => $this->tokens->issueReceipt((int) $withdrawal['id_jooim_withdrawal'], (int) $withdrawal['id_shop']),
        ];
    }

    private function history(int $idWithdrawal, string $event, ?string $old, ?string $new, ?string $note): void
    {
        try {
            $inserted = \Db::getInstance()->insert('jooim_withdrawal_history', [
                'id_jooim_withdrawal' => $idWithdrawal,
                'id_employee' => null,
                'old_status_code' => $old,
                'new_status_code' => $new,
                'event_code' => pSQL($event),
                'private_note' => $note ? pSQL($note, true) : null,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ], true, false);
            if (!$inserted) {
                throw $this->databaseFailure(\Db::getInstance(), 'append_history_' . $event);
            }
        } catch (\Throwable $exception) {
            $this->reportPostCommitFailure($exception, 'append_history_' . $event, $idWithdrawal);
        }
    }

    private function fetchOne(\Db $db, string $sql, string $stage): ?array
    {
        $rows = $db->executeS($sql, true, false);
        if ($rows === false) {
            throw $this->databaseFailure($db, $stage);
        }
        if (!isset($rows[0]) || !is_array($rows[0])) {
            return null;
        }

        return $rows[0];
    }

    private function mustExecute(\Db $db, string $sql, string $stage): void
    {
        if (!$db->execute($sql, false)) {
            throw $this->databaseFailure($db, $stage);
        }
    }

    private function mustInsert(\Db $db, string $table, array $data, string $stage): void
    {
        if (!$db->insert($table, $data, true, false)) {
            throw $this->databaseFailure($db, $stage);
        }
    }

    private function mustUpdate(\Db $db, string $table, array $data, string $where, string $stage): void
    {
        if (!$db->update($table, $data, $where, 0, true, false)) {
            throw $this->databaseFailure($db, $stage);
        }
    }

    private function databaseFailure(\Db $db, string $stage): \RuntimeException
    {
        $message = trim((string) $db->getMsgError());
        if ($message === '') {
            $message = 'unknown database error';
        }

        return new \RuntimeException('Withdrawal database failure at ' . $stage . ': ' . $message);
    }

    private function encodeJson(array $payload, string $stage): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Cannot encode JSON at ' . $stage . ': ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Runs non-transactional work without allowing output or exceptions to
     * invalidate the already committed withdrawal.
     *
     * @param mixed $fallback
     * @return mixed
     */
    private function postCommitStage(string $stage, int $idWithdrawal, callable $callback, $fallback = null)
    {
        $bufferLevel = ob_get_level();
        $bufferStarted = @ob_start();
        try {
            return $callback();
        } catch (\Throwable $exception) {
            $this->reportPostCommitFailure($exception, $stage, $idWithdrawal);
            return $fallback;
        } finally {
            if ($bufferStarted) {
                while (ob_get_level() > $bufferLevel) {
                    @ob_end_clean();
                }
            }
        }
    }

    private function reportPostCommitFailure(\Throwable $exception, string $stage, int $idWithdrawal): void
    {
        $context = __METHOD__ . ':' . $stage . ':withdrawal=' . $idWithdrawal;
        try {
            if (class_exists('\\JooimWithdrawalRuntimeDiagnostics')) {
                \JooimWithdrawalRuntimeDiagnostics::report($exception, $context);
            }
        } catch (\Throwable $ignored) {
        }

        try {
            if (class_exists('\\PrestaShopLogger')) {
                \PrestaShopLogger::addLog(
                    'JOOIM Withdrawal post-commit failure [' . $stage . '] withdrawal=' . $idWithdrawal
                    . ': ' . get_class($exception) . ': ' . $exception->getMessage(),
                    3,
                    null,
                    'Module',
                    isset($this->module->id) ? (int) $this->module->id : 0,
                    true
                );
            }
        } catch (\Throwable $ignored) {
        }
    }

    private function minimalWithdrawal(int $idWithdrawal, string $reference, array $payload, string $snapshotJson, string $payloadHash, \DateTimeImmutable $nowUtc): array
    {
        $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];

        return [
            'id_jooim_withdrawal' => $idWithdrawal,
            'id_shop' => (int) $this->context->shop->id,
            'id_lang' => (int) ($payload['id_lang'] ?? $this->context->language->id),
            'id_order' => !empty($payload['id_order']) ? (int) $payload['id_order'] : null,
            'reference' => $reference,
            'order_reference' => (string) ($payload['order_reference'] ?? ''),
            'source' => (string) ($payload['source'] ?? 'guest_direct'),
            'scope' => (string) ($payload['scope'] ?? 'full'),
            'status_code' => 'submitted',
            'customer_firstname' => (string) ($customer['firstname'] ?? ''),
            'customer_lastname' => (string) ($customer['lastname'] ?? ''),
            'customer_email' => (string) ($customer['email'] ?? ''),
            'statement_text' => (string) ($payload['statement_text'] ?? ''),
            'submitted_at_utc' => $nowUtc->format('Y-m-d H:i:s'),
            'payload_json' => $snapshotJson,
            'payload_hash' => $payloadHash,
        ];
    }

    private function resolveDeliveryDateUtc(int $idOrder): ?string
    {
        $value = \Db::getInstance()->getValue(
            'SELECT oh.date_add FROM `' . _DB_PREFIX_ . 'order_history` oh '
            . 'INNER JOIN `' . _DB_PREFIX_ . 'order_state` os ON os.id_order_state=oh.id_order_state '
            . 'WHERE oh.id_order=' . (int) $idOrder . ' AND os.delivery=1 ORDER BY oh.date_add ASC',
            false
        );
        if (!$value) {
            return null;
        }
        $timezoneName = (string) \Configuration::get('PS_TIMEZONE') ?: 'UTC';
        try {
            $localTz = new \DateTimeZone($timezoneName);
            return (new \DateTimeImmutable((string) $value, $localTz))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s');
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function languageLocale(): string
    {
        if (isset($this->context->language->locale) && (string) $this->context->language->locale !== '') {
            return (string) $this->context->language->locale;
        }
        return (string) ($this->context->language->iso_code ?: 'en');
    }

    private function shopCode(): string
    {
        $iso = '';
        try {
            $country = new \Country((int) \Configuration::get('PS_COUNTRY_DEFAULT'));
            $iso = (string) $country->iso_code;
        } catch (\Throwable $exception) {
        }
        return $iso !== '' ? $iso : 'S' . (int) $this->context->shop->id;
    }
}
