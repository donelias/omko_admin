<?php

namespace App\Services\Payment;

use Throwable;
use RuntimeException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class CashfreePayment implements PaymentInterface
{
    private string $clientId;
    private string $clientSecret;
    private string $currencyCode;
    private bool $isSandbox;
    private string $baseUrl;

    public function __construct($paymentData)
    {
        $this->clientId = $paymentData['cashfree_app_id'] ?? '';
        $this->clientSecret = $paymentData['cashfree_secret_key'] ?? '';
        $this->currencyCode = $paymentData['cashfree_currency'] ?? 'INR';
        $this->isSandbox = ($paymentData['cashfree_sandbox_mode'] ?? 0) == 1;
        $this->baseUrl = $this->isSandbox
            ? 'https://sandbox.cashfree.com/pg'
            : 'https://api.cashfree.com/pg';
    }

    /**
     * Create Cashfree payment link
     */
    public function createPaymentIntent($amount, $customMetaData)
    {
        try {
            // Validate credentials
            if (empty($this->clientId) || empty($this->clientSecret)) {
                throw new RuntimeException('Cashfree credentials are missing. Please check cashfree_app_id and cashfree_secret_key.');
            }

            $amount = $this->minimumAmountValidation($this->currencyCode, $amount);
            $return_url = $customMetaData['platform_type'] == 'app' ? route('payment.success') : route('payment.success.web');
            $notify_url = url('/webhook/cashfree');

            $linkId = 'link_' . ($customMetaData['payment_transaction_id'] ?? uniqid());

            // Sanitize customer name to only allow Latin characters
            $customerName = $this->sanitizeCustomerName($customMetaData['user_name'] ?? '');

            // Prepare payment link data
            $linkData = [
                'link_id' => $linkId,
                'link_amount' => round($amount, 2),
                'link_currency' => $this->currencyCode,
                'link_purpose' => $customMetaData['description'] ?? 'Payment',
                'customer_details' => [
                    'customer_name' => $customerName,
                    'customer_email' => $customMetaData['email'] ?? '',
                    'customer_phone' => $customMetaData['phone'] ?? '',
                ],
                'link_notify' => [
                    'send_email' => false,
                    'send_sms' => false,
                ],
                'link_meta' => [
                    'return_url' => $return_url,
                    'notify_url' => $notify_url,
                ],
            ];

            // Add optional fields if present
            if (isset($customMetaData['link_notes'])) {
                $linkData['link_notes'] = $customMetaData['link_notes'];
            }

            Log::info('Cashfree Payment Link Request: ', [
                'url' => $this->baseUrl . '/links',
                'client_id' => $this->clientId,
                'is_sandbox' => $this->isSandbox,
                'link_data' => $linkData,
            ]);

            $response = Http::withHeaders([
                'x-client-id' => $this->clientId,
                'x-client-secret' => $this->clientSecret,
                'x-api-version' => '2023-08-01',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($this->baseUrl . '/links', $linkData);

            if (!$response->successful()) {
                $errorBody = $response->body();
                $statusCode = $response->status();
                Log::error('Cashfree createPaymentLink failed', [
                    'status_code' => $statusCode,
                    'response' => $errorBody,
                    'headers' => $response->headers(),
                ]);
                throw new RuntimeException("Failed to create Cashfree payment link (Status: {$statusCode}): {$errorBody}");
            }

            $responseData = $response->json();

            // Log the response to debug
            Log::info('Cashfree Payment Link Response: ' . json_encode($responseData));

            // Extract the payment link URL
            $paymentUrl = $responseData['link_url'] ?? null;

            if (!$paymentUrl) {
                Log::error('Cashfree createPaymentLink missing link_url: ' . $response->body());
                throw new RuntimeException('Failed to create Cashfree payment link: Missing link_url');
            }

            return [
                'link_id' => $linkId,
                'cf_link_id' => $responseData['cf_link_id'] ?? null,
                'payment_url' => $paymentUrl,
                'link_amount' => round($amount, 2),
                'link_currency' => $this->currencyCode,
                'link_status' => $responseData['link_status'] ?? 'ACTIVE',
                'data' => $responseData,
            ];

        } catch (Throwable $e) {
            Log::error('Cashfree createPaymentIntent failed: ' . $e->getMessage());
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * Required by PaymentInterface
     * If not needed, return null
     */
    public function createAndFormatPaymentIntent($amount, $customMetaData): array
    {
        $paymentIntent = $this->createPaymentIntent($amount, $customMetaData);
        if (!$paymentIntent) {
            return [];
        }
        return $this->format($paymentIntent, $amount, $this->currencyCode, $customMetaData);
    }

    /**
     * Required by PaymentInterface
     * If not needed, return null
     */
    public function retrievePaymentIntent($paymentId): array
    {
        // Optional: implement if you want to check payment link status
        try {
            $response = Http::withHeaders([
                'x-client-id' => $this->clientId,
                'x-client-secret' => $this->clientSecret,
                'x-api-version' => '2023-08-01',
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/links/' . $paymentId);

            if ($response->successful()) {
                return $response->json();
            }
        } catch (Throwable $e) {
            Log::error('Cashfree retrievePaymentIntent failed: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Required by PaymentInterface
     * If not needed, return null
     */
    public function formatPaymentIntent($id, $amount, $currency, $status, $metadata, $paymentIntent, $paymentUrl = ''): array
    {
        return [
            'id' => $id,
            'payment_url' => $paymentUrl,
        ];
    }

    /**
     * Helper to format response
     */
    private function format($paymentIntent, $amount, $currencyCode, $metadata): array
    {
        $id = $paymentIntent['link_id'] ?? $paymentIntent['cf_link_id'] ?? '';
        $status = $paymentIntent['link_status'] ?? 'ACTIVE';
        $paymentUrl = $paymentIntent['payment_url'] ?? $paymentIntent['link_url'] ?? '';

        return $this->formatPaymentIntent($id, $amount, $currencyCode, $status, $metadata, $paymentIntent, $paymentUrl);
    }

    /**
     * Minimum amount validation
     */
    public function minimumAmountValidation($currency, $amount)
    {
        $minimumAmount = match (strtoupper($currency)) {
            'INR' => 1.00,
            'USD', 'EUR', 'GBP', 'CAD', 'AUD', 'CHF', 'NZD', 'SGD', 'HKD' => 0.01,
            'JPY' => 1,
            'KRW' => 10,
            'PKR', 'BDT' => 1,
            default => 0.01,
        };

        return max($amount, $minimumAmount);
    }

    /**
     * Sanitize customer name to only allow Latin characters, numbers, spaces, dots, and hyphens
     * Cashfree only accepts person names in Latin script
     */
    private function sanitizeCustomerName($name)
    {
        if (empty($name)) {
            return 'Customer';
        }

        // Remove any non-Latin characters (keep only a-z, A-Z, spaces, dots, hyphens, apostrophes)
        $sanitized = preg_replace('/[^a-zA-Z\s.\'-]/u', '', $name);

        // Remove extra spaces
        $sanitized = preg_replace('/\s+/', ' ', trim($sanitized));

        // If the name becomes empty after sanitization (was all non-Latin), use a default
        if (empty($sanitized)) {
            return 'Customer';
        }

        // Limit length to 100 characters (Cashfree limit)
        $sanitized = substr($sanitized, 0, 100);

        return $sanitized;
    }
}
