<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Mollie Driver
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

class MollieDriver implements PaymentGatewayInterface
{
    protected string $apiKey;

    protected string $baseUrl = 'https://api.mollie.com/v2';

    protected Curl $curl;

    public function __construct(string $apiKey, ?Curl $curl = null)
    {
        $this->apiKey = $apiKey;
        $this->curl = $curl ?? new Curl();
    }

    public function driver(): string
    {
        return 'mollie';
    }

    public function initialize(PaymentData $data): PaymentResponse
    {
        $payload = [
            'amount' => [
                'currency' => $data->amount->getCurrency()->getCode(),
                'value' => number_format($data->amount->getMajorAmount(), 2, '.', ''),
            ],
            'description' => $data->metadata['description'] ?? 'Payment for order ' . $data->reference,
            'redirectUrl' => $data->callbackUrl,
            'metadata' => array_merge($data->metadata, ['reference' => $data->reference]),
        ];

        $response = $this->curl->post($this->baseUrl . '/payments', $payload)
            ->withToken($this->apiKey)
            ->asJson()
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("Mollie Error: " . $response->body());
        }

        $resData = $response->json();

        return new PaymentResponse(
            reference: $data->reference,
            status: Status::PENDING,
            authorizationUrl: $resData['_links']['checkout']['href'],
            providerReference: $resData['id'],
            metadata: $resData
        );
    }

    public function verify(string $reference): VerificationResponse
    {
        // Reference here could be Mollie's payment ID (tr_...)
        $response = $this->curl->get($this->baseUrl . '/payments/' . $reference)
            ->withToken($this->apiKey)
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("Mollie Verification Error: " . $response->body());
        }

        $resData = $response->json();

        return new VerificationResponse(
            reference: $resData['metadata']['reference'] ?? $reference,
            status: $this->normalizeStatus($resData['status']),
            amount: Money::amount($resData['amount']['value'], $resData['amount']['currency']),
            paidAt: isset($resData['paidAt']) ? new DateTimeImmutable($resData['paidAt']) : null,
            metadata: $resData
        );
    }

    protected function normalizeStatus(string $status): Status
    {
        return match ($status) {
            'paid' => Status::SUCCESS,
            'canceled' => Status::FAILED,
            'failed' => Status::FAILED,
            'expired' => Status::FAILED,
            default => Status::PENDING,
        };
    }

    public function validateWebhook(string $payload, string $signature): bool
    {
        // Mollie generally relies on fetching the payment status from the API using the ID passed in the webhook.
        // There isn't a robust signature mechanism by default like Stripe's HMAC.
        // It's verified by the fact that the ID exists on Mollie's side and status is legitimate when queried.

        return true;
    }

    public function processWebhook(array $payload): VerificationResponse
    {
        // If it's parsed as array by service, checks for 'id'.

        $id = $payload['id'] ?? null;

        if (! $id) {
            throw new PaymentException("Mollie Webhook Error: No ID found in payload.");
        }

        // We must fetch the payment status from Mollie to get details
        return $this->verify($id);
    }
}
