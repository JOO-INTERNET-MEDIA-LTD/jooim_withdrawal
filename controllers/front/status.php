<?php
require_once dirname(__DIR__, 2) . '/autoload.php'; // JOOIM_WITHDRAWAL_FRONT_AUTOLOAD

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Compatibility\FrontControllerBase;
use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service\WithdrawalStatusPresenter;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class Jooim_WithdrawalStatusModuleFrontController extends FrontControllerBase
{
    public function initContent()
    {
        parent::initContent();
        $receipt = (string) \Tools::getValue('r');
        $id = $this->services()->tokens()->validateReceipt($receipt, (int) $this->context->shop->id);
        $withdrawal = $id ? $this->services()->withdrawalRepository()->find($id, (int) $this->context->shop->id) : null;
        if (!$withdrawal) {
            http_response_code(404);
            $this->context->smarty->assign(['message_title' => $this->module->jooimTranslate('Not found', [], 'Modules.Jooimwithdrawal.Shop'), 'message_text' => $this->module->jooimTranslate('This status link is invalid or expired.', [], 'Modules.Jooimwithdrawal.Validation')]);
            $this->renderModule('message.tpl');
            return;
        }
        $withdrawal = WithdrawalStatusPresenter::decorateRow(
            $this->module,
            $withdrawal,
            WithdrawalStatusPresenter::SHOP_DOMAIN
        );
        $this->context->smarty->assign(['withdrawal' => $withdrawal]);
        $this->renderModule('status.tpl');
    }
}
