<?php

namespace App\Services\Payment;

use Illuminate\Support\Str;

class MockPayment implements PaymentInterface
{
    private string $currencyCode;

    public function __construct(array $paymentData = [])
    {
        $this->currencyCode = $paymentData['mock_currency'] ?? 'USD';
    }

    /**
     * Simulate a payment intent without contacting a real gateway.
     *
     * The returned payment_url points to the local mock webhook so the
     * purchase can be completed end-to-end in development/staging.
     */
    public function createPaymentIntent($amount, $customMetaData)
    {
        $orderId = 'MOCK'.Str::upper(Str::random(12));

        return [
            'id' => $orderId,
            'status' => 'PENDING',
            'amount' => $amount,
            'currency' => $this->currencyCode,
            'payment_url' => route('webhook.mock', ['order_id' => $orderId]),
            'metadata' => $customMetaData,
        ];
    }

    public function createAndFormatPaymentIntent($amount, $customMetaData): array
    {
        $intent = $this->createPaymentIntent($amount, $customMetaData);

        return $this->formatPaymentIntent(
            $intent['id'],
            $amount,
            $this->currencyCode,
            $intent['status'],
            $customMetaData,
            $intent,
            $intent['payment_url']
        );
    }

    public function retrievePaymentIntent($paymentId): array
    {
        return $this->formatPaymentIntent(
            $paymentId,
            0,
            $this->currencyCode,
            'PENDING',
            [],
            ['id' => $paymentId],
            route('webhook.mock', ['order_id' => $paymentId])
        );
    }

    public function minimumAmountValidation($currency, $amount)
    {
        return true;
    }

    public function formatPaymentIntent($id, $amount, $currency, $status, $metadata, $paymentIntent, $paymentUrl = ''): array
    {
        return [
            'id' => $id,
            'payment_url' => $paymentUrl,
        ];
    }
}