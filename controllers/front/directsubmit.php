<?php
require_once dirname(__DIR__, 2) . '/autoload.php'; // JOOIM_WITHDRAWAL_FRONT_AUTOLOAD

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Compatibility\FrontControllerBase;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class Jooim_WithdrawalDirectsubmitModuleFrontController extends FrontControllerBase
{
    private const LIFECYCLE_REVISION = 'JOOIM_WITHDRAWAL_POST_REDIRECT_GET_V3';

    /**
     * Keep the final direct-withdrawal POST independent from storefront header
     * hooks. The accepted submission is processed in postProcess() and then
     * redirected before PrestaShop initializes displayHeader.
     */
    public function init()
    {
        parent::init();
        $this->displayHeader(false);
        $this->displayFooter(false);
        $this->displayHeaderJavaScript(false);

        if (!headers_sent()) {
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
    }

    public function postProcess()
    {
        $directUrl = $this->fallbackModuleUrl('direct');

        try {
            $directUrl = $this->context->link->getModuleLink(
                $this->module->name,
                'direct',
                [],
                true
            );

            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                $this->setRedirectAfter($directUrl);
                return;
            }

            if (!$this->services()->config()->bool('JOOIM_WITHDRAWAL_ENABLED', true)
                || !$this->services()->config()->bool('JOOIM_WITHDRAWAL_DIRECT_FUNCTION', true)
            ) {
                $this->setRedirectAfter($this->context->link->getModuleLink(
                    $this->module->name,
                    'identify',
                    [],
                    true
                ));
                return;
            }

            $idDraft = (int) \Tools::getValue('id_draft');
            $key = (string) \Tools::getValue('draft_key');
            $draft = $this->services()->drafts()->getForSubmission($idDraft, $key);
            if (!$draft || ($draft['source'] ?? '') !== 'guest_direct') {
                $this->setRedirectAfter($this->submissionFailureUrl('direct', 'session'));
                return;
            }

            $alreadySubmitted = !empty($draft['submitted_at']);
            if (!$alreadySubmitted
                && !$this->services()->csrf()->validate(
                    'direct_submit:' . $idDraft,
                    (string) \Tools::getValue('csrf_token'),
                    true
                )
            ) {
                $this->setRedirectAfter($this->submissionFailureUrl('direct', 'session'));
                return;
            }

            $result = $this->executeWithoutOutput(
                fn(): array => $this->services()->withdrawals()->submit($idDraft, $key)
            );
            if (empty($result['receipt_token'])) {
                throw new \RuntimeException('Direct withdrawal submission completed without a receipt token.');
            }

            $this->setRedirectAfter($this->context->link->getModuleLink(
                $this->module->name,
                'confirmation',
                ['r' => (string) $result['receipt_token']],
                true
            ));
        } catch (\Throwable $exception) {
            $reference = $this->reportFrontFailure(
                $exception,
                static::class . '::postProcess'
            );

            try {
                $failureUrl = $this->submissionFailureUrl(
                    'direct',
                    'technical',
                    $reference
                );
            } catch (\Throwable $linkException) {
                $failureUrl = $directUrl;
            }
            $this->setRedirectAfter($failureUrl);
        }
    }

    public function initContent()
    {
        \Tools::redirect($this->fallbackModuleUrl('direct'));
    }

    private function fallbackModuleUrl(string $controller): string
    {
        $controller = $controller === 'direct' ? 'direct' : 'identify';
        return rtrim((string) \Tools::getShopDomainSsl(true), '/')
            . '/index.php?fc=module&module=jooim_withdrawal&controller=' . $controller;
    }
}
