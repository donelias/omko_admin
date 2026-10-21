<div style="font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; color: #231f20;">
    @include('mail-templates.partials.header')
    <div style="border: 1px solid #e2e8f0; border-top: none; padding: 24px;">
        <p>Hola <strong>{{ $agent_name }}</strong>:</p>
        <p>El cliente <strong>{{ $client_name }}</strong> completó el formulario de depuración para la propiedad <strong>{{ $property_name }}</strong>.</p>
        <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2; width: 40%;">Puntaje preliminar</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;"><strong>{{ $score }}/100</strong> ({{ $nivel }})</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2; width: 40%;">Correo del cliente</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $client_email }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; border: 1px solid #e2e8f0; background: #eef8f2; width: 40%;">Fecha</td>
                <td style="padding: 8px; border: 1px solid #e2e8f0;">{{ $date }}</td>
            </tr>
        </table>
        @if ($recomendacion_ia)
            <p style="margin-top: 16px;">Recomendación IA: <em>{{ $recomendacion_ia }}</em></p>
        @endif
        <p style="margin-top: 16px;">Ingrese a su panel para revisar las respuestas y tomar la decisión.</p>
    </div>
    @include('mail-templates.partials.footer')
</div>
