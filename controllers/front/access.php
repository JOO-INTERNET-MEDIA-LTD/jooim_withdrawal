<?php
require_once dirname(__DIR__, 2) . '/autoload.php'; // JOOIM_WITHDRAWAL_FRONT_AUTOLOAD

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Compatibility\FrontControllerBase;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class Jooim_WithdrawalAccessModuleFrontController extends FrontControllerBase
{
    public function initContent()
    {
        $token = (string) \Tools::getValue('t');
        \Tools::redirect($this->context->link->getModuleLink($this->module->name, 'verify', ['t' => $token], true));
    }
}
