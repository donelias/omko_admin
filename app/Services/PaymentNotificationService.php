<?php

namespace App\Services;

use App\Models\PayAsYouGo;
use App\Models\PaymentTransaction;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Notificaciones por correo del flujo de pagos/suscripciones.
 *
 * Todos los envíos son "best effort": un fallo de SMTP nunca debe romper el
 * flujo de pago (webhooks, aprobaciones), por eso cada método va en try/catch.
 */
class PaymentNotificationService
{
    public static function sendSuccess(PaymentTransaction $payment): void
    {
        self::send($payment, 'payment_success', [
            'transaction_id' => $payment->transaction_id ?? $payment->order_id ?? '—',
        ]);
    }

    public static function sendFailed(PaymentTransaction $payment, string $reason = ''): void
    {
        self::send($payment, 'payment_failed', [
            'reason' => $reason,
        ]);
    }

    public static function sendReview(PaymentTransaction $payment): void
    {
        self::send($payment, 'payment_review', []);
    }

    private static function send(PaymentTransaction $payment, string $type, array $extra): void
    {
        try {
            $payment->loadMissing(['customer', 'package']);
            $customer = $payment->customer;
            if (empty($customer) || empty($customer->email)) {
                return;
            }

            $appName = config('app.name') ?? 'omko';
            $currency = HelperService::getSettingData('currency_symbol') ?: '$';

            $variables = array_merge([
                'app_name' => $appName,
                'user_name' => ! empty($customer->name) ? $customer->name : "$appName User",
                'email' => $customer->email,
                'package_name' => self::productName($payment),
                'currency' => $currency,
                'amount' => number_format((float) $payment->amount, 2, '.', ','),
            ], $extra);

            $emailTypeData = HelperService::getEmailTemplatesTypes($type);
            $templateData = HelperService::getSettingData($emailTypeData['type']);
            $template = HelperService::replaceEmailVariables((string) $templateData, $variables);

            HelperService::sendMail([
                'email_template' => $template,
                'email' => $customer->email,
                'title' => $emailTypeData['title'],
            ]);
        } catch (Exception $e) {
            Log::warning('PaymentNotificationService::'.$type.' failed: '.$e->getMessage());
        }
    }

    private static function productName(PaymentTransaction $payment): string
    {
        if (! empty($payment->package->name)) {
            return $payment->package->name;
        }

        if (! empty($payment->pay_as_you_go_id)) {
            $payAsYouGo = PayAsYouGo::find($payment->pay_as_you_go_id);
            if (! empty($payAsYouGo->name)) {
                return $payAsYouGo->name;
            }
        }

        return config('app.name') ?? 'Omko';
    }
}
