<?php
require_once dirname(__DIR__, 2) . '/autoload.php'; // JOOIM_WITHDRAWAL_FRONT_AUTOLOAD

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Compatibility\FrontControllerBase;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class Jooim_WithdrawalFormModuleFrontController extends FrontControllerBase
{
    public function initContent()
    {
        parent::initContent();
        if (!$this->ensureEnabled()) { $this->renderModule('message.tpl'); return; }
        $idOrder = (int) \Tools::getValue('id_order');
        $access = $this->services()->accessResolver()->forOrder($idOrder);
        if (!$access) {
            \Tools::redirect($this->context->link->getModuleLink($this->module->name, 'identify', [], true));
        }
        $order = $access['order'];
        $identity = $this->services()->identities()->resolve($order);
        $items = $this->services()->quantities()->orderItems($order);
        $this->context->smarty->assign([
            'order' => $order,
            'identity' => $identity,
            'items' => $items,
            'csrf_token' => $this->services()->csrf()->issue('withdrawal_form:' . $idOrder),
            'review_url' => $this->context->link->getModuleLink($this->module->name, 'review', [], true),
            'reason_options' => [
                'not_suitable' => $this->module->jooimTranslate('The product does not suit me', [], 'Modules.Jooimwithdrawal.Shop'),
                'wrong_variant' => $this->module->jooimTranslate('Wrong size or variant', [], 'Modules.Jooimwithdrawal.Shop'),
                'ordered_by_mistake' => $this->module->jooimTranslate('The order was created by mistake', [], 'Modules.Jooimwithdrawal.Shop'),
                'not_needed' => $this->module->jooimTranslate('I no longer need the product', [], 'Modules.Jooimwithdrawal.Shop'),
                'other' => $this->module->jooimTranslate('Other reason', [], 'Modules.Jooimwithdrawal.Shop'),
                'no_reason' => $this->module->jooimTranslate('I do not want to state a reason', [], 'Modules.Jooimwithdrawal.Shop'),
            ],
            'note_max_length' => $this->services()->config()->int('JOOIM_WITHDRAWAL_NOTE_MAX_LENGTH', 2000),
        ]);
        $this->renderModule('form.tpl');
    }
}
