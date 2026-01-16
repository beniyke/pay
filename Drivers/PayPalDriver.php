<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Pay Pal Driver
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Drivers;

use DateTimeImmutable;
use Exception;
use Helpers\Http\Client\Curl;
use Money\Money;
use Pay\Contracts\PaymentGatewayInterface;
use Pay\DataObjects\PaymentData;
use Pay\DataObjects\PaymentResponse;
use Pay\DataObjects\VerificationResponse;
use Pay\Enums\Status;
use Pay\Exceptions\PaymentException;

class PayPalDriver implements PaymentGatewayInterface
{
    protected string $clientId;

    protected string $clientSecret;

    protected string $baseUrl;

    protected Curl $curl;

    // PayPal typically requires obtaining an access token first.
    // For simplicity in this package, we'll implement the token fetch in a private method.

    public function __construct(string $clientId, string $clientSecret, string $mode = 'sandbox', ?Curl $curl = null)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->baseUrl = $mode === 'sandbox' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $this->curl = $curl ?? new Curl();
    }

    public function driver(): string
    {
        return 'paypal';
    }

    private function getAccessToken(): string
    {
        $response = $this->curl->post($this->baseUrl . '/v1/oauth2/token', 'grant_type=client_credentials')
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("PayPal Token Error: " . $response->body());
        }

        return $response->json()['access_token'];
    }

    public function initialize(PaymentData $data): PaymentResponse
    {
        // PayPal Orders V2
        // Data should map to PayPal's "application_context" and "purchase_units"

        $token = $this->getAccessToken();

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => $data->amount->getCurrency()->getCode(),
                        'value' => (string) $data->amount->getMajorAmount()
                    ]
                ]
            ],
            'application_context' => [
                'return_url' => $data->callbackUrl,
                'cancel_url' => $data->callbackUrl . '?status=cancelled' // Simplified
            ]
        ];

        $response = $this->curl->post($this->baseUrl . '/v2/checkout/orders', $payload)
            ->withToken($token)
            ->withHeader('PayPal-Request-Id', $data->reference ?? uniqid())
            ->asJson()
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("PayPal Order Error: " . $response->body());
        }

        $resData = $response->json();

        // Find approval link
        $approvalLink = null;
        foreach ($resData['links'] as $link) {
            if ($link['rel'] === 'approve') {
                $approvalLink = $link['href'];
                break;
            }
        }

        return new PaymentResponse(
            reference: $data->reference,
            status: Status::PENDING,
            authorizationUrl: $approvalLink,
            providerReference: $resData['id'],
            metadata: $resData
        );
    }

    public function verify(string $reference): VerificationResponse
    {
        // Reference is Order ID
        $token = $this->getAccessToken();

        $response = $this->curl->post($this->baseUrl . '/v2/checkout/orders/' . $reference . '/capture', [])
            ->withToken($token)
            ->asJson()
            ->send();

        // If already captured, or success.
        // But simplified flow usually implies capturing on return.

        if (! $response->ok()) {
            throw new PaymentException("PayPal Capture Error: " . $response->body());
        }

        $resData = $response->json();

        return new VerificationResponse(
            reference: $reference,
            status: $this->normalizeStatus($resData['status']),
            amount: Money::amount(
                $resData['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0,
                $resData['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] ?? 'USD'
            ),
            paidAt: isset($resData['purchase_units'][0]['payments']['captures'][0]['create_time'])
                ? new DateTimeImmutable($resData['purchase_units'][0]['payments']['captures'][0]['create_time'])
                : null,
            metadata: $resData
        );
    }

    protected function normalizeStatus(string $status): Status
    {
        return match ($status) {
            'COMPLETED' => Status::SUCCESS,
            'FAILED' => Status::FAILED,
            default => Status::PENDING,
        };
    }

    public function validateWebhook(string $payload, string $signature): bool
    {
        // To verify with PayPal, we need headers: transmission_id, transmission_time, cert_url, auth_algo, and transmission_sig.
        // The `$signature` argument passed here is expected to be a JSON encoded string containing these headers
        // if the upstream `WebhookService` extracts them.
        // If not, we cannot verify.

        $headers = json_decode($signature, true);
        if (! is_array($headers) || ! isset($headers['transmission_sig'])) {
            // Fallback or fail. For strict implementation, we should fail, but for current integration
            // where simple signature is passed, we might just return true if we can't do it.
            // BUT user said "implement".
            // Let's assume we can't verify if headers are missing.
            return false;
        }

        $verifyPayload = [
            'auth_algo' => $headers['auth_algo'] ?? '',
            'cert_url' => $headers['cert_url'] ?? '',
            'transmission_id' => $headers['transmission_id'] ?? '',
            'transmission_sig' => $headers['transmission_sig'] ?? '',
            'transmission_time' => $headers['transmission_time'] ?? '',
            'webhook_id' => $this->config['webhook_id'] ?? '', // Needs config default
            'webhook_event' => json_decode($payload, true)
        ];

        try {
            $response = $this->curl->post($this->baseUrl . '/v1/notifications/verify-webhook-signature', $verifyPayload)
                ->withToken($this->getAccessToken())
                ->asJson()
                ->send();

            return $response->ok() && ($response->json()['verification_status'] ?? '') === 'SUCCESS';
        } catch (Exception $e) {
            return false;
        }
    }

    public function processWebhook(array $payload): VerificationResponse
    {
        // Parse PayPal webhook payload
        $resource = $payload['resource'] ?? [];
        $eventType = $payload['event_type'] ?? '';

        $status = Status::PENDING;
        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $status = Status::SUCCESS;
        } elseif ($eventType === 'PAYMENT.CAPTURE.DENIED' || $eventType === 'PAYMENT.CAPTURE.REFUNDED') {
            $status = Status::FAILED;
        }

        return new VerificationResponse(
            reference: $resource['id'] ?? 'unknown', // Transaction ID
            status: $status,
            amount: Money::amount(
                $resource['amount']['value'] ?? 0,
                $resource['amount']['currency_code'] ?? 'USD'
            ),
            paidAt: isset($resource['create_time']) ? new DateTimeImmutable($resource['create_time']) : null,
            metadata: $payload
        );
    }
}
