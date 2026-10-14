<?php
require_once dirname(__DIR__, 2) . '/autoload.php'; // JOOIM_WITHDRAWAL_FRONT_AUTOLOAD

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Compatibility\FrontControllerBase;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class Jooim_WithdrawalDirectreviewModuleFrontController extends FrontControllerBase
{
    public function initContent()
    {
        parent::initContent();
        if (!$this->ensureEnabled()) { $this->renderModule('message.tpl'); return; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            \Tools::redirect($this->context->link->getModuleLink($this->module->name, 'direct', [], true));
        }
        if (!$this->services()->csrf()->validate('direct_withdrawal', (string) \Tools::getValue('csrf_token'), true)) {
            $this->context->smarty->assign(['message_title' => $this->module->jooimTranslate('Form expired', [], 'Modules.Jooimwithdrawal.Shop'), 'message_text' => $this->module->jooimTranslate('The form expired. Please try again.', [], 'Modules.Jooimwithdrawal.Validation')]);
            $this->renderModule('message.tpl');
            return;
        }
        $reference = (string) \Tools::getValue('order_reference');
        $normalized = $this->services()->orderLookup()->normalize($reference) ?? strtoupper(trim(substr($reference, 0, 64)));
        $refHmac = $this->services()->tokens()->referenceHmac($normalized, (int) $this->context->shop->id);
        // The mandatory direct withdrawal function is never blocked by the low anti-enumeration
        // limits used by the order-discovery/magic-link flow. Abuse is still auditable through
        // pseudonymous request hashes and the form is protected by CSRF and idempotent submission.
        \Db::getInstance()->insert('jooim_withdrawal_access_request', [
            'id_shop' => (int) $this->context->shop->id,
            'id_order' => null,
            'order_reference_hmac' => pSQL($refHmac),
            'token_hash' => null,
            'purpose' => 'direct_withdrawal',
            'expires_at' => null,
            'request_ip_hash' => pSQL($this->services()->tokens()->ipHash($this->ip())),
            'request_user_agent_hash' => pSQL($this->services()->tokens()->userAgentHash($this->ua())),
            'mail_status' => 'not_required',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ], true);
        try {
            $draft = $this->services()->drafts()->createDirect([
                'firstname' => (string) \Tools::getValue('firstname'),
                'lastname' => (string) \Tools::getValue('lastname'),
                'email' => (string) \Tools::getValue('email'),
                'order_reference' => $reference,
                'scope' => (string) \Tools::getValue('scope'),
                'manual_items_description' => (string) \Tools::getValue('manual_items_description'),
                'phone' => (string) \Tools::getValue('phone'),
                'note' => (string) \Tools::getValue('note'),
            ], $this->statement(), $this->privacyText());
        } catch (\Throwable $e) {
            // JOOIM_WITHDRAWAL_CATCH_DIAGNOSTICS
            JooimWithdrawalRuntimeDiagnostics::report($e, __METHOD__);
            $this->context->smarty->assign(['message_title' => $this->module->jooimTranslate('Unable to prepare withdrawal', [], 'Modules.Jooimwithdrawal.Shop'), 'message_text' => $this->module->jooimTranslate('Unable to prepare withdrawal', [], 'Modules.Jooimwithdrawal.Shop')]);
            $this->renderModule('message.tpl');
            return;
        }
        $this->context->smarty->assign([
            'draft' => $draft,
            'csrf_token' => $this->services()->csrf()->issue('direct_submit:' . $draft['id_draft']),
            'submit_url' => $this->context->link->getModuleLink($this->module->name, 'directsubmit', [], true),
        ]);
        $this->renderModule('direct-review.tpl');
    }
}
