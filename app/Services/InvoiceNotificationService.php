<?php

namespace App\Services;

use App\Mail\GenericMailTemplate;
use App\Models\Invoice;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Correo de la factura de crédito fiscal con el PDF adjunto.
 *
 * Va a dos destinatarios: el cliente de la factura y una copia interna a
 * Omko. El PDF se genera UNA sola vez y se adjunta a ambos envíos.
 *
 * Todos los envíos quedan aislados en try/catch: el transporte de correo
 * está bloqueado a nivel de red en el servidor, así que un fallo de SMTP
 * jamás debe impedir que la factura se emita o se guarde.
 */
class InvoiceNotificationService
{
    /**
     * @return int  número de correos encolados
     */
    public static function send(Invoice $invoice): int
    {
        $sent = 0;

        try {
            $pdf = app(\App\Services\PDF\InvoicePdfService::class)->getPDFContents($invoice);
        } catch (Exception $e) {
            Log::error('InvoiceNotificationService: no se pudo generar el PDF.', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }

        $attachment = [[
            'contents' => $pdf,
            'filename' => 'FACTURA_'.$invoice->invoice_number.'.pdf',
            'mime' => 'application/pdf',
        ]];

        $ctx = self::context($invoice);

        $recipients = [];
        if ($invoice->client_email && filter_var($invoice->client_email, FILTER_VALIDATE_EMAIL)) {
            $recipients[$invoice->client_email] = 'client';
        }

        $omkoMail = HelperService::getSettingData('info_email')
            ?: (HelperService::getSettingData('company_email') ?: config('mail.from.address'));
        if ($omkoMail && filter_var($omkoMail, FILTER_VALIDATE_EMAIL)) {
            $recipients[$omkoMail] = 'omko';
        }

        // Copia (CC) adicional de la factura: correos extra que también reciben
        // la factura. Es distinto de la copia interna de Omko. Se envían como
        // CC real en el correo del cliente; se excluyen los que ya son
        // destinatario principal para no duplicar.
        $ccList = array_values(array_filter(
            $invoice->ccEmailList(),
            fn (string $email) => ! isset($recipients[$email])
        ));

        foreach ($recipients as $email => $role) {
            try {
                $body = view('mail-templates.invoice', $ctx + ['role' => $role, 'invoice' => $invoice])->render();

                $data = [
                    'title' => ($role === 'omko' ? '[Copia] ' : '')
                        .($invoice->status === 'anulada' ? 'Factura anulada' : 'Factura').' '.$invoice->invoice_number,
                    'email' => $email,
                    'email_template' => $body,
                ];

                // La CC solo acompaña al correo del cliente (el destinatario
                // natural de la factura). La copia interna de Omko va sola.
                $pending = Mail::to($email);
                if ($role === 'client' && $ccList !== []) {
                    $pending = $pending->cc($ccList);
                }

                // Se envía de forma SÍNCRONA a propósito: el PDF adjunto son
                // bytes binarios y un Mailable en cola se serializa a JSON, lo
                // que rompe con "Malformed UTF-8 characters". En envío directo
                // el adjunto viaja tal cual al transporte.
                $pending->send(new GenericMailTemplate(
                    $data,
                    // config() en lugar de env(): con config:cache env() es null
                    HelperService::getSettingData('company_email') ?: config('mail.from.address'),
                    HelperService::getSettingData('company_name') ?: config('app.name'),
                    $attachment
                ));

                $sent++;
            } catch (Exception $e) {
                Log::error('InvoiceNotificationService: correo no enviado.', [
                    'invoice_id' => $invoice->id,
                    'recipient' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Si no hubo destinatario principal (sin email de cliente) pero sí CC,
        // se envía la factura directamente a los CC para que no se pierda.
        if ($recipients === [] && $ccList !== []) {
            try {
                $body = view('mail-templates.invoice', $ctx + ['role' => 'client', 'invoice' => $invoice])->render();

                Mail::to($ccList)->send(new GenericMailTemplate(
                    [
                        'title' => 'Factura '.$invoice->invoice_number,
                        'email' => $ccList[0],
                        'email_template' => $body,
                    ],
                    HelperService::getSettingData('company_email') ?: config('mail.from.address'),
                    HelperService::getSettingData('company_name') ?: config('app.name'),
                    $attachment
                ));

                $sent += count($ccList);
            } catch (Exception $e) {
                Log::error('InvoiceNotificationService: correo a CC no enviado.', [
                    'invoice_id' => $invoice->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    private static function context(Invoice $invoice): array
    {
        $invoice->loadMissing(['customer', 'paymentTransaction.package']);

        return [
            'company_name' => self::utf8(HelperService::getSettingData('company_name') ?? config('app.name')),
            'company_rnc' => self::utf8(HelperService::getSettingData('company_rnc') ?? ''),
            'client_name' => self::utf8($invoice->client_name),
            'client_rnc' => self::utf8($invoice->client_rnc ?? '—'),
            'concept' => self::utf8($invoice->concept),
            'amount' => Invoice::money($invoice->total),
            'currency' => $invoice->currency,
            'issue_date' => $invoice->issue_date->format('d/m/Y'),
            'status' => $invoice->status,
        ];
    }

    /**
     * Normaliza a UTF-8 válido. Un carácter mal codificado en el concepto
     * hace que el Mailable falle al serializarse o al renderizar.
     */
    private static function utf8(?string $value): string
    {
        $value = (string) $value;

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
