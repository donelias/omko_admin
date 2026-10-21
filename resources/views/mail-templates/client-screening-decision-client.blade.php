<div style="font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; color: #231f20;">
    @include('mail-templates.partials.header')
    <div style="border: 1px solid #e2e8f0; border-top: none; padding: 24px;">
        <p>Estimado/a <strong>{{ $client_name }}</strong>:</p>
        <p>Le informamos el resultado de su solicitud de depuración para la propiedad <strong>{{ $property_name }}</strong>:</p>
        @if ($decision === 'aprobado')
            <p style="padding: 12px; background: #eef8f2; border: 1px solid #41BA77; border-radius: 6px; color: #1a1a1a;">
                <strong>Aprobado</strong> — ¡Felicidades! {{ $agent_name }} se comunicará con usted para continuar con el proceso.
            </p>
        @else
            <p style="padding: 12px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; color: #991b1b;">
                <strong>Rechazado</strong> — Lamentablemente su solicitud no fue aprobada en esta ocasión.
            </p>
        @endif
        @if ($agent_notes)
            <p><strong>Comentario del agente:</strong> {{ $agent_notes }}</p>
        @endif
        <p>Gracias por su interés en Omko Bienes Raíces.</p>
    </div>
    @include('mail-templates.partials.footer')
</div>
