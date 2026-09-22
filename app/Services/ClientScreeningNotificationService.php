<?php

namespace App\Services;

use App\Mail\GenericMailTemplate;
use App\Models\ClientScreening;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notificaciones por correo del flujo de depuración de clientes.
 *
 *  - sendToClient():    confirmación de recepción al cliente.
 *  - sendToAgent():     aviso de nuevo screening al agente dueño.
 *  - sendDecisionToClient(): resultado final (aprobado/rechazado) al cliente.
 *
 * Cada envío está aislado en try/catch para nunca romper el flujo.
 */
class ClientScreeningNotificationService
{
    private static function context(ClientScreening $screening): array
    {
        $screening->loadMissing(['property', 'agent']);

        $adminMail = env('MAIL_FROM_ADDRESS', 'info@omko.do');
        $companyName = HelperService::getSettingData('company_name') ?? config('app.name');
        $agent = $screening->agent;

        return [
            'screening' => $screening,
            'adminMail' => $adminMail,
            'companyName' => $companyName,
            'agentName' => $agent
                ? ($agent->name ?? trim(($agent->first_name ?? '').' '.($agent->last_name ?? '')) ?: 'el agente')
                : 'el agente',
            'propertyName' => $screening->property->title ?? $screening->property->name ?? ('Propiedad #'.$screening->property_id),
        ];
    }

    public static function sendToClient(ClientScreening $screening): void
    {
        if (! $screening->customer_email) {
            return;
        }

        $ctx = self::context($screening);

        try {
            $data = [
                'title' => 'Hemos recibido su solicitud - Omko',
                'email' => $screening->customer_email,
                'email_template' => view('mail-templates.client-screening-client', [
                    'client_name' => $screening->customer_name,
                    'property_name' => $ctx['propertyName'],
                    'agent_name' => $ctx['agentName'],
                ])->render(),
            ];
            Mail::to($screening->customer_email)->queue(new GenericMailTemplate($data, $ctx['adminMail'], $ctx['companyName']));
        } catch (Exception $e) {
            Log::error('ClientScreeningNotificationService: correo al cliente falló: '.$e->getMessage());
        }
    }

    public static function sendToAgent(ClientScreening $screening): void
    {
        $agent = $screening->agent ?? $screening->agent()->withTrashed()->first();
        $email = $agent?->email;

        if (! $email) {
            return;
        }

        $ctx = self::context($screening);

        try {
            $data = [
                'title' => 'Nuevo cliente para evaluar - '.$screening->customer_name,
                'email' => $email,
                'email_template' => view('mail-templates.client-screening-agent', [
                    'agent_name' => $ctx['agentName'],
                    'client_name' => $screening->customer_name,
                    'client_email' => $screening->customer_email ?? 'N/A',
                    'property_name' => $ctx['propertyName'],
                    'score' => $screening->score,
                    'nivel' => $screening->nivel,
                    'date' => $screening->created_at->format('d/m/Y H:i'),
                    'recomendacion_ia' => $screening->recomendacion_ia,
                ])->render(),
            ];
            Mail::to($email)->queue(new GenericMailTemplate($data, $ctx['adminMail'], $ctx['companyName']));
        } catch (Exception $e) {
            Log::error('ClientScreeningNotificationService: correo al agente falló: '.$e->getMessage());
        }
    }

    public static function sendDecisionToClient(ClientScreening $screening): void
    {
        if (! $screening->customer_email) {
            return;
        }

        $ctx = self::context($screening);

        try {
            $data = [
                'title' => $screening->decision_agente === 'aprobado'
                    ? 'Su solicitud fue aprobada - Omko'
                    : 'Actualización de su solicitud - Omko',
                'email' => $screening->customer_email,
                'email_template' => view('mail-templates.client-screening-decision-client', [
                    'client_name' => $screening->customer_name,
                    'property_name' => $ctx['propertyName'],
                    'agent_name' => $ctx['agentName'],
                    'decision' => $screening->decision_agente,
                    'agent_notes' => $screening->notas_agente,
                ])->render(),
            ];
            Mail::to($screening->customer_email)->queue(new GenericMailTemplate($data, $ctx['adminMail'], $ctx['companyName']));
        } catch (Exception $e) {
            Log::error('ClientScreeningNotificationService: correo de decisión al cliente falló: '.$e->getMessage());
        }
    }
}