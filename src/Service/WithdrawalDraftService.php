<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Security\TokenService;

final class WithdrawalDraftService
{
    private \Context $context;
    private ModuleConfig $config;
    private TokenService $tokens;
    private OrderLookupService $lookup;
    private OrderIdentityService $identities;
    private WithdrawalQuantityCalculator $quantities;

    public function __construct(\Context $context, ModuleConfig $config, TokenService $tokens, OrderLookupService $lookup, OrderIdentityService $identities, WithdrawalQuantityCalculator $quantities)
    {
        $this->context = $context;
        $this->config = $config;
        $this->tokens = $tokens;
        $this->lookup = $lookup;
        $this->identities = $identities;
        $this->quantities = $quantities;
    }

    public function createForOrder(\Order $order, string $source, ?int $guestSessionId, array $input, string $statement, string $privacyText): array
    {
        $scope = in_array(($input['scope'] ?? ''), ['full', 'partial'], true) ? (string) $input['scope'] : '';
        if ($scope === '') {
            throw new \InvalidArgumentException('Invalid withdrawal scope.');
        }
        $identity = $this->identities->resolve($order);
        if (!$identity) {
            throw new \RuntimeException('Order identity is unavailable.');
        }
        $availableItems = $this->quantities->orderItems($order);
        $selected = [];
        $requested = is_array($input['quantity'] ?? null) ? $input['quantity'] : [];
        foreach ($availableItems as $item) {
            if ($item['available_quantity'] <= 0) {
                continue;
            }
            $qty = $scope === 'full'
                ? (int) $item['available_quantity']
                : max(0, (int) ($requested[$item['id_order_detail']] ?? 0));
            if ($qty <= 0) {
                continue;
            }
            if ($qty > (int) $item['available_quantity']) {
                throw new \InvalidArgumentException('Requested quantity exceeds available quantity.');
            }
            $item['withdrawal_quantity'] = $qty;
            $selected[] = $item;
        }
        if ($selected === []) {
            throw new \InvalidArgumentException('Select at least one item to withdraw.');
        }
        $note = trim((string) ($input['note'] ?? ''));
        if (mb_strlen($note) > $this->config->int('JOOIM_WITHDRAWAL_NOTE_MAX_LENGTH', 2000)) {
            throw new \InvalidArgumentException('Note is too long.');
        }
        $payload = [
            'schema_version' => '1.0',
            'source' => $source,
            'scope' => $scope,
            'id_shop' => (int) $order->id_shop,
            'id_order' => (int) $order->id,
            'id_customer' => (int) $order->id_customer,
            'id_lang' => (int) $this->context->language->id,
            'id_currency' => (int) $order->id_currency,
            'order_reference' => (string) $order->reference,
            'order_date' => (string) $order->date_add,
            'customer' => [
                'firstname' => (string) $identity['firstname'],
                'lastname' => (string) $identity['lastname'],
                'email' => (string) $identity['email'],
                'phone' => trim((string) ($input['phone'] ?? '')),
            ],
            'reason_code' => $this->sanitizeReason((string) ($input['reason'] ?? '')),
            'customer_note' => $note,
            'statement_text' => $statement,
            'items' => $selected,
            'privacy' => $this->privacySnapshot($privacyText),
            'legal_text_version' => 'eu-crd-11a-2026-06',
            'form_version' => '1.0',
        ];
        return $this->persist($payload, $guestSessionId);
    }

