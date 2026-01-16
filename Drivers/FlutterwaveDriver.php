<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Flutterwave Driver
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Drivers;

use DateTimeImmutable;
use Helpers\Http\Client\Curl;
use Money\Money;
use Pay\Contracts\PaymentGatewayInterface;
use Pay\DataObjects\PaymentData;
use Pay\DataObjects\PaymentResponse;
use Pay\DataObjects\VerificationResponse;
use Pay\Enums\Status;
use Pay\Exceptions\PaymentException;

class FlutterwaveDriver implements PaymentGatewayInterface
{
    protected string $secretKey;

    protected string $secretHash;

    protected string $baseUrl = 'https://api.flutterwave.com/v3';

    protected Curl $curl;

    public function __construct(string $secretKey, string $secretHash = '', ?Curl $curl = null)
    {
        $this->secretKey = $secretKey;
        $this->secretHash = $secretHash;
        $this->curl = $curl ?? new Curl();
    }

    public function driver(): string
    {
        return 'flutterwave';
    }

    public function initialize(PaymentData $data): PaymentResponse
    {
        $payload = [
            'tx_ref' => $data->reference ?? uniqid('flw-'),
            'amount' => $data->amount->getMajorAmount(),
            'currency' => $data->amount->getCurrency()->getCode(),
            'redirect_url' => $data->callbackUrl,
            'customer' => [
                'email' => $data->email,
            ],
            'meta' => $data->metadata,
            'payment_options' => 'card',
        ];

        $response = $this->curl->post($this->baseUrl . '/payments', $payload)
            ->withToken($this->secretKey)
            ->asJson()
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("Flutterwave Error: " . $response->body());
        }

        $resData = $response->json();

        return new PaymentResponse(
            reference: $payload['tx_ref'],
            status: Status::PENDING,
            authorizationUrl: $resData['data']['link'],
            providerReference: (string) ($resData['data']['id'] ?? ''),
            metadata: $resData['data']
        );
    }

    public function verify(string $reference): VerificationResponse
    {
        $response = $this->curl->get($this->baseUrl . '/transactions/verify_by_reference?tx_ref=' . $reference)
            ->withToken($this->secretKey)
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("Flutterwave Verification Error: " . $response->body());
        }

        $resData = $response->json()['data'];

        return new VerificationResponse(
            reference: $resData['tx_ref'],
            status: $this->normalizeStatus($resData['status']),
            amount: Money::amount($resData['amount'], $resData['currency']),
            paidAt: isset($resData['created_at']) ? new DateTimeImmutable($resData['created_at']) : null,
            metadata: $resData
        );
    }

    protected function normalizeStatus(string $status): Status
    {
        return match ($status) {
            'successful' => Status::SUCCESS,
            'failed' => Status::FAILED,
            default => Status::PENDING,
        };
    }

    public function validateWebhook(string $payload, string $signature): bool
    {
        // Flutterwave uses a "verif-hash" header which should match your specific "Secret Hash"
        // configured in the dashboard.

        if (empty($this->secretHash)) {
            // Cannot verify without the hash configured
            return false;
        }

        return $signature === $this->secretHash;
    }

    public function processWebhook(array $payload): VerificationResponse
    {
        // FLW webhook payload structure
        $data = $payload['data'] ?? $payload; // Sometimes wrapper exists

        return new VerificationResponse(
            reference: $data['tx_ref'],
            status: $this->normalizeStatus($data['status']),
            amount: Money::amount($data['amount'], $data['currency']),
            paidAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            metadata: $data
        );
    }
}
