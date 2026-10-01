<div style="font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; color: #333;">
    <div style="background: #0f766e; padding: 20px; border-radius: 8px 8px 0 0;">
        <h2 style="margin: 0; color: #fff;">Nueva solicitud de depuración</h2>
    </div>
    <div style="border: 1px solid #e2e8f0; border-top: none; padding: 24px; border-radius: 0 0 8px 8px;">
        <p>Se ha registrado una solicitud nueva en el flujo de depuración de clientes.</p>
        <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #f1f5f9; width: 40%;">Propiedad</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $property_name }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #f1f5f9; width: 40%;">Agente asignado</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $agent_name }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #f1f5f9; width: 40%;">Cliente</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $client_name }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #f1f5f9; width: 40%;">Correo del cliente</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $client_email }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #f1f5f9; width: 40%;">Teléfono</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $client_phone }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #f1f5f9; width: 40%;">Puntaje preliminar</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;"><strong>{{ $score }}/100</strong> ({{ $nivel }})</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #f1f5f9; width: 40%;">Fecha</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $date }}</td>
            </tr>
        </table>
        <p style="margin-top: 16px;">Se recomienda revisar la solicitud si el agente no responde en un plazo razonable.</p>
    </div>
</div>