    public function createDirect(array $input, string $statement, string $privacyText): array
    {
        $firstname = trim((string) ($input['firstname'] ?? ''));
        $lastname = trim((string) ($input['lastname'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $orderReferenceInput = trim((string) ($input['order_reference'] ?? ''));
        $scope = in_array(($input['scope'] ?? ''), ['full', 'partial'], true) ? (string) $input['scope'] : 'full';
        $manualItems = trim((string) ($input['manual_items_description'] ?? ''));
        if ($firstname === '' || $lastname === '' || !\Validate::isEmail($email) || $orderReferenceInput === '') {
            throw new \InvalidArgumentException('Name, contract/order reference and a valid confirmation email are required.');
        }
        if ($scope === 'partial' && $manualItems === '') {
            throw new \InvalidArgumentException('Describe the goods or contract part you want to withdraw from.');
        }
        $note = trim((string) ($input['note'] ?? ''));
        if (mb_strlen($note) > $this->config->int('JOOIM_WITHDRAWAL_NOTE_MAX_LENGTH', 2000)) {
            throw new \InvalidArgumentException('Note is too long.');
        }
        if (mb_strlen($manualItems) > 2000) {
            throw new \InvalidArgumentException('Selected-items description is too long.');
        }

        $order = $this->lookup->findByPublicReference($orderReferenceInput);
        $normalized = $this->lookup->normalize($orderReferenceInput) ?? strtoupper(substr($orderReferenceInput, 0, 64));
        // Direct Article 11a path deliberately does not expose or copy hidden order-line data.
        // The order may be associated internally for back-office reconciliation, but the receipt only
        // contains data supplied by the consumer until ownership is verified.
        $items = [];

        $payload = [
            'schema_version' => '1.0',
            'source' => 'guest_direct',
            'scope' => $scope,
            'id_shop' => (int) $this->context->shop->id,
            'id_order' => $order ? (int) $order->id : null,
            'id_customer' => $order ? (int) $order->id_customer : null,
            'id_lang' => (int) $this->context->language->id,
            'id_currency' => $order ? (int) $order->id_currency : null,
            'order_reference' => $normalized,
            'order_date' => $order ? (string) $order->date_add : null,
            'customer' => [
                'firstname' => mb_substr($firstname, 0, 128),
                'lastname' => mb_substr($lastname, 0, 128),
                'email' => mb_substr($email, 0, 254),
                'phone' => trim((string) ($input['phone'] ?? '')),
            ],
            'reason_code' => null,
            'customer_note' => $note,
            'statement_text' => $statement,
            'items' => $items,
            'manual_items_description' => $scope === 'partial' ? $manualItems : '',
            'privacy' => $this->privacySnapshot($privacyText),
            'legal_text_version' => 'eu-crd-11a-2026-06',
            'form_version' => '1.0-direct',
            // A public reference match is reconciliation data, never authentication.
            'requires_manual_review' => true,
        ];
        return $this->persist($payload, null);
    }

    public function get(int $idDraft, string $plainKey): ?array
    {
        return $this->findDraft($idDraft, $plainKey, false);
    }

    /**
     * Returns an unexpired draft or an already-submitted draft for an
     * idempotent retry of the final POST. The high-entropy draft key remains
     * mandatory in both cases.
     */
    public function getForSubmission(int $idDraft, string $plainKey): ?array
    {
        return $this->findDraft($idDraft, $plainKey, true);
    }

    private function findDraft(int $idDraft, string $plainKey, bool $allowSubmitted): ?array
    {
        $hash = $this->tokens->hash($plainKey, 'draft_idempotency');
        $condition = $allowSubmitted
            ? ' AND (submitted_at IS NOT NULL OR expires_at > UTC_TIMESTAMP())'
            : ' AND submitted_at IS NULL AND expires_at > UTC_TIMESTAMP()';
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'jooim_withdrawal_draft` WHERE id_draft=' . (int) $idDraft
            . ' AND id_shop=' . (int) $this->context->shop->id
            . " AND idempotency_key_hash='" . pSQL($hash) . "'" . $condition,
            false
        );
        if (!$row) {
            return null;
        }
        $row['payload'] = json_decode((string) $row['draft_payload'], true);
        return is_array($row['payload']) ? $row : null;
    }

    private function persist(array $payload, ?int $guestSessionId): array
    {
        $plain = $this->tokens->randomToken(32);
        $hash = $this->tokens->hash($plain, 'draft_idempotency');
        $now = gmdate('Y-m-d H:i:s');
        $expires = gmdate('Y-m-d H:i:s', time() + 30 * 60);
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payloadJson === false) {
            throw new \RuntimeException('Cannot encode withdrawal draft.');
        }

        $db = \Db::getInstance();
        $inserted = $db->insert('jooim_withdrawal_draft', [
            'id_shop' => (int) $payload['id_shop'],
            'id_order' => $payload['id_order'] ? (int) $payload['id_order'] : null,
            'id_customer' => $payload['id_customer'] ? (int) $payload['id_customer'] : null,
            'guest_session_id' => $guestSessionId ?: null,
            'source' => pSQL((string) $payload['source']),
            'scope' => pSQL((string) $payload['scope']),
            'draft_payload' => pSQL($payloadJson, true),
            'idempotency_key_hash' => pSQL($hash),
            'expires_at' => $expires,
            'created_at' => $now,
            'updated_at' => $now,
        ], true, false);
        if (!$inserted) {
            $error = trim((string) $db->getMsgError()) ?: 'unknown database error';
            throw new \RuntimeException('Cannot create withdrawal draft: ' . $error);
        }
        $id = (int) $db->Insert_ID();
        if ($id <= 0) {
            throw new \RuntimeException('Cannot create withdrawal draft.');
        }
        return ['id_draft' => $id, 'key' => $plain, 'payload' => $payload, 'expires_at' => $expires];
    }

    private function privacySnapshot(string $text): array
    {
        return [
            'version' => 'privacy-1.0',
            'content' => $text,
            'content_hash' => hash('sha256', $text),
            'url' => $this->config->get('JOOIM_WITHDRAWAL_PRIVACY_URL', ''),
            'shown_at_utc' => gmdate('c'),
            'language' => (string) $this->context->language->iso_code,
        ];
    }

    private function sanitizeReason(string $reason): ?string
    {
        $allowed = ['not_suitable','wrong_variant','ordered_by_mistake','not_needed','other','no_reason'];
        return in_array($reason, $allowed, true) ? $reason : null;
    }
}
