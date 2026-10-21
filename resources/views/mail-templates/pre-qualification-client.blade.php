<div style="font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; color: #231f20;">
    @include('mail-templates.partials.header')
    <div style="border: 1px solid #e2e8f0; border-top: none; padding: 24px;">
        <p>Estimado/a <strong>{{ $client_name }}</strong>:</p>
        <p>Hemos recibido su solicitud de precalificación. A continuación el resumen:</p>
        <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2; width: 40%;">Entidad</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $entity_name }} ({{ $entity_type }})</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2;">Proyecto</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $project_name }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2;">Ingreso mensual</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $currency }} {{ $monthly_income }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2;">Fecha</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $date }}</td>
            </tr>
        </table>
        <p style="margin-top: 16px;">Nuestro equipo revisará su información y le contactará lo antes posible.</p>
        <p>Si tiene alguna pregunta, puede responder directamente este correo.</p>
    </div>
    @include('mail-templates.partials.footer')
</div>
