<?php
require_once dirname(__DIR__, 2) . '/autoload.php'; // JOOIM_WITHDRAWAL_FRONT_AUTOLOAD

use JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Compatibility\FrontControllerBase;

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

class Jooim_WithdrawalDocumentModuleFrontController extends FrontControllerBase
{
    public function initContent()
    {
        $id = (int) \Tools::getValue('id');
        $withdrawal = $this->services()->withdrawalRepository()->find($id, (int) $this->context->shop->id);
        if (!$withdrawal || empty($withdrawal['id_order']) || !$this->services()->accessResolver()->forOrder((int) $withdrawal['id_order'])) {
            http_response_code(403);
            exit('Forbidden');
        }
        $docs = $this->services()->withdrawalRepository()->documents($id);
        $doc = $docs[0] ?? null;
        if (!$doc) {
            http_response_code(404);
            exit('Not found');
        }
        try {
            $content = $this->services()->storage()->read((string) $doc['storage_path']);
        } catch (\Throwable $e) {
            // JOOIM_WITHDRAWAL_CATCH_DIAGNOSTICS
            JooimWithdrawalRuntimeDiagnostics::report($e, __METHOD__);
            http_response_code(404);
            exit('Not found');
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="withdrawal-' . rawurlencode((string) $withdrawal['reference']) . '.pdf"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, no-store, max-age=0');
        echo $content;
        exit;
    }
}
