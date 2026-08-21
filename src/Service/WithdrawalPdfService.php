<?php
namespace JooInternetMedia\PrestaShop\Module\JooimWithdrawal\Service;

final class WithdrawalPdfService
{
    private \Context $context;
    private \Module $module;
    private StorageService $storage;

    public function __construct(\Context $context, \Module $module, StorageService $storage)
    {
        $this->context = $context;
        $this->module = $module;
        $this->storage = $storage;
    }

    public function generate(array $withdrawal, array $items, array $snapshot): array
    {
        $html = $this->html($withdrawal, $items, $snapshot);
        if (!class_exists('TCPDF')) {
            $tcpdfPath = _PS_TOOL_DIR_ . 'tcpdf/tcpdf.php';
            if (is_file($tcpdfPath)) {
                require_once $tcpdfPath;
            }
        }
        if (!class_exists('TCPDF')) {
            throw new \RuntimeException('TCPDF is unavailable in this PrestaShop installation.');
        }

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('JOOIM Withdrawal ' . (string) ($snapshot['module']['version'] ?? ''));
        $pdf->SetAuthor((string) ($snapshot['merchant']['name'] ?? ''));
        $pdf->SetTitle($this->tr('Withdrawal acknowledgement') . ' ' . (string) $withdrawal['reference']);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        $logoName = (string) \Configuration::get('PS_LOGO');
        $logoPath = $logoName !== '' ? rtrim(_PS_IMG_DIR_, '/\\') . DIRECTORY_SEPARATOR . $logoName : '';
        if ($logoPath !== '' && is_file($logoPath)) {
            try {
                $pdf->Image($logoPath, 15, 10, 38, 0, '', '', '', true, 300);
                $pdf->Ln(16);
            } catch (\Throwable $e) {
                // A broken logo must never invalidate the consumer's withdrawal record.
            }
        }
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->writeHTML($html, true, false, true, false, '');
        $content = $pdf->Output('', 'S');
        return $this->storage->writePdf((string) $withdrawal['reference'], (string) $content);
    }

    private function html(array $withdrawal, array $items, array $snapshot): string
    {
        $e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $merchant = $snapshot['merchant'] ?? [];
        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr><td>' . $e($item['product_name'] ?? '') . '</td><td>' . $e($item['product_reference'] ?? '') . '</td><td>' . (int) ($item['withdrawal_quantity'] ?? 0) . '</td></tr>';
        }
        if ($rows === '') {
            $manual = (string) ($snapshot['manual_items_description'] ?? '');
            $rows = '<tr><td colspan="3">' . $e($manual !== '' ? $manual : '—') . '</td></tr>';
        }
        $scopeLabel = ($withdrawal['scope'] ?? '') === 'full' ? $this->tr('Entire contract/order') : $this->tr('Selected goods or contract part');

        return '<h1>' . $e($this->tr('Acknowledgement of receipt of contract withdrawal')) . '</h1>'
            . '<p><strong>' . $e($this->tr('Withdrawal reference:')) . '</strong> ' . $e($withdrawal['reference']) . '<br>'
            . '<strong>' . $e($this->tr('Date and time received:')) . '</strong> ' . $e($snapshot['submitted_at'] ?? $withdrawal['submitted_at_utc']) . '<br>'
            . '<strong>' . $e($this->tr('Received at (UTC):')) . '</strong> ' . $e($snapshot['submitted_at_utc'] ?? $withdrawal['submitted_at_utc']) . '</p>'
            . '<h2>' . $e($this->tr('Trader')) . '</h2><p>' . $e($merchant['name'] ?? '') . '<br>' . $e($merchant['address'] ?? '') . '<br>'
            . $e($merchant['identifier'] ?? '') . '<br>' . $e($merchant['email'] ?? '') . '</p>'
            . '<h2>' . $e($this->tr('Consumer and contract')) . '</h2><p>' . $e(($withdrawal['customer_firstname'] ?? '') . ' ' . ($withdrawal['customer_lastname'] ?? '')) . '<br>'
            . $e($withdrawal['customer_email'] ?? '') . '<br><strong>' . $e($this->tr('Order / contract reference:')) . '</strong> ' . $e($withdrawal['order_reference'] ?? '') . '</p>'
            . '<h2>' . $e($this->tr('Withdrawal scope')) . '</h2><p>' . $e($scopeLabel) . '</p>'
            . '<table border="1" cellpadding="5"><thead><tr><th>' . $e($this->tr('Item')) . '</th><th>' . $e($this->tr('Reference')) . '</th><th>' . $e($this->tr('Quantity')) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<h2>' . $e($this->tr('Withdrawal statement')) . '</h2><p>' . nl2br($e($withdrawal['statement_text'] ?? '')) . '</p>'
            . '<h2>' . $e($this->tr('Return instructions')) . '</h2><p>' . nl2br($e($merchant['return_address'] ?? '')) . '</p>'
            . '<p><small>' . $e($this->tr('Immutable snapshot SHA-256:')) . ' ' . $e($withdrawal['payload_hash'] ?? '') . '</small></p>';
    }

    private function tr(string $source): string
    {
        return $this->module->jooimTranslate($source, [], 'Modules.Jooimwithdrawal.Pdf');
    }
}
