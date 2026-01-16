<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Monnify Driver
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

class MonnifyDriver implements PaymentGatewayInterface
{
    protected string $apiKey;

    protected string $secretKey;

    protected string $contractCode;

    protected string $baseUrl;

    protected Curl $curl;

    public function __construct(string $apiKey, string $secretKey, string $contractCode, bool $sandbox = true, ?Curl $curl = null)
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->contractCode = $contractCode;
        $this->baseUrl = $sandbox ? 'https://sandbox.monnify.com/api/v1' : 'https://api.monnify.com/api/v1';
        $this->curl = $curl ?? new Curl();
    }

    public function driver(): string
    {
        return 'monnify';
    }

    private function getAccessToken(): string
    {
        $response = $this->curl->post($this->baseUrl . '/auth/login', [])
            ->withBasicAuth($this->apiKey, $this->secretKey)
            ->asJson()
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("Monnify Token Error: " . $response->body());
        }

        return $response->json()['responseBody']['accessToken'];
    }

    public function initialize(PaymentData $data): PaymentResponse
    {
        $token = $this->getAccessToken();

        $payload = [
            'amount' => $data->amount->getMajorAmount(),
            'customerName' => $data->metadata['name'] ?? 'Customer',
            'customerEmail' => $data->email,
            'paymentReference' => $data->reference ?? uniqid('mnfy_'),
            'paymentDescription' => $data->metadata['description'] ?? 'Payment',
            'currencyCode' => $data->amount->getCurrency()->getCode(),
            'contractCode' => $this->contractCode,
            'redirectUrl' => $data->callbackUrl,
            'paymentMethods' => ['CARD', 'ACCOUNT_TRANSFER'],
        ];

        $response = $this->curl->post($this->baseUrl . '/merchant/transactions/init-transaction', $payload)
            ->withToken($token)
            ->asJson()
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("Monnify Error: " . $response->body());
        }

        $resData = $response->json()['responseBody'];

        return new PaymentResponse(
            reference: $payload['paymentReference'],
            status: Status::PENDING,
            authorizationUrl: $resData['checkoutUrl'],
            providerReference: $resData['transactionReference'],
            metadata: $resData
        );
    }

    public function verify(string $reference): VerificationResponse
    {
        $token = $this->getAccessToken();

        $response = $this->curl->get($this->baseUrl . '/merchant/transactions/query?transactionReference=' . $reference)
            ->withToken($token)
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("Monnify Verification Error: " . $response->body());
        }

        $resData = $response->json()['responseBody'];

        return new VerificationResponse(
            reference: $resData['paymentReference'],
            status: $this->normalizeStatus($resData['paymentStatus']),
            amount: Money::amount($resData['amountPaid'], $resData['currencyCode']),
            paidAt: isset($resData['completedOn']) ? new DateTimeImmutable($resData['completedOn']) : null,
            metadata: $resData
        );
    }

    protected function normalizeStatus(string $status): Status
    {
        return match ($status) {
            'PAID' => Status::SUCCESS,
            'OVERPAID' => Status::SUCCESS, # Consider success
            'FAILED' => Status::FAILED,
            default => Status::PENDING,
        };
    }

    public function validateWebhook(string $payload, string $signature): bool
    {
        $computed = hash('sha512', $this->secretKey . '|' . $payload);

        return $signature === $computed;
    }

    public function processWebhook(array $payload): VerificationResponse
    {
        $data = $payload['eventData'] ?? $payload;

        $status = Status::PENDING;
        $paymentStatus = $data['paymentStatus'] ?? '';

        if ($paymentStatus === 'PAID' || $paymentStatus === 'OVERPAID') {
            $status = Status::SUCCESS;
        } elseif ($paymentStatus === 'FAILED' || $paymentStatus === 'EXPIRED') {
            $status = Status::FAILED;
        }

        return new VerificationResponse(
            reference: $data['paymentReference'] ?? 'unknown',
            status: $status,
            amount: Money::amount($data['amountPaid'] ?? 0, $data['currency'] ?? 'NGN'),
            paidAt: isset($data['paidOn']) ? new DateTimeImmutable($data['paidOn']) : null,
            metadata: $data
        );
    }
}
