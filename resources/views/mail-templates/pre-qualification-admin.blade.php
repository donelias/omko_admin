<p>Se ha registrado una nueva solicitud de precalificación en la plataforma.</p>
<table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
    <tr>
        <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2; width: 40%;">Cliente</td>
        <td style="padding: 8px; border: 1px solid #e2e8f0;"><strong>{{ $client_name }}</strong></td>
    </tr>
    <tr>
        <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2;">Correo del cliente</td>
        <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $client_email }}</td>
    </tr>
    <tr>
        <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2;">Teléfono</td>
        <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $client_phone }}</td>
    </tr>
    <tr>
        <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2;">Entidad</td>
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
        <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2;">Estado</td>
        <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ ucfirst($status) }}</td>
    </tr>
    <tr>
        <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2;">Fecha</td>
        <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $date }}</td>
    </tr>
</table>
