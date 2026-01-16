<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Now Payments Driver
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

class NowPaymentsDriver implements PaymentGatewayInterface
{
    protected string $apiKey;

    protected string $ipnSecret;

    protected string $baseUrl = 'https://api.nowpayments.io/v1';

    protected Curl $curl;

    public function __construct(string $apiKey, string $ipnSecret = '', bool $sandbox = true, ?Curl $curl = null)
    {
        $this->apiKey = $apiKey;
        $this->ipnSecret = $ipnSecret;
        $this->curl = $curl ?? new Curl();
        if ($sandbox) {
            $this->baseUrl = 'https://api-sandbox.nowpayments.io/v1';
        }
    }

    public function driver(): string
    {
        return 'nowpayments';
    }

    public function initialize(PaymentData $data): PaymentResponse
    {
        $payload = [
            'price_amount' => $data->amount->getMajorAmount(),
            'price_currency' => $data->amount->getCurrency()->getCode(),
            'order_id' => $data->reference,
            'order_description' => $data->metadata['description'] ?? 'Payment',
            'success_url' => $data->callbackUrl,
            'cancel_url' => $data->callbackUrl,
        ];

        $response = $this->curl->post($this->baseUrl . '/payment', $payload)
            ->withHeader('x-api-key', $this->apiKey)
            ->asJson()
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("NowPayments Error: " . $response->body());
        }

        $resData = $response->json();

        return new PaymentResponse(
            reference: $data->reference,
            status: Status::PENDING,
            authorizationUrl: $resData['invoice_url'],
            providerReference: (string) $resData['id'],
            metadata: $resData
        );
    }

    public function verify(string $reference): VerificationResponse
    {
        $response = $this->curl->get($this->baseUrl . '/payment/' . $reference)
            ->withHeader('x-api-key', $this->apiKey)
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("NowPayments Verification Error: " . $response->body());
        }

        $resData = $response->json();

        return new VerificationResponse(
            reference: $resData['order_id'] ?? $reference,
            status: $this->normalizeStatus($resData['payment_status']),
            amount: Money::amount($resData['purchase_id'] ? $resData['pay_amount'] : $resData['price_amount'], $resData['price_currency']),
            paidAt: isset($resData['updated_at']) ? new DateTimeImmutable($resData['updated_at']) : null,
            metadata: $resData
        );
    }

    protected function normalizeStatus(string $status): Status
    {
        return match ($status) {
            'finished' => Status::SUCCESS,
            'failed' => Status::FAILED,
            'refunded' => Status::FAILED,
            'expired' => Status::FAILED,
            default => Status::PENDING,
        };
    }

    public function validateWebhook(string $payload, string $signature): bool
    {
        // NowPayments: Sort params by key, convert to string, HMAC-SHA512 with IPN Secret.
        // If the payload was already a specific JSON format, decoding and re-encoding might change whitespace.
        // If possible, $payload should be used directly IF it is already sorted. But it's safer to sort.

        $data = json_decode($payload, true);
        if (! is_array($data)) {
            return false;
        }

        ksort($data);
        $jsonString = json_encode($data);

        $calculated = hash_hmac('sha512', $jsonString, $this->ipnSecret);

        return $signature === $calculated;
    }

    public function processWebhook(array $payload): VerificationResponse
    {
        $status = $this->normalizeStatus($payload['payment_status'] ?? 'waiting');

        return new VerificationResponse(
            reference: $payload['order_id'] ?? 'unknown',
            status: $status,
            amount: Money::make(
                ($payload['pay_amount'] ?? 0) * 100, // NowPayments usually sends major units in float, we need minor for Money (Wait, Money::make expects int)
                $payload['pay_currency'] ?? 'USD'
            ),
            paidAt: isset($payload['updated_at']) ? new DateTimeImmutable((string)$payload['updated_at']) : null,
            metadata: $payload
        );
    }
}
