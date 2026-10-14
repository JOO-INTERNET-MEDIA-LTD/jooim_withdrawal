<?php
use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\ServiceContainer;
use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\WithdrawalStatusPresenter;

class AdminJooimWithdrawalController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function initContent()
    {
        if (!$this->access('view')) {
            http_response_code(403);
            $this->errors[] = $this->module->jooimTranslate('You do not have permission to view contract withdrawals.', [], 'Modules.Jooimwithdrawal.Admin');
            parent::initContent();
            return;
        }
        if (Tools::getValue('exportWithdrawalCsv')) {
            $this->exportCsv();
        }
        if ((int) Tools::getValue('downloadWithdrawalDocument') > 0) {
            $this->downloadDocument((int) Tools::getValue('downloadWithdrawalDocument'));
        }
        parent::initContent();
        $this->processActions();
        $id = (int) Tools::getValue('id_jooim_withdrawal');
        if ($id > 0 && Tools::getValue('viewjooim_withdrawal')) {
            $this->renderDetail($id);
            return;
        }
        $this->renderListPage();
    }

    private function processActions(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        $postedToken = (string) Tools::getValue('token');
        if (!hash_equals(Tools::getAdminTokenLite('AdminJooimWithdrawal'), $postedToken)) {
            $this->errors[] = $this->module->jooimTranslate('Invalid CSRF token.', [], 'Modules.Jooimwithdrawal.Admin');
            return;
        }
        $services = ServiceContainer::get($this->module);
        $db = Db::getInstance();

        if (Tools::isSubmit('changeWithdrawalStatus')) {
            if (!$this->access('edit')) { $this->errors[] = $this->module->jooimTranslate('You do not have permission to change withdrawal status.', [], 'Modules.Jooimwithdrawal.Admin'); return; }
            $id = (int) Tools::getValue('id_jooim_withdrawal');
            $new = (string) Tools::getValue('new_status_code');
            $public = trim((string) Tools::getValue('public_message'));
            $private = trim((string) Tools::getValue('private_note'));
            $row = $this->findAccessibleWithdrawal($id);
            if (!$row || !WithdrawalStatusPresenter::isValid($new)) {
                $this->errors[] = $this->module->jooimTranslate('Invalid withdrawal or status.', [], 'Modules.Jooimwithdrawal.Admin');
                return;
            }
            if ($new === 'rejected' && $public === '') {
                $this->errors[] = $this->module->jooimTranslate('A rejection requires a customer-facing reason.', [], 'Modules.Jooimwithdrawal.Admin');
                return;
            }
            $old = (string) $row['status_code'];
            try {
                $this->requireDatabaseSuccess($db->execute('START TRANSACTION'));
                $locked = $db->executeS('SELECT status_code FROM `' . _DB_PREFIX_ . 'jooim_withdrawal` WHERE id_jooim_withdrawal=' . $id
                    . ' AND id_shop IN (' . implode(',', $this->allowedShopIds()) . ') FOR UPDATE', true, false);
                if (!is_array($locked) || !isset($locked[0])) {
                    throw new RuntimeException('Withdrawal is no longer accessible.');
                }
                $old = (string) $locked[0]['status_code'];
                $this->requireDatabaseSuccess($db->update('jooim_withdrawal', ['status_code' => pSQL($new), 'updated_at' => gmdate('Y-m-d H:i:s')], 'id_jooim_withdrawal=' . $id));
                $this->requireDatabaseSuccess($db->insert('jooim_withdrawal_history', [
                    'id_jooim_withdrawal' => $id,
                    'id_employee' => (int) $this->context->employee->id,
                    'old_status_code' => pSQL($old),
                    'new_status_code' => pSQL($new),
                    'event_code' => 'admin_status_changed',
                    'public_message' => $public !== '' ? pSQL($public, true) : null,
                    'private_note' => $private !== '' ? pSQL($private, true) : null,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                ], true));
                $this->requireDatabaseSuccess($db->execute('COMMIT'));
                $this->afterCommit(function () use ($services, $id, $new, $old, $public): void {
                    $updated = $services->withdrawalRepository()->find($id);
                    if ($updated && $new !== $old) {
                        $services->mail()->sendStatusNotification($updated, $public);
                    }
                    Hook::exec('actionJooimWithdrawalStatusChanged', ['withdrawal_id' => $id, 'old_status' => $old, 'new_status' => $new]);
                });
                $this->confirmations[] = $this->module->jooimTranslate('Status updated.', [], 'Modules.Jooimwithdrawal.Admin');
            } catch (Throwable $e) {
                $db->execute('ROLLBACK');
                $this->errors[] = $this->module->jooimTranslate('Invalid withdrawal or status.', [], 'Modules.Jooimwithdrawal.Admin');
            }
        }

        if (Tools::isSubmit('recordRefund')) {
            if (!$this->access('edit')) { $this->errors[] = $this->module->jooimTranslate('You do not have permission to record refunds.', [], 'Modules.Jooimwithdrawal.Admin'); return; }
            $id = (int) Tools::getValue('id_jooim_withdrawal');
            $amountInput = str_replace(',', '.', trim((string) Tools::getValue('refund_amount')));
            $amount = (float) $amountInput;
            $currency = strtoupper(trim((string) Tools::getValue('refund_currency')));
            $refundedAt = trim((string) Tools::getValue('refunded_at', gmdate('Y-m-d H:i:s')));
            if (!$this->findAccessibleWithdrawal($id) || !preg_match('/^\d{1,14}(?:\.\d{1,6})?$/D', $amountInput) || $amount <= 0 || !preg_match('/^[A-Z]{3}$/', $currency) || !Validate::isDate($refundedAt)) {
                $this->errors[] = $this->module->jooimTranslate('Invalid refund data.', [], 'Modules.Jooimwithdrawal.Admin');
                return;
            }
            try {
                $this->requireDatabaseSuccess($db->execute('START TRANSACTION'));
            $this->requireDatabaseSuccess($db->insert('jooim_withdrawal_refund', [
                'id_jooim_withdrawal' => $id,
                'amount' => $amountInput,
                'currency_iso' => pSQL($currency),
                'refund_method' => pSQL((string) Tools::getValue('refund_method')),
                'transaction_reference' => pSQL((string) Tools::getValue('transaction_reference')),
                'internal_note' => pSQL((string) Tools::getValue('refund_note'), true),
                'refunded_at' => pSQL($refundedAt),
                'id_employee' => (int) $this->context->employee->id,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ], true));
            $this->requireDatabaseSuccess($db->insert('jooim_withdrawal_history', [
                'id_jooim_withdrawal' => $id,
                'id_employee' => (int) $this->context->employee->id,
                'event_code' => 'refund_recorded',
                'metadata_json' => pSQL((string) json_encode(['amount' => $amount, 'currency' => $currency], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ], true));
            $this->requireDatabaseSuccess($db->execute('COMMIT'));
            $this->afterCommit(function () use ($id, $amount, $currency): void {
                Hook::exec('actionJooimWithdrawalRefundRecorded', ['withdrawal_id' => $id, 'amount' => $amount, 'currency' => $currency]);
            });
            $this->confirmations[] = $this->module->jooimTranslate('Refund recorded.', [], 'Modules.Jooimwithdrawal.Admin');
            } catch (Throwable $e) {
                $db->execute('ROLLBACK');
                $this->errors[] = $this->module->jooimTranslate('Refund could not be recorded.', [], 'Modules.Jooimwithdrawal.Admin');
            }
        }

        if (Tools::isSubmit('resendAcknowledgement')) {
            if (!$this->access('edit')) { $this->errors[] = $this->module->jooimTranslate('You do not have permission to resend acknowledgements.', [], 'Modules.Jooimwithdrawal.Admin'); return; }
            $id = (int) Tools::getValue('id_jooim_withdrawal');
            $row = $this->findAccessibleWithdrawal($id);
            if (!$row) { $this->errors[] = $this->module->jooimTranslate('Withdrawal not found.', [], 'Modules.Jooimwithdrawal.Admin'); return; }
            $snapshot = json_decode((string) $row['payload_json'], true) ?: [];
            $pdfContent = null;
            $docs = $services->withdrawalRepository()->documents($id);
            if ($docs) {
                try { $pdfContent = $services->storage()->read((string) $docs[0]['storage_path']); } catch (Throwable $e) {}
            }
            if ($services->mail()->sendAcknowledgement($row, $snapshot, $pdfContent)) {
                $this->confirmations[] = $this->module->jooimTranslate('Acknowledgement sent.', [], 'Modules.Jooimwithdrawal.Admin');
            } else {
                $this->errors[] = $this->module->jooimTranslate('Acknowledgement email could not be sent.', [], 'Modules.Jooimwithdrawal.Admin');
            }
        }

        if (Tools::isSubmit('createManualWithdrawal')) {
            if (!$this->access('add')) { $this->errors[] = $this->module->jooimTranslate('You do not have permission to create withdrawal records.', [], 'Modules.Jooimwithdrawal.Admin'); return; }
            try {
                $draft = $services->drafts()->createDirect([
                    'firstname' => (string) Tools::getValue('manual_firstname'),
                    'lastname' => (string) Tools::getValue('manual_lastname'),
                    'email' => (string) Tools::getValue('manual_email'),
                    'order_reference' => (string) Tools::getValue('manual_order_reference'),
                    'scope' => (string) Tools::getValue('manual_scope', 'full'),
                    'manual_items_description' => (string) Tools::getValue('manual_items_description'),
                    'phone' => (string) Tools::getValue('manual_phone'),
                    'note' => (string) Tools::getValue('manual_original_content'),
                ],
                'The customer unequivocally notified the trader of withdrawal through another communication channel; this record was entered by an authorised employee.',
                'Personal data is processed to receive, record and handle the contract withdrawal.');
                $payload = $draft['payload'];
                $payload['source'] = 'admin_manual';
                $payload['admin_employee_id'] = (int) $this->context->employee->id;
                $payload['received_method'] = (string) Tools::getValue('manual_received_method');
                $payload['received_at'] = (string) Tools::getValue('manual_received_at', gmdate('c'));
                $this->requireDatabaseSuccess($db->update('jooim_withdrawal_draft', [
                    'source' => 'admin_manual',
                    'draft_payload' => pSQL((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ], 'id_draft=' . (int) $draft['id_draft']));
                $result = $services->withdrawals()->submit((int) $draft['id_draft'], (string) $draft['key']);
                $this->confirmations[] = $this->module->jooimTranslate('Manual withdrawal created: %reference%', ['%reference%' => (string) $result['withdrawal']['reference']], 'Modules.Jooimwithdrawal.Admin');
            } catch (Throwable $e) {
                $this->errors[] = $this->module->jooimTranslate('Invalid withdrawal or status.', [], 'Modules.Jooimwithdrawal.Admin');
            }
        }
    }

    private function afterCommit(callable $callback): void
    {
        $level = ob_get_level();
        ob_start();
        try {
            $callback();
        } catch (Throwable $exception) {
            try {
                PrestaShopLogger::addLog('JOOIM Withdrawal admin post-commit notification failed.', 3);
            } catch (Throwable $ignored) {
            }
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }

    private function requireDatabaseSuccess(bool $ok): void
    {
        if (!$ok) {
            throw new RuntimeException('The withdrawal database operation failed.');
        }
    }

    private function renderListPage(): void
    {
        $shopIds = array_map('intval', Shop::getContextListShopID());
        if (!$shopIds) { $shopIds = [(int) $this->context->shop->id]; }
        $where = ['w.id_shop IN (' . implode(',', $shopIds) . ')'];
        $filters = [
            'reference' => trim((string) Tools::getValue('reference')),
            'order_reference' => trim((string) Tools::getValue('order_reference')),
            'email' => trim((string) Tools::getValue('email')),
            'status' => trim((string) Tools::getValue('status')),
            'date_from' => trim((string) Tools::getValue('date_from')),
            'date_to' => trim((string) Tools::getValue('date_to')),
        ];
        if ($filters['reference'] !== '') $where[] = "w.reference LIKE '%" . pSQL($filters['reference']) . "%'";
        if ($filters['order_reference'] !== '') $where[] = "w.order_reference LIKE '%" . pSQL($filters['order_reference']) . "%'";
        if ($filters['email'] !== '') $where[] = "w.customer_email LIKE '%" . pSQL($filters['email']) . "%'";
        if (WithdrawalStatusPresenter::isValid($filters['status'])) $where[] = "w.status_code='" . pSQL($filters['status']) . "'";
        if ($filters['date_from'] !== '' && Validate::isDate($filters['date_from'])) $where[] = "w.submitted_at_utc >= '" . pSQL($filters['date_from'] . ' 00:00:00') . "'";
        if ($filters['date_to'] !== '' && Validate::isDate($filters['date_to'])) $where[] = "w.submitted_at_utc <= '" . pSQL($filters['date_to'] . ' 23:59:59') . "'";

        $page = max(1, (int) Tools::getValue('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;
        $base = ' FROM `' . _DB_PREFIX_ . 'jooim_withdrawal` w WHERE ' . implode(' AND ', $where);
        $total = (int) Db::getInstance()->getValue('SELECT COUNT(*)' . $base);
        $rows = Db::getInstance()->executeS(
            'SELECT w.*, (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_item` i WHERE i.id_jooim_withdrawal=w.id_jooim_withdrawal) AS item_count'
            . $base . ' ORDER BY w.id_jooim_withdrawal DESC LIMIT ' . $offset . ',' . $limit
        ) ?: [];
        $rows = WithdrawalStatusPresenter::decorateRows($this->module, $rows);

        $this->context->smarty->assign([
            'withdrawals' => $rows,
            'filters' => $filters,
            'status_options' => WithdrawalStatusPresenter::options($this->module),
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $limit)),
            'prev_page' => max(1, $page - 1),
            'next_page' => $page + 1,
            'token' => Tools::getAdminTokenLite('AdminJooimWithdrawal'),
            'current_url' => self::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminJooimWithdrawal'),
            'export_url' => self::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminJooimWithdrawal') . '&exportWithdrawalCsv=1',
        ]);
        $this->content = $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/list.tpl');
        $this->context->smarty->assign('content', $this->content);
    }

    private function renderDetail(int $id): void
    {
        $services = ServiceContainer::get($this->module);
        $row = $services->withdrawalRepository()->find($id);
        $shopIds = array_map('intval', Shop::getContextListShopID());
        if (!$shopIds) { $shopIds = [(int) $this->context->shop->id]; }
        if (!$row || !in_array((int) $row['id_shop'], $shopIds, true)) {
            $this->errors[] = $this->module->jooimTranslate('Withdrawal not found or not accessible in this shop context.', [], 'Modules.Jooimwithdrawal.Admin');
            $this->renderListPage();
            return;
        }
        $snapshot = json_decode((string) $row['payload_json'], true) ?: [];
        $refunds = Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_refund` WHERE id_jooim_withdrawal=' . $id . ' ORDER BY id_refund') ?: [];
        $row = WithdrawalStatusPresenter::decorateRow($this->module, $row);
        $history = WithdrawalStatusPresenter::decorateHistoryRows(
            $this->module,
            $services->withdrawalRepository()->history($id)
        );
        $this->context->smarty->assign([
            'withdrawal' => $row,
            'items' => $services->withdrawalRepository()->items($id),
            'history' => $history,
            'documents' => $services->withdrawalRepository()->documents($id),
            'refunds' => $refunds,
            'snapshot' => $snapshot,
            'status_options' => WithdrawalStatusPresenter::options($this->module),
            'token' => Tools::getAdminTokenLite('AdminJooimWithdrawal'),
            'current_url' => self::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminJooimWithdrawal'),
            'order_admin_url' => !empty($row['id_order']) ? $this->context->link->getAdminLink('AdminOrders') . '&vieworder&id_order=' . (int) $row['id_order'] : '',
            'document_download_base' => self::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminJooimWithdrawal') . '&downloadWithdrawalDocument=',
        ]);
        $this->content = $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->module->name . '/views/templates/admin/detail.tpl');
        $this->context->smarty->assign('content', $this->content);
    }

    private function findAccessibleWithdrawal(int $id): ?array
    {
        $row = ServiceContainer::get($this->module)->withdrawalRepository()->find($id);
        if (!$row || !in_array((int) $row['id_shop'], $this->allowedShopIds(), true)) {
            return null;
        }
        return $row;
    }

    private function assertAdminToken(): void
    {
        $provided = (string) Tools::getValue('token');
        if ($provided === '' || !hash_equals(Tools::getAdminTokenLite('AdminJooimWithdrawal'), $provided)) {
            http_response_code(403);
            exit('Forbidden');
        }
    }

    private function allowedShopIds(): array
    {
        $ids = array_map('intval', Shop::getContextListShopID());
        return $ids ?: [(int) $this->context->shop->id];
    }

    private function exportCsv(): void
    {
        $this->assertAdminToken();
        $shopIds = $this->allowedShopIds();
        $rows = Db::getInstance()->executeS(
            'SELECT reference,order_reference,source,scope,status_code,customer_firstname,customer_lastname,customer_email,'
            . 'submitted_at_utc,period_assessment,id_shop,payload_hash FROM `' . _DB_PREFIX_ . 'jooim_withdrawal` '
            . 'WHERE id_shop IN (' . implode(',', $shopIds) . ') ORDER BY id_jooim_withdrawal DESC'
        ) ?: [];
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="jooim-withdrawals-' . gmdate('Ymd-His') . '.csv"');
        header('Cache-Control: private, no-store, max-age=0');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['reference','order_reference','source','scope','status','status_code','firstname','lastname','email','submitted_at_utc','period_assessment','shop_id','payload_hash']);
        foreach ($rows as $row) {
            $values = [
                $row['reference'] ?? '',
                $row['order_reference'] ?? '',
                $row['source'] ?? '',
                $row['scope'] ?? '',
                WithdrawalStatusPresenter::label($this->module, (string) ($row['status_code'] ?? '')),
                $row['status_code'] ?? '',
                $row['customer_firstname'] ?? '',
                $row['customer_lastname'] ?? '',
                $row['customer_email'] ?? '',
                $row['submitted_at_utc'] ?? '',
                $row['period_assessment'] ?? '',
                $row['id_shop'] ?? '',
                $row['payload_hash'] ?? '',
            ];
            fputcsv($out, array_map([$this, 'csvSafe'], $values));
        }
        fclose($out);
        exit;
    }


    private function csvSafe($value): string
    {
        $value = (string) $value;
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }
        return $value;
    }

    private function downloadDocument(int $idDocument): void
    {
        $this->assertAdminToken();
        $shopIds = $this->allowedShopIds();
        $row = Db::getInstance()->getRow(
            'SELECT d.*,w.reference,w.id_shop FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_document` d '
            . 'INNER JOIN `' . _DB_PREFIX_ . 'jooim_withdrawal` w ON w.id_jooim_withdrawal=d.id_jooim_withdrawal '
            . 'WHERE d.id_document=' . (int) $idDocument . ' AND w.id_shop IN (' . implode(',', $shopIds) . ')'
        );
        if (!$row) {
            http_response_code(404);
            exit('Not found');
        }
        try {
            $content = ServiceContainer::get($this->module)->storage()->read((string) $row['storage_path']);
        } catch (Throwable $e) {
            http_response_code(404);
            exit('Not found');
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="withdrawal-' . rawurlencode((string) $row['reference']) . '.pdf"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, no-store, max-age=0');
        echo $content;
        exit;
    }
}
