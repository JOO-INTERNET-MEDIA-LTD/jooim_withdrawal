<?php
require_once dirname(__DIR__, 2) . '/autoload.php'; // JOOIM_WITHDRAWAL_FRONT_AUTOLOAD

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Compatibility\FrontControllerBase;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class Jooim_WithdrawalVerifyModuleFrontController extends FrontControllerBase
{
    public function initContent()
    {
        parent::initContent();
        if (!$this->ensureEnabled()) { $this->renderModule('message.tpl'); return; }
        $token = (string) \Tools::getValue('t');
        $valid = $token !== '' && $this->services()->guestAccess()->peek($token) !== null;
        $error = null;

        if (\Tools::isSubmit('submitVerify')) {
            if (!$this->services()->csrf()->validate('verify:' . hash('sha256', $token), (string) \Tools::getValue('csrf_token'), true)) {
                $error = $this->module->jooimTranslate('The verification page expired. Open the link from the email again.', [], 'Modules.Jooimwithdrawal.Validation');
            } else {
                $result = $this->services()->guestAccess()->consume($token);
                if ($result && isset($result['order'])) {
                    \Tools::redirect($this->context->link->getModuleLink($this->module->name, 'form', ['id_order' => (int) $result['order']->id], true));
                }
                $valid = false;
                $error = $this->module->jooimTranslate('This access link is invalid, expired or already used.', [], 'Modules.Jooimwithdrawal.Validation');
            }
        }

        $this->context->smarty->assign([
            'token' => $token,
            'token_valid' => $valid,
            'form_error' => $error,
            'csrf_token' => $valid ? $this->services()->csrf()->issue('verify:' . hash('sha256', $token)) : '',
        ]);
        $this->renderModule('verify.tpl');
    }
}
