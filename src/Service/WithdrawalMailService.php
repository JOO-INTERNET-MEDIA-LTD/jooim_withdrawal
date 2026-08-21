<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

final class WithdrawalMailService
{
    private \Context $context;
    private \Module $module;
    private ModuleConfig $config;

    public function __construct(\Context $context, \Module $module, ModuleConfig $config)
    {
        $this->context = $context;
        $this->module = $module;
        $this->config = $config;
    }

    public function sendAccessLink(int $idLang, int $idShop, string $email, string $firstname, string $url, ?int $accessRequestId = null): bool
    {
        $subject = $this->module->jooimTranslate('Access to online contract withdrawal', [], 'Modules.Jooimwithdrawal.Email');
        $mailId = $this->log(null, $accessRequestId, 'withdrawal_access_link', $email, $subject);
        $ok = false;
        try {
            $ok = (bool) \Mail::Send(
                $idLang,
                'withdrawal_access_link',
                $subject,
                [
                    '{firstname}' => $firstname,
                    '{firstname_html}' => $this->h($firstname),
                    '{access_url}' => $url,
                    '{access_url_html}' => $this->h($url),
                    '{shop_name}' => (string) \Configuration::get('PS_SHOP_NAME'),
                    '{shop_name_html}' => $this->h((string) \Configuration::get('PS_SHOP_NAME')),
                    '{expires_minutes}' => (string) $this->config->int('JOOIM_WITHDRAWAL_MAGIC_TTL_MINUTES', 30),
                ],
                $email,
                null,
                null,
                null,
                null,
                null,
                _PS_MODULE_DIR_ . $this->module->name . '/mails/',
                false,
                $idShop
            );
            $this->finishLog($mailId, $ok, $ok ? null : 'Mail::Send returned false.');
        } catch (\Throwable $e) {
            $this->finishLog($mailId, false, $e->getMessage());
        }
        return $ok;
    }

    public function sendAcknowledgement(array $withdrawal, array $snapshot, ?string $pdfContent = null): bool
    {
        $idLang = (int) $withdrawal['id_lang'];
        $idShop = (int) $withdrawal['id_shop'];
        $email = (string) $withdrawal['customer_email'];
        $subject = $this->module->jooimTranslate(
            'Acknowledgement of contract withdrawal – %reference%',
            ['%reference%' => (string) $withdrawal['reference']],
            'Modules.Jooimwithdrawal.Email'
        );
        $mailId = $this->log((int) $withdrawal['id_jooim_withdrawal'], null, 'withdrawal_acknowledgement', $email, $subject);
        $attachment = null;
        if ($pdfContent !== null && $this->config->bool('JOOIM_WITHDRAWAL_ATTACH_PDF', true)) {
            $attachment = [
                'content' => $pdfContent,
                'name' => 'withdrawal-' . preg_replace('/[^A-Za-z0-9_.-]/', '_', (string) $withdrawal['reference']) . '.pdf',
                'mime' => 'application/pdf',
            ];
        }
        $merchant = $snapshot['merchant'] ?? [];
        [$itemsText, $itemsHtml] = $this->formatItems($snapshot);
        $ok = false;
        try {
            $ok = (bool) \Mail::Send(
                $idLang,
                'withdrawal_acknowledgement',
                $subject,
                [
                    '{firstname}' => (string) $withdrawal['customer_firstname'],
                    '{firstname_html}' => $this->h((string) $withdrawal['customer_firstname']),
                    '{lastname}' => (string) $withdrawal['customer_lastname'],
                    '{lastname_html}' => $this->h((string) $withdrawal['customer_lastname']),
                    '{customer_email}' => (string) $withdrawal['customer_email'],
                    '{customer_email_html}' => $this->h((string) $withdrawal['customer_email']),
                    '{withdrawal_reference}' => (string) $withdrawal['reference'],

                    '{withdrawal_reference_html}' => $this->h((string) ((string) $withdrawal['reference'])),
                    '{order_reference}' => (string) $withdrawal['order_reference'],

                    '{order_reference_html}' => $this->h((string) ((string) $withdrawal['order_reference'])),
                    '{submitted_at}' => (string) ($snapshot['submitted_at'] ?? $withdrawal['submitted_at_utc']),

                    '{submitted_at_html}' => $this->h((string) ((string) ($snapshot['submitted_at'] ?? $withdrawal['submitted_at_utc']))),
                    '{submitted_at_utc}' => (string) ($snapshot['submitted_at_utc'] ?? $withdrawal['submitted_at_utc']),

                    '{submitted_at_utc_html}' => $this->h((string) ((string) ($snapshot['submitted_at_utc'] ?? $withdrawal['submitted_at_utc']))),
                    '{scope}' => (string) $withdrawal['scope'],

                    '{scope_html}' => $this->h((string) ((string) $withdrawal['scope'])),
                    '{statement}' => (string) $withdrawal['statement_text'],

                    '{statement_html}' => $this->h((string) ((string) $withdrawal['statement_text'])),
                    '{items_text}' => $itemsText,
                    '{items_html}' => $itemsHtml,
                    '{merchant_name}' => (string) ($merchant['name'] ?? ''),

                    '{merchant_name_html}' => $this->h((string) ((string) ($merchant['name'] ?? ''))),
                    '{merchant_address}' => (string) ($merchant['address'] ?? ''),

                    '{merchant_address_html}' => $this->h((string) ((string) ($merchant['address'] ?? ''))),
                    '{merchant_identifier}' => (string) ($merchant['identifier'] ?? ''),

                    '{merchant_identifier_html}' => $this->h((string) ((string) ($merchant['identifier'] ?? ''))),
                    '{merchant_email}' => (string) ($merchant['email'] ?? ''),

                    '{merchant_email_html}' => $this->h((string) ((string) ($merchant['email'] ?? ''))),
                    '{return_address}' => (string) ($merchant['return_address'] ?? ''),

                    '{return_address_html}' => $this->h((string) ((string) ($merchant['return_address'] ?? ''))),
                    '{payload_hash}' => (string) $withdrawal['payload_hash'],

                    '{payload_hash_html}' => $this->h((string) ((string) $withdrawal['payload_hash'])),
                ],
                $email,
                null,
                null,
                null,
                $attachment,
                null,
                _PS_MODULE_DIR_ . $this->module->name . '/mails/',
                false,
                $idShop
            );
            $this->finishLog($mailId, $ok, $ok ? null : 'Mail::Send returned false.');
        } catch (\Throwable $e) {
            $this->finishLog($mailId, false, $e->getMessage());
        }
        if ($ok) {
            \Db::getInstance()->update('jooim_withdrawal', ['acknowledgement_sent_at' => gmdate('Y-m-d H:i:s')], 'id_jooim_withdrawal=' . (int) $withdrawal['id_jooim_withdrawal']);
        }
        return $ok;
    }

