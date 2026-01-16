<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * O Pay Driver
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

class OPayDriver implements PaymentGatewayInterface
{
    protected string $publicKey;

    protected string $secretKey;

    protected string $merchantId;

    protected string $baseUrl;

    protected Curl $curl;

    public function __construct(string $publicKey, string $secretKey, string $merchantId, string $mode = 'sandbox', ?Curl $curl = null)
    {
        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;
        $this->merchantId = $merchantId;
        $this->baseUrl = $mode === 'sandbox' ? 'https://cashierapi.sandbox.opaycheckout.com/api/v1/international' : 'https://cashierapi.opaycheckout.com/api/v1/international';
        $this->curl = $curl ?? new Curl();
    }

    public function driver(): string
    {
        return 'opay';
    }

    public function initialize(PaymentData $data): PaymentResponse
    {
        $payload = [
            'reference' => $data->reference ?? uniqid('opay_'),
            'mchShortName' => $data->metadata['name'] ?? 'Merchant',
            'productName' => 'Payment',
            'productDesc' => 'Service Payment',
            'userPhone' => $data->metadata['phone'] ?? '+2348000000000',
            'userEmail' => $data->email,
            'amount' => (string) $data->amount->getMinorAmount(),
            'currency' => $data->amount->getCurrency()->getCode(),
            'returnUrl' => $data->callbackUrl,
            'payType' => 'BalancePayment,BonusPayment,CardPayment',
            'expireAt' => '10', // 10 minutes
        ];

        $response = $this->curl->post($this->baseUrl . '/cashier/initialize', $payload)
            ->withHeader('MerchantId', $this->merchantId)
            ->withToken($this->publicKey)
            ->asJson()
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("OPay Error: " . $response->body());
        }

        $resData = $response->json()['data'];

        return new PaymentResponse(
            reference: $payload['reference'],
            status: Status::PENDING,
            authorizationUrl: $resData['cashierUrl'] ?? null,
            providerReference: $resData['orderNo'] ?? null,
            metadata: $resData
        );
    }

    public function verify(string $reference): VerificationResponse
    {
        $payload = [
            'reference' => $reference,
        ];

        // OPay requires HMAC-SHA512 of sorted JSON payload for status
        ksort($payload);
        $signature = hash_hmac('sha512', json_encode($payload), $this->secretKey);

        $response = $this->curl->post($this->baseUrl . '/cashier/status', $payload)
            ->withHeader('MerchantId', $this->merchantId)
            ->withHeader('Signature', $signature)
            ->asJson()
            ->send();

        if (! $response->ok()) {
            throw new PaymentException("OPay Verify Error: " . $response->body());
        }

        $resData = $response->json()['data'];

        return new VerificationResponse(
            reference: $resData['reference'],
            status: $this->normalizeStatus($resData['status']),
            amount: Money::make($resData['amount'], $resData['currency']),
            paidAt: null,
            metadata: $resData
        );
    }

    protected function normalizeStatus(string $status): Status
    {
        return match ($status) {
            'SUCCESS' => Status::SUCCESS,
            'FAIL', 'CLOSE', 'CANCEL' => Status::FAILED,
            default => Status::PENDING,
        };
    }

    public function validateWebhook(string $payload, string $signature): bool
    {
        // OPay verification: HMAC-SHA512 of the payload signed with Secret Key
        // Warning: Payload order might matter if re-encoding.
        // Ideally use valid raw payload string passed in $payload.

        $calculated = hash_hmac('sha512', $payload, $this->secretKey);

        return $signature === $calculated;
    }

    public function processWebhook(array $payload): VerificationResponse
    {
        // Payload structure: { "payload": { ... } } or flattened?
        // OPay webhook usually sends: { "notifyType": "...", "outTradeNo": "...", "tradeStatus": "topup_success", ... }

        $status = Status::PENDING;
        $tradeStatus = $payload['tradeStatus'] ?? '';

        if ($tradeStatus === 'topup_success' || $tradeStatus === 'success') {
            $status = Status::SUCCESS;
        } elseif ($tradeStatus === 'close' || $tradeStatus === 'fail') {
            $status = Status::FAILED;
        }

        return new VerificationResponse(
            reference: $payload['outTradeNo'] ?? 'unknown',
            status: $status,
            amount: Money::make($payload['amount'] ?? 0, $payload['currency'] ?? 'NGN'), // OPay usually minor units
            paidAt: new DateTimeImmutable(),
            metadata: $payload
        );
    }
}
