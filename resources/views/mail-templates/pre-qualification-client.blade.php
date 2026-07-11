<h2>Confirmación de Precalificación</h2>
<p>Hola {{ $client_name }},</p>
<p>Hemos recibido tu solicitud de precalificación exitosamente. Nuestro equipo se pondrá en contacto contigo pronto para continuar con el proceso.</p>

<table style="border-collapse:collapse;width:100%;max-width:600px;">
    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Proyecto</td><td style="padding:8px;border:1px solid #ddd;">{{ $project_name }}</td></tr>
    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Entidad Financiera</td><td style="padding:8px;border:1px solid #ddd;">{{ $entity_name }} ({{ $entity_type }})</td></tr>
    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Moneda</td><td style="padding:8px;border:1px solid #ddd;">{{ $currency }}</td></tr>
    <tr><td style="padding:8px;border:1px solid #ddd;font-weight:bold;">Fecha</td><td style="padding:8px;border:1px solid #ddd;">{{ $date }}</td></tr>
</table>

<p>Si tienes alguna pregunta, no dudes en contactarnos.</p>
<p>Gracias por confiar en {{ $company_name ?? 'Omko' }}.</p>