    public function sendStatusNotification(array $withdrawal, string $publicMessage = ''): bool
    {
        $templateMap = [
            'goods_received' => 'withdrawal_goods_received',
            'refunded' => 'withdrawal_refund_completed',
            'rejected' => 'withdrawal_rejected',
            'awaiting_goods' => 'withdrawal_return_instructions',
        ];
        $template = $templateMap[(string) $withdrawal['status_code']] ?? 'withdrawal_status_changed';
        $locale = $this->localeForLanguage((int) $withdrawal['id_lang']);
        $statusLabel = WithdrawalStatusPresenter::label(
            $this->module,
            (string) $withdrawal['status_code'],
            WithdrawalStatusPresenter::EMAIL_DOMAIN,
            $locale
        );
        $subject = $this->module->jooimTranslate(
            'Contract withdrawal update – %reference%',
            ['%reference%' => (string) $withdrawal['reference']],
            'Modules.Jooimwithdrawal.Email',
            $locale
        );
        $mailId = $this->log((int) $withdrawal['id_jooim_withdrawal'], null, $template, (string) $withdrawal['customer_email'], $subject);
        $snapshot = json_decode((string) ($withdrawal['payload_json'] ?? ''), true) ?: [];
        $merchant = is_array($snapshot['merchant'] ?? null) ? $snapshot['merchant'] : [];
        $merchantName = (string) ($merchant['name'] ?? '');
        $merchantEmail = (string) ($merchant['email'] ?? '');
        $returnAddress = (string) ($merchant['return_address'] ?? '');
        $ok = false;
        try {
            $ok = (bool) \Mail::Send(
                (int) $withdrawal['id_lang'],
                $template,
                $subject,
                [
                    '{firstname}' => (string) $withdrawal['customer_firstname'],
                    '{withdrawal_reference}' => (string) $withdrawal['reference'],
                    '{order_reference}' => (string) $withdrawal['order_reference'],
                    '{status}' => $statusLabel,
                    '{status_html}' => $this->h($statusLabel),
                    '{public_message}' => $publicMessage,
                    '{public_message_html}' => nl2br($this->h($publicMessage)),
                    '{merchant_name}' => $merchantName,
                    '{merchant_name_html}' => $this->h($merchantName),
                    '{merchant_email}' => $merchantEmail,
                    '{merchant_email_html}' => $this->h($merchantEmail),
                    '{return_address}' => $returnAddress,
                    '{return_address_html}' => nl2br($this->h($returnAddress)),
                ],
                (string) $withdrawal['customer_email'],
                null,
                null,
                null,
                null,
                null,
                _PS_MODULE_DIR_ . $this->module->name . '/mails/',
                false,
                (int) $withdrawal['id_shop']
            );
            $this->finishLog($mailId, $ok, $ok ? null : 'Mail::Send returned false.');
        } catch (\Throwable $e) {
            $this->finishLog($mailId, false, $e->getMessage());
        }
        return $ok;
    }

