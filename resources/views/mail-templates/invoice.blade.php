<div style="font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; color: #231f20;">
    @include('mail-templates.partials.header')
    <div style="background: #231f20; padding: 12px 24px; text-align: center;">
        <p style="margin: 0; color: #ffffff; font-size: 14px; font-weight: bold;">Factura {{ $invoice->invoice_number }}</p>
        <p style="margin: 2px 0 0; color: #bfe9d1; font-size: 12px;">{{ $company_name }} · RNC {{ $company_rnc }}</p>
    </div>

    <div style="border: 1px solid #e2e8f0; border-top: none; padding: 24px;">
        @if ($role === 'omko')
            <p style="margin-top: 0; background: #eef8f2; padding: 8px 10px; border-radius: 6px; border-left: 3px solid #41BA77;">
                <strong>Copia interna</strong> — esta factura fue enviada al cliente.
            </p>
        @endif

        <p style="margin-top: 0;">
            Estimado/a <strong>{{ $client_name }}</strong>, adjuntamos la factura de crédito fiscal
            correspondiente a la operación indicada abajo.
        </p>

        <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2; width: 40%;">Factura</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;"><strong>{{ $invoice->invoice_number }}</strong></td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2;">Fecha de emisión</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $issue_date }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2;">Cliente / RNC</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $client_name }} · {{ $client_rnc }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2;">Concepto</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $concept }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2;">Estado</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ ucfirst($status) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2;">Total a pagar</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0; font-size: 16px;">
                    <strong>{{ $currency }} ${{ $amount }}</strong>
                </td>
            </tr>
        </table>

        <p style="margin-top: 16px;">
            El detalle completo de ITBIS y retenciones se encuentra en el archivo
            <strong>FACTURA {{ $invoice->invoice_number }}.pdf</strong> adjunto a este correo.
        </p>

        <p style="margin-top: 16px; color: #64748b; font-size: 13px;">
            Ante cualquier duda responda a este correo o escriba a {{ $company_name }}.
        </p>
    </div>
    @include('mail-templates.partials.footer')
</div>
