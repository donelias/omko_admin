<?php

declare(strict_types=1);

namespace App\Services\PDF;

use App\Models\Invoice;
use App\Services\HelperService;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Generación del PDF de la factura de crédito fiscal.
 *
 * dompdf corre con enable_remote=false, así que la plantilla tiene que ser
 * autosuficiente: logo embebido en base64, estilos inline y sin fuentes ni
 * imágenes remotas. Cargar Google Fonts aquí fallaría en silencio.
 */
class InvoicePdfService
{
    public function generatePDF(Invoice $invoice)
    {
        return Pdf::loadView('invoices.invoice', $this->payload($invoice));
    }

    public function downloadPDF(Invoice $invoice)
    {
        return $this->generatePDF($invoice)->download($this->getFileName($invoice));
    }

    public function streamPDF(Invoice $invoice)
    {
        return $this->generatePDF($invoice)->stream($this->getFileName($invoice));
    }

    /**
     * PDF en binario listo para adjuntar al correo.
     */
    public function getPDFContents(Invoice $invoice): string
    {
        return $this->generatePDF($invoice)->output();
    }

    public function getFileName(Invoice $invoice): string
    {
        return 'FACTURA_'.$invoice->invoice_number.'.pdf';
    }

    private function payload(Invoice $invoice): array
    {
        $settings = $this->getSettings();

        // El logo de settings es un nombre de archivo; como dompdf no resuelve
        // archivos remotos ni legados fuera del chroot, se embebe en base64.
        // Se prioriza el logo dedicado de facturación (assets/images/invoice),
        // con fallback al logo genérico configurado en settings.
        $settings['logo'] = '';
        $invoiceLogoPath = public_path('assets/images/invoice/logo_omko.png');
        if (file_exists($invoiceLogoPath)) {
            $settings['logo'] = 'data:image/png;base64,'.base64_encode((string) file_get_contents($invoiceLogoPath));
        } else {
            $companyLogo = $settings['company_logo'];
            if ($companyLogo !== '') {
                $logoPath = public_path('assets/images/logo/'.$companyLogo);
                if (file_exists($logoPath)) {
                    $settings['logo'] = 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
                }
            }
        }

        // Firma y sello: imágenes dedicadas del comprobante, embebidas en
        // base64 porque dompdf no carga rutas locales ni remotas.
        $settings['signature_image'] = $this->embedImage('assets/images/invoice/fima-elisvernith-removebg-preview.png');
        $settings['seal_image'] = $this->embedImage('assets/images/invoice/sello-sin-fondo.png');
        $settings['qr_image'] = $this->embedImage('assets/images/invoice/qr_omko.png');

        return [
            'invoice' => $invoice,
            'settings' => $settings,
            // Carbon::format('F') sale en inglés y PHP no aporta localización;
            // el vencimiento del comprobante es en español.
            'months_es' => [
                '01' => 'ENERO', '02' => 'FEBRERO', '03' => 'MARZO',
                '04' => 'ABRIL', '05' => 'MAYO', '06' => 'JUNIO',
                '07' => 'JULIO', '08' => 'AGOSTO', '09' => 'SEPTIEMBRE',
                '10' => 'OCTUBRE', '11' => 'NOVIEMBRE', '12' => 'DICIEMBRE',
            ],
            'signer_name' => $settings['invoice_signer_name'] ?: 'Elisvernith Herrera',
            'signer_role' => $settings['invoice_signer_role'] ?: 'CEO Omko Bienes Raíces',
        ];
    }

    private function getSettings(): array
    {
        $types = [
            'company_name', 'company_address', 'company_email', 'company_logo',
            'company_tel1', 'company_tel2', 'currency_symbol', 'currency_code',
            'company_rnc', 'bank_account', 'bank_name', 'company_website',
            'invoice_signer_name', 'invoice_signer_role', 'invoice_payment_email',
        ];

        $settings = HelperService::getMultipleSettingData($types) ?? [];

        // getMultipleSettingData omite las claves que no existan todavía en la
        // tabla; el blade espera todos los huecos, así que se rellenan.
        return $settings + array_fill_keys($types, '');
    }

    /**
     * Lee una imagen pública y la devuelve como data-URI PNG base64.
     * Devuelve cadena vacía si no existe.
     */
    private function embedImage(string $relativePath): string
    {
        $path = public_path($relativePath);

        if (! file_exists($path)) {
            return '';
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }
}
