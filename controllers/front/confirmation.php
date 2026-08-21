<?php
require_once dirname(__DIR__, 2) . '/autoload.php'; // JOOIM_WITHDRAWAL_FRONT_AUTOLOAD

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Compatibility\FrontControllerBase;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class Jooim_WithdrawalConfirmationModuleFrontController extends FrontControllerBase
{
    public function initContent()
    {
        $this->runPage(function (): void {
            $receipt = (string) \Tools::getValue('r');
            $id = $this->services()->tokens()->validateReceipt($receipt, (int) $this->context->shop->id);
            $withdrawal = $id
                ? $this->services()->withdrawalRepository()->find($id, (int) $this->context->shop->id)
                : null;
            if (!$withdrawal) {
                http_response_code(404);
                $this->context->smarty->assign([
                    'message_title' => $this->module->jooimTranslate('Confirmation unavailable', [], 'Modules.Jooimwithdrawal.Shop'),
                    'message_text' => $this->module->jooimTranslate('This confirmation link is invalid or expired.', [], 'Modules.Jooimwithdrawal.Validation'),
                ]);
                $this->renderModule('message.tpl');
                return;
            }

            $canDownload = !empty($withdrawal['id_order'])
                && $this->services()->accessResolver()->forOrder((int) $withdrawal['id_order']) !== null;
            $this->context->smarty->assign([
                'withdrawal' => $withdrawal,
                'can_download' => $canDownload,
                'document_url' => $canDownload
                    ? $this->context->link->getModuleLink($this->module->name, 'document', ['id' => (int) $withdrawal['id_jooim_withdrawal']], true)
                    : '',
            ]);
            $this->renderModule('confirmation.tpl');
        });
    }
}
