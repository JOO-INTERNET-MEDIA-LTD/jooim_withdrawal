<?php
require_once dirname(__DIR__, 2) . '/autoload.php'; // JOOIM_WITHDRAWAL_FRONT_AUTOLOAD

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Compatibility\FrontControllerBase;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class Jooim_WithdrawalDirectModuleFrontController extends FrontControllerBase
{
    public function initContent()
    {
        $this->runPage(function (): void {
            if (!$this->ensureEnabled()) {
                $this->renderModule('message.tpl');
                return;
            }
            if (!$this->services()->config()->bool('JOOIM_WITHDRAWAL_DIRECT_FUNCTION', true)) {
                \Tools::redirect($this->context->link->getModuleLink(
                    $this->module->name,
                    'identify',
                    [],
                    true
                ));
            }
            $this->context->smarty->assign([
                'submission_error' => $this->submissionErrorMessage(),
                'csrf_token' => $this->services()->csrf()->issue('direct_withdrawal'),
                'review_url' => $this->context->link->getModuleLink(
                    $this->module->name,
                    'directreview',
                    [],
                    true
                ),
                'note_max_length' => $this->services()->config()->int(
                    'JOOIM_WITHDRAWAL_NOTE_MAX_LENGTH',
                    2000
                ),
            ]);
            $this->renderModule('direct.tpl');
        });
    }
}
