<?php
require_once dirname(__DIR__, 2) . '/autoload.php'; // JOOIM_WITHDRAWAL_FRONT_AUTOLOAD

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Compatibility\FrontControllerBase;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class Jooim_WithdrawalIdentifyModuleFrontController extends FrontControllerBase
{
    public function initContent()
    {
        $this->runPage(function (): void {
            if (!$this->ensureEnabled()) {
                $this->renderModule('message.tpl');
                return;
            }

            $sent = false;
            $error = $this->submissionErrorMessage();
            if (\Tools::isSubmit('submitIdentify')) {
                $csrf = (string) \Tools::getValue('csrf_token');
                if (!$this->services()->csrf()->validate('identify', $csrf, true)) {
                    $error = $this->module->jooimTranslate(
                        'The form expired. Please try again.',
                        [],
                        'Modules.Jooimwithdrawal.Validation'
                    );
                } else {
                    $reference = (string) \Tools::getValue('order_reference');
                    $this->services()->guestAccess()->request($reference, $this->ip(), $this->ua());
                    $sent = true;
                }
            }

            $this->context->smarty->assign([
                'csrf_token' => $this->services()->csrf()->issue('identify'),
                'sent' => $sent,
                'form_error' => $error,
                'direct_function_enabled' => $this->services()->config()->bool(
                    'JOOIM_WITHDRAWAL_DIRECT_FUNCTION',
                    true
                ),
            ]);
            $this->renderModule('identify.tpl');
        });
    }
}
