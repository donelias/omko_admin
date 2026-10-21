<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Plantillas de correo para el flujo de pagos/suscripciones.
     *
     *  - payment_success  -> pago confirmado (pasarela o aprobado por admin).
     *  - payment_failed   -> pago fallido o rechazado.
     *  - payment_review   -> el cliente inició una transferencia bancaria y queda
     *                        pendiente de revisión por el equipo.
     *
     * Si la fila ya existe no se sobrescribe (puede haber sido editada).
     */
    public function up(): void
    {
        $templates = [
            'payment_success_mail_template' => <<<'HTML'
<p>Hola <strong>{user_name}</strong>,</p>
<p>¡Tu pago ha sido confirmado exitosamente!</p>
<p><strong>Plan/Producto:</strong> {package_name}<br>
<strong>Monto:</strong> {currency} {amount}<br>
<strong>Referencia:</strong> {transaction_id}</p>
<p>Tu suscripción ya está activa. Ingresa a <strong>{app_name}</strong> para comenzar a disfrutar de tus beneficios.</p>
<p>Gracias por tu compra.</p>
<p>Saludos cordiales,<br>El equipo de <strong>{app_name}</strong></p>
HTML,
            'payment_failed_mail_template' => <<<'HTML'
<p>Hola <strong>{user_name}</strong>,</p>
<p>Te informamos que no pudimos procesar tu pago.</p>
<p><strong>Plan/Producto:</strong> {package_name}<br>
<strong>Monto:</strong> {currency} {amount}</p>
<p><strong>Motivo:</strong> {reason}</p>
<p>Puedes intentar nuevamente desde <strong>{app_name}</strong>. Si el problema persiste, contacta a nuestro equipo de soporte.</p>
<p>Saludos cordiales,<br>El equipo de <strong>{app_name}</strong></p>
HTML,
            'payment_review_mail_template' => <<<'HTML'
<p>Hola <strong>{user_name}</strong>,</p>
<p>Hemos recibido tu solicitud de pago por transferencia bancaria. Tu pago está en revisión y te notificaremos una vez sea confirmado.</p>
<p><strong>Plan/Producto:</strong> {package_name}<br>
<strong>Monto:</strong> {currency} {amount}</p>
<p>Saludos cordiales,<br>El equipo de <strong>{app_name}</strong></p>
HTML,
        ];

        foreach ($templates as $type => $data) {
            if (! DB::table('settings')->where('type', $type)->exists()) {
                DB::table('settings')->insert([
                    'type' => $type,
                    'data' => $data,
                ]);
            }
        }
    }

    public function down(): void
    {
        // No se eliminan: pueden haber sido editadas desde el panel.
    }
};
