<h2>Nueva Precalificación de Cliente</h2>
<p>Hola {{ $agent_name }},</p>
<p>Tu cliente <strong>{{ $client_name }}</strong> ha completado una solicitud de precalificación.</p>

<table style="border-collapse:collapse;width:100%;max-width:600px;">
    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Cliente</td><td style="padding:8px;border:1px solid #ddd;">{{ $client_name }}</td></tr>
    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Email</td><td style="padding:8px;border:1px solid #ddd;">{{ $client_email }}</td></tr>
    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Teléfono</td><td style="padding:8px;border:1px solid #ddd;">{{ $client_phone }}</td></tr>
    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Proyecto</td><td style="padding:8px;border:1px solid #ddd;">{{ $project_name }}</td></tr>
    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Entidad</td><td style="padding:8px;border:1px solid #ddd;">{{ $entity_name }} ({{ $entity_type }})</td></tr>
    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Moneda</td><td style="padding:8px;border:1px solid #ddd;">{{ $currency }}</td></tr>
    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Fecha</td><td style="padding:8px;border:1px solid #ddd;">{{ $date }}</td></tr>
</table>

<p>Puedes dar seguimiento a esta precalificación desde tu panel de agente.</p>