    public function sendAdminNotification(array $withdrawal): void
    {
        $email = $this->config->get('JOOIM_WITHDRAWAL_MERCHANT_EMAIL', (string) \Configuration::get('PS_SHOP_EMAIL'));
        if (!\Validate::isEmail($email)) {
            return;
        }
        $locale = $this->localeForLanguage((int) $withdrawal['id_lang']);
        $statusLabel = WithdrawalStatusPresenter::label(
            $this->module,
            (string) $withdrawal['status_code'],
            WithdrawalStatusPresenter::EMAIL_DOMAIN,
            $locale
        );
        $subject = '[Withdrawal] ' . (string) $withdrawal['reference'];
        try {
            \Mail::Send(
                (int) $withdrawal['id_lang'], 'withdrawal_admin_notification', $subject,
                [
                    '{withdrawal_reference}' => (string) $withdrawal['reference'],
                    '{withdrawal_reference_html}' => $this->h((string) $withdrawal['reference']),
                    '{order_reference}' => (string) $withdrawal['order_reference'],
                    '{order_reference_html}' => $this->h((string) $withdrawal['order_reference']),
                    '{customer_email}' => (string) $withdrawal['customer_email'],
                    '{customer_email_html}' => $this->h((string) $withdrawal['customer_email']),
                    '{status}' => $statusLabel,
                    '{status_html}' => $this->h($statusLabel),
                    '{merchant_name}' => $this->config->get('JOOIM_WITHDRAWAL_MERCHANT_NAME', (string) \Configuration::get('PS_SHOP_NAME')),
                    '{merchant_name_html}' => $this->h($this->config->get('JOOIM_WITHDRAWAL_MERCHANT_NAME', (string) \Configuration::get('PS_SHOP_NAME'))),
                ],
                $email, null, null, null, null, null,
                _PS_MODULE_DIR_ . $this->module->name . '/mails/', false, (int) $withdrawal['id_shop']
            );
        } catch (\Throwable $e) {
            \PrestaShopLogger::addLog('JOOIM Withdrawal admin mail failed: ' . $e->getMessage(), 2);
        }
    }

    private function localeForLanguage(int $idLang): ?string
    {
        try {
            $language = new \Language($idLang);
            if (\Validate::isLoadedObject($language)) {
                if (!empty($language->locale)) {
                    return str_replace('_', '-', (string) $language->locale);
                }
                if (!empty($language->language_code)) {
                    return str_replace('_', '-', (string) $language->language_code);
                }
            }
        } catch (\Throwable $exception) {
            // English source label is the safe fallback.
        }

        return null;
    }

    private function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function formatItems(array $snapshot): array
    {
        $lines = [];
        foreach (($snapshot['items'] ?? []) as $item) {
            $name = trim((string) ($item['product_name'] ?? ''));
            $reference = trim((string) ($item['product_reference'] ?? ''));
            $qty = (int) ($item['withdrawal_quantity'] ?? 0);
            $lines[] = trim($name . ($reference !== '' ? ' [' . $reference . ']' : '') . ' × ' . $qty);
        }
        if ($lines === [] && !empty($snapshot['manual_items_description'])) {
            $lines[] = (string) $snapshot['manual_items_description'];
        }
        if ($lines === []) {
            $lines[] = (string) (($snapshot['scope'] ?? '') === 'full' ? 'Entire contract/order' : 'See withdrawal statement');
        }
        $text = implode("\n", $lines);
        $html = implode('<br>', array_map(static fn(string $line): string => htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $lines));
        return [$text, $html];
    }

    private function log(?int $withdrawalId, ?int $accessId, string $template, string $email, string $subject): int
    {
        \Db::getInstance()->insert('jooim_withdrawal_mail', [
            'id_jooim_withdrawal' => $withdrawalId ?: null,
            'id_access_request' => $accessId ?: null,
            'template_code' => pSQL($template),
            'recipient_email' => pSQL($email),
            'subject_snapshot' => pSQL($subject),
            'status' => 'sending',
            'attempt_count' => 1,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ], true);
        return (int) \Db::getInstance()->Insert_ID();
    }

    private function finishLog(int $id, bool $ok, ?string $error): void
    {
        \Db::getInstance()->update('jooim_withdrawal_mail', [
            'status' => $ok ? 'sent' : 'failed',
            'error_message' => $error === null ? null : pSQL(substr($error, 0, 4000)),
            'sent_at' => $ok ? gmdate('Y-m-d H:i:s') : null,
            'next_attempt_at' => $ok ? null : gmdate('Y-m-d H:i:s', time() + 300),
        ], 'id_mail=' . (int) $id, 0, true);
    }
}
