<?php
require_once dirname(__DIR__, 2) . '/autoload.php'; // JOOIM_WITHDRAWAL_FRONT_AUTOLOAD

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Compatibility\FrontControllerBase;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class Jooim_WithdrawalReviewModuleFrontController extends FrontControllerBase
{
    public function initContent()
    {
        parent::initContent();
        if (!$this->ensureEnabled()) { $this->renderModule('message.tpl'); return; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            \Tools::redirect($this->context->link->getModuleLink($this->module->name, 'identify', [], true));
        }
        $idOrder = (int) \Tools::getValue('id_order');
        $access = $this->services()->accessResolver()->forOrder($idOrder);
        if (!$access) {
            \Tools::redirect($this->context->link->getModuleLink($this->module->name, 'identify', [], true));
        }
        $error = null;
        $draft = null;
        if (!$this->services()->csrf()->validate('withdrawal_form:' . $idOrder, (string) \Tools::getValue('csrf_token'), true)) {
            $error = $this->module->jooimTranslate('The form expired. Please start again.', [], 'Modules.Jooimwithdrawal.Validation');
        } else {
            try {
                $draft = $this->services()->drafts()->createForOrder(
                    $access['order'],
                    (string) $access['source'],
                    $access['guest_session'] ? (int) $access['guest_session']['id_guest_session'] : null,
                    [
                        'scope' => (string) \Tools::getValue('scope'),
                        'quantity' => (array) \Tools::getValue('quantity', []),
                        'reason' => (string) \Tools::getValue('reason'),
                        'note' => (string) \Tools::getValue('note'),
                        'phone' => (string) \Tools::getValue('phone'),
                    ],
                    $this->statement(),
                    $this->privacyText()
                );
            } catch (\Throwable $e) {
            // JOOIM_WITHDRAWAL_CATCH_DIAGNOSTICS
            JooimWithdrawalRuntimeDiagnostics::report($e, __METHOD__);
                $error = $e->getMessage();
            }
        }
        if (!$draft) {
            $this->context->smarty->assign(['message_title' => $this->module->jooimTranslate('Unable to prepare withdrawal', [], 'Modules.Jooimwithdrawal.Shop'), 'message_text' => $error]);
            $this->renderModule('message.tpl');
            return;
        }
        $this->context->smarty->assign([
            'draft' => $draft,
            'csrf_token' => $this->services()->csrf()->issue('withdrawal_submit:' . $draft['id_draft']),
            'submit_url' => $this->context->link->getModuleLink($this->module->name, 'submit', [], true),
        ]);
        $this->renderModule('review.tpl');
    }
}
