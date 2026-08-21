<?php
require_once dirname(__DIR__, 2) . '/autoload.php'; // JOOIM_WITHDRAWAL_FRONT_AUTOLOAD

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Compatibility\FrontControllerBase;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class Jooim_WithdrawalSubmitModuleFrontController extends FrontControllerBase
{
    private const LIFECYCLE_REVISION = 'JOOIM_WITHDRAWAL_POST_REDIRECT_GET_V3';

    /**
     * The final POST endpoint is deliberately headless.
     *
     * PrestaShop executes postProcess() before initHeader()/initContent(). The
     * previous implementation performed the legal submission in initContent(),
     * after displayHeader hooks had already started. A failure in an unrelated
     * analytics hook therefore prevented the database transaction entirely.
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

    /**
     * Processes the final order-based withdrawal before any storefront hook is
     * initialized, then applies Post/Redirect/Get to a normal confirmation page.
     */
    public function postProcess()
    {
        $identifyUrl = $this->fallbackModuleUrl('identify');

        try {
            $identifyUrl = $this->context->link->getModuleLink(
                $this->module->name,
                'identify',
                [],
                true
            );

            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                $this->setRedirectAfter($identifyUrl);
                return;
            }

            if (!$this->services()->config()->bool('JOOIM_WITHDRAWAL_ENABLED', true)) {
                $this->setRedirectAfter($identifyUrl);
                return;
            }

            $idDraft = (int) \Tools::getValue('id_draft');
            $key = (string) \Tools::getValue('draft_key');
            $draft = $this->services()->drafts()->getForSubmission($idDraft, $key);
            if (!$draft) {
                $this->setRedirectAfter($this->submissionFailureUrl('identify', 'session'));
                return;
            }

            $alreadySubmitted = !empty($draft['submitted_at']);
            if (!$alreadySubmitted
                && !$this->services()->csrf()->validate(
                    'withdrawal_submit:' . $idDraft,
                    (string) \Tools::getValue('csrf_token'),
                    true
                )
            ) {
                $this->setRedirectAfter($this->submissionFailureUrl('identify', 'session'));
                return;
            }

            if (!$alreadySubmitted
                && !empty($draft['id_order'])
                && !$this->services()->accessResolver()->forOrder((int) $draft['id_order'])
            ) {
                $this->setRedirectAfter($this->submissionFailureUrl('identify', 'access'));
                return;
            }

            $result = $this->executeWithoutOutput(
                fn(): array => $this->services()->withdrawals()->submit($idDraft, $key)
            );
            if (empty($result['receipt_token'])) {
                throw new \RuntimeException('Withdrawal submission completed without a receipt token.');
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
                    'identify',
                    'technical',
                    $reference
                );
            } catch (\Throwable $linkException) {
                $failureUrl = $identifyUrl;
            }
            $this->setRedirectAfter($failureUrl);
        }
    }

    /**
     * Controller::run() redirects immediately after postProcess(). This method
     * is only a hard fallback for non-standard dispatchers.
     */
    public function initContent()
    {
        \Tools::redirect($this->fallbackModuleUrl('identify'));
    }

    private function fallbackModuleUrl(string $controller): string
    {
        $controller = $controller === 'direct' ? 'direct' : 'identify';
        return rtrim((string) \Tools::getShopDomainSsl(true), '/')
            . '/index.php?fc=module&module=jooim_withdrawal&controller=' . $controller;
    }
}
