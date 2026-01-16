<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Paystack Driver
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Drivers;

use DateTimeImmutable;
use Helpers\Http\Client\Curl;
use Money\Money;
use Pay\Contracts\PaymentGatewayInterface;
use Pay\Contracts\SubscriptionInterface;
use Pay\DataObjects\PaymentData;
use Pay\DataObjects\PaymentResponse;
use Pay\DataObjects\VerificationResponse;
use Pay\Enums\Status;
use Pay\Exceptions\PaymentException;

class PaystackDriver implements PaymentGatewayInterface, SubscriptionInterface
{
    protected string $secretKey;

    protected string $baseUrl = 'https://api.paystack.co';

    protected Curl $curl;

    public function __construct(string $secretKey, ?Curl $curl = null)
    {
        $this->secretKey = $secretKey;
        $this->curl = $curl ?? new Curl();
    }

    public function driver(): string
    {
        return 'paystack';
    }

    public function initialize(PaymentData $data): PaymentResponse
    {
        // Paystack expects amount in kobo (minor units)
        $payload = $data->toArray(); // toArray returns major amount

        // We override amount with kobo from Money object
        $payload['amount'] = $data->amount->getMinorAmount();

        $response = $this->curl->post($this->baseUrl . '/transaction/initialize', $payload)
            ->withToken($this->secretKey)
            ->asJson()
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("Paystack Error: " . $response->body());
        }

        $resData = $response->json()['data'];

        return new PaymentResponse(
            reference: $data->reference,
            status: Status::PENDING,
            authorizationUrl: $resData['authorization_url'] ?? null,
            providerReference: $resData['access_code'] ?? null,
            metadata: $resData
        );
    }

    public function verify(string $reference): VerificationResponse
    {
        $response = $this->curl->get($this->baseUrl . '/transaction/verify/' . $reference)
            ->withToken($this->secretKey)
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("Paystack Verification Error: " . $response->body());
        }

        $resData = $response->json()['data'];

        return new VerificationResponse(
            reference: $resData['reference'],
            status: $this->normalizeStatus($resData['status']),
            amount: Money::make($resData['amount'], $resData['currency']),
            paidAt: isset($resData['paid_at']) ? new DateTimeImmutable($resData['paid_at']) : null,
            metadata: $resData
        );
    }

    protected function normalizeStatus(string $status): Status
    {
        return match ($status) {
            'success' => Status::SUCCESS,
            'failed' => Status::FAILED,
            default => Status::PENDING,
        };
    }

    // Subscription Methods

    public function createPlan(array $data): array
    {
        $response = $this->curl->post($this->baseUrl . '/plan', $data)
            ->withToken($this->secretKey)
            ->asJson()
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("Paystack Plan Error: " . $response->body());
        }

        return $response->json()['data'];
    }

    public function subscribe(array $data): PaymentResponse
    {
        // Hydrate PaymentData from array
        $paymentData = new PaymentData(
            amount: Money::amount($data['amount'], $data['currency'] ?? 'NGN'),
            email: $data['email'],
            reference: $data['reference'] ?? uniqid('sub-'),
            callbackUrl: $data['callback_url'] ?? '',
            metadata: $data['metadata'] ?? []
        );

        return $this->initialize($paymentData);
    }

    public function unsubscribe(string $subscriptionId): array
    {
        // Pass token strictly to handle disable
        $response = $this->curl->post($this->baseUrl . '/subscription/disable', ['code' => $subscriptionId, 'token' => $this->secretKey])
            ->withToken($this->secretKey)
            ->asJson()
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("Paystack Unsubscribe Error: " . $response->body());
        }

        return $response->json();
    }

    public function validateWebhook(string $payload, string $signature): bool
    {
        return $signature === hash_hmac('sha512', $payload, $this->secretKey);
    }

    public function processWebhook(array $payload): VerificationResponse
    {
        $data = $payload['data'] ?? [];

        return new VerificationResponse(
            reference: $data['reference'],
            status: $this->normalizeStatus($data['status']),
            amount: Money::make($data['amount'], $data['currency']), // Paystack amounts are in kobo
            paidAt: isset($data['paid_at']) ? new DateTimeImmutable($data['paid_at']) : null,
            metadata: $data
        );
    }
}
