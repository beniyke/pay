<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Payment Service
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Services;

use Core\Services\ConfigServiceInterface;
use Pay\Contracts\PaymentGatewayInterface;
use Pay\DataObjects\PaymentData;
use Pay\DataObjects\PaymentResponse;
use Pay\DataObjects\VerificationResponse;
use Pay\Enums\Status;
use Pay\Models\PaymentTransaction;
use Pay\Pay;
use Throwable;

class PaymentService
{
    protected string $transactionModel;

    public function __construct(
        protected PaymentGatewayInterface $gateway,
        ConfigServiceInterface $config
    ) {
        $this->transactionModel = $config->get('pay.logging.model', PaymentTransaction::class);
    }

    public function initialize(PaymentData $data, array $fallbackDrivers = []): PaymentResponse
    {
        // Extract payable info from metadata for polymorphic linking
        $payableId = $data->metadata['payable_id'] ?? null;
        $payableType = $data->metadata['payable_type'] ?? null;

        $transaction = $this->transactionModel::create([
            'reference' => $data->reference ?? uniqid('tx_'),
            'driver' => $this->gateway->driver(),
            'status' => Status::PENDING,
            'amount' => $data->amount->getMinorAmount(), // Store minor units
            'currency' => $data->amount->getCurrency()->getCode(),
            'email' => $data->email,
            'metadata' => $data->metadata,
            'payable_id' => $payableId,
            'payable_type' => $payableType,
        ]);

        try {
            return $this->attemptInitialize($this->gateway, $data, $transaction);
        } catch (Throwable $e) {
            foreach ($fallbackDrivers as $driverName) {
                try {
                    $paramDriver = Pay::driver($driverName);

                    $transaction->update(['driver' => $driverName]);

                    return $this->attemptInitialize($paramDriver, $data, $transaction);
                } catch (Throwable $ex) {
                    continue; // Try next fallback
                }
            }

            $transaction->update(['status' => Status::FAILED]);
            throw $e;
        }
    }

    protected function attemptInitialize(PaymentGatewayInterface $gateway, PaymentData $data, $transaction): PaymentResponse
    {
        $response = $gateway->initialize($data);

        if ($response->reference !== $transaction->reference) {
            $transaction->update(['reference' => $response->reference]);
        }

        return $response;
    }

    public function verify(string $reference): VerificationResponse
    {
        $transaction = $this->transactionModel::where('reference', $reference)->first();

        try {
            $response = $this->gateway->verify($reference);

            if ($transaction) {
                $transaction->update([
                    'status' => $response->status,
                    'metadata' => array_merge($transaction->metadata ?? [], $response->metadata ?? [])
                ]);
            }

            return $response;
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
