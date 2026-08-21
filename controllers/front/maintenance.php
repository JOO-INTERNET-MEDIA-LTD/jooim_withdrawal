<?php
require_once dirname(__DIR__, 2) . '/autoload.php'; // JOOIM_WITHDRAWAL_FRONT_AUTOLOAD

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Compatibility\FrontControllerBase;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class Jooim_WithdrawalMaintenanceModuleFrontController extends FrontControllerBase
{
    public $ajax = true;

    public function display()
    {
        $expected = (string) \Configuration::get('JOOIM_WITHDRAWAL_CRON_TOKEN');
        $provided = (string) \Tools::getValue('token');
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            http_response_code(404);
            $this->ajaxRender('Not found');
            return;
        }
        $result = $this->services()->maintenance()->run();
        header('Content-Type: application/json');
        $this->ajaxRender(json_encode(['ok' => true, 'result' => $result]));
    }
}
