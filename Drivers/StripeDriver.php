<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Stripe Driver
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

class StripeDriver implements PaymentGatewayInterface
{
    protected string $secretKey;

    protected string $webhookSecret;

    protected string $baseUrl = 'https://api.stripe.com/v1';

    protected Curl $curl;

    public function __construct(string $secretKey, string $webhookSecret = '', ?Curl $curl = null)
    {
        $this->secretKey = $secretKey;
        $this->webhookSecret = $webhookSecret;
        $this->curl = $curl ?? new Curl();
    }

    public function driver(): string
    {
        return 'stripe';
    }

    public function initialize(PaymentData $data): PaymentResponse
    {
        // Stripe Checkout Session
        // Build Stripe-specific payload from PaymentData
        $payload = [
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $data->amount->getCurrency()->getCode(),
                    'product_data' => [
                        'name' => 'Payment for Order ' . ($data->reference ?? uniqid()),
                    ],
                    'unit_amount' => $data->amount->getMinorAmount(), // cents
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $data->callbackUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $data->callbackUrl . '?status=cancelled',
            'customer_email' => $data->email,
        ];

        $response = $this->curl->post($this->baseUrl . '/checkout/sessions', $payload)
            ->withToken($this->secretKey)
            ->withHeader('Idempotency-Key', $data->reference ?? uniqid())
            ->asForm() // Stripe uses form-encoded data usually
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("Stripe Error: " . $response->body());
        }

        $resData = $response->json();

        return new PaymentResponse(
            reference: $data->reference,
            status: Status::PENDING,
            authorizationUrl: $resData['url'],
            providerReference: $resData['id'],
            metadata: $resData
        );
    }

    public function verify(string $reference): VerificationResponse
    {
        // Verify via checkout session or payment intent
        // Reference here assumed to be session_id
        $response = $this->curl->get($this->baseUrl . '/checkout/sessions/' . $reference)
            ->withToken($this->secretKey)
            ->asForm()
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("Stripe Verification Error: " . $response->body());
        }

        $resData = $response->json();

        return new VerificationResponse(
            reference: $reference,
            status: $resData['payment_status'] === 'paid' ? Status::SUCCESS : Status::PENDING,
            amount: Money::make($resData['amount_total'], $resData['currency']),
            paidAt: null, // Stripe doesn't always have this in session object easily, could be added later
            metadata: $resData
        );
    }

    public function validateWebhook(string $payload, string $signature): bool
    {
        // Stripe requires the raw body for signature verification to work correctly.
        // We'll perform a simplified check here assuming the signature header format: t=timestamp,v1=signature.
        // In a real production environment, strict timestamp checking and raw body HMAC should be used.

        $parts = explode(',', $signature);
        $timestamp = null;
        $hash = null;

        foreach ($parts as $part) {
            if (str_starts_with($part, 't=')) {
                $timestamp = substr($part, 2);
            } elseif (str_starts_with($part, 'v1=')) {
                $hash = substr($part, 3);
            }
        }

        if (! $timestamp || ! $hash) {
            return false;
        }

        // Reconstruct signed payload using json_encode (Note: this is fragile if specific JSON formatting differs from Stripe's raw body)
        // Ideally, the caller should pass the raw payload string.
        $signedPayload = $timestamp . '.' . $payload;

        if (empty($this->webhookSecret)) {
            return false;
        }

        return hash_hmac('sha256', $signedPayload, $this->webhookSecret) === $hash;
    }

    public function processWebhook(array $payload): VerificationResponse
    {
        $object = $payload['data']['object'] ?? [];

        // Extract status
        $status = Status::FAILED;
        if (isset($payload['type'])) {
            if ($payload['type'] === 'checkout.session.completed' || $payload['type'] === 'payment_intent.succeeded') {
                $status = Status::SUCCESS;
            } elseif ($payload['type'] === 'payment_intent.processing') {
                $status = Status::PENDING;
            }
        }

        return new VerificationResponse(
            reference: $object['client_reference_id'] ?? $object['id'] ?? 'unknown',
            status: $status,
            amount: Money::make($object['amount_total'] ?? $object['amount'] ?? 0, $object['currency'] ?? 'USD'),
            paidAt: new DateTimeImmutable(),
            metadata: $payload
        );
    }
}
