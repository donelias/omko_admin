<div style="font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; color: #333;">
    <div style="background: #0f766e; padding: 20px; border-radius: 8px 8px 0 0;">
        <h2 style="margin: 0; color: #fff;">Factura {{ $invoice->invoice_number }}</h2>
        <p style="margin: 6px 0 0; color: #d9f0ee; font-size: 13px;">{{ $company_name }} · RNC {{ $company_rnc }}</p>
    </div>

    <div style="border: 1px solid #e2e8f0; border-top: none; padding: 24px; border-radius: 0 0 8px 8px;">
        @if ($role === 'omko')
            <p style="margin-top: 0; background: #f1f5f9; padding: 8px 10px; border-radius: 6px;">
                <strong>Copia interna</strong> — esta factura fue enviada al cliente.
            </p>
        @endif

        <p style="margin-top: 0;">
            Estimado/a <strong>{{ $client_name }}</strong>, adjuntamos la factura de crédito fiscal
            correspondiente a la operación indicada abajo.
        </p>

        <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #f1f5f9; width: 40%;">Factura</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;"><strong>{{ $invoice->invoice_number }}</strong></td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #f1f5f9;">Fecha de emisión</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $issue_date }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #f1f5f9;">Cliente / RNC</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $client_name }} · {{ $client_rnc }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #f1f5f9;">Concepto</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $concept }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #f1f5f9;">Estado</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ ucfirst($status) }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #f1f5f9;">Total a pagar</td>
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
</div>
