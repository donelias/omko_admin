<div style="font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; color: #231f20;">
    @include('mail-templates.partials.header')
    <div style="border: 1px solid #e2e8f0; border-top: none; padding: 24px;">
        <p>Estimado/a <strong>{{ $client_name }}</strong>:</p>
        <p>Hemos recibido su solicitud de depuración para la propiedad <strong>{{ $property_name }}</strong>.</p>
        <p>{{ $agent_name }} revisará su información y le contactará lo antes posible.</p>
        <p>Si tiene alguna pregunta, puede responder directamente este correo.</p>
        <br>
        <p style="color: #64748b; font-size: 12px;">Gracias por su interés. — Omko Bienes Raíces</p>
    </div>
    @include('mail-templates.partials.footer')
</div>
