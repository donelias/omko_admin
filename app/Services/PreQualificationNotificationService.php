<?php

namespace App\Services;

use App\Mail\GenericMailTemplate;
use App\Models\BankFinancialAdvisor;
use App\Models\PreQualification;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PreQualificationNotificationService
{
    public static function sendNotifications(PreQualification $preQualification): void
    {
        $preQualification->loadMissing([
            'customer',
            'financialAdvisor',
            'financialEntity',
            'project',
            'property',
            'submittedBy',
        ]);

        $customer = $preQualification->customer;
        $entity = $preQualification->financialEntity;
        $entityName = $entity?->name ?? 'N/A';
        $projectName = $preQualification->project?->title ?? 'N/A';
        $advisor = $preQualification->financialAdvisor;
        $adminMail = env('MAIL_FROM_ADDRESS');
        $companyName = HelperService::getSettingData('company_name') ?? config('app.name');

        $clientName = $customer ? ($customer->name ?? ($customer->first_name . ' ' . $customer->last_name)) : 'Cliente';

        $entityTypeLabel = $preQualification->financial_entity_type === 'App\Models\Bank' ? 'Banco' : 'Cooperativa';

        $baseVariables = [
            'client_name' => $clientName,
            'client_email' => $customer?->email ?? 'N/A',
            'client_phone' => $customer?->phone ?? 'N/A',
            'entity_name' => $entityName,
            'entity_type' => $entityTypeLabel,
            'project_name' => $projectName,
            'currency' => $preQualification->currency,
            'monthly_income' => number_format((float) $preQualification->monthly_income, 2),
            'status' => $preQualification->status,
            'date' => $preQualification->created_at->format('d/m/Y H:i'),
        ];

        try {
            $advisorEmail = $advisor?->email;
            if ($advisorEmail) {
                $data = [
                    'title' => 'Nueva Precalificación - ' . $clientName,
                    'email' => $advisorEmail,
                    'email_template' => view('mail-templates.pre-qualification-advisor', $baseVariables)->render(),
                ];
                Mail::to($advisorEmail)->queue(new GenericMailTemplate($data, $adminMail, $companyName));
            } elseif ($entity?->email) {
                $data = [
                    'title' => 'Nueva Precalificación - ' . $clientName,
                    'email' => $entity->email,
                    'email_template' => view('mail-templates.pre-qualification-advisor', $baseVariables)->render(),
                ];
                Mail::to($entity->email)->queue(new GenericMailTemplate($data, $adminMail, $companyName));
            }
        } catch (Exception $e) {
            Log::error('Failed to send advisor notification: ' . $e->getMessage());
        }

        try {
            $agent = null;
            if ($preQualification->project?->added_by) {
                $agent = \App\Models\Customer::find($preQualification->project->added_by);
            }
            if ($preQualification->submittedBy) {
                $agent = $preQualification->submittedBy;
            }
            if ($agent && $agent->email) {
                $variables = array_merge($baseVariables, [
                    'agent_name' => $agent->name ?? ($agent->first_name . ' ' . $agent->last_name),
                ]);
                $data = [
                    'title' => 'Nueva Precalificación de Cliente - ' . $clientName,
                    'email' => $agent->email,
                    'email_template' => view('mail-templates.pre-qualification-agent', $variables)->render(),
                ];
                Mail::to($agent->email)->queue(new GenericMailTemplate($data, $adminMail, $companyName));
            }
        } catch (Exception $e) {
            Log::error('Failed to send agent notification: ' . $e->getMessage());
        }

        try {
            if ($customer && $customer->email) {
                $data = [
                    'title' => 'Confirmación de Precalificación - Omko',
                    'email' => $customer->email,
                    'email_template' => view('mail-templates.pre-qualification-client', $baseVariables)->render(),
                ];
                Mail::to($customer->email)->queue(new GenericMailTemplate($data, $adminMail, $companyName));
            }
        } catch (Exception $e) {
            Log::error('Failed to send client confirmation: ' . $e->getMessage());
        }

        try {
            $infoEmail = env('MAIL_FROM_ADDRESS', 'info@omko.do');
            $data = [
                'title' => '[Admin] Nueva Precalificación - ' . $clientName,
                'email' => $infoEmail,
                'email_template' => view('mail-templates.pre-qualification-admin', $baseVariables)->render(),
            ];
            Mail::to($infoEmail)->queue(new GenericMailTemplate($data, $adminMail, $companyName));
        } catch (Exception $e) {
            Log::error('Failed to send admin notification: ' . $e->getMessage());
        }
    }
}
