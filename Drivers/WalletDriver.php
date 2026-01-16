<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Wallet Driver
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Drivers;

use DateTimeImmutable;
use Helpers\File\Contracts\LoggerInterface;
use InvalidArgumentException;
use Money\Money;
use Pay\Contracts\PaymentGatewayInterface;
use Pay\DataObjects\PaymentData;
use Pay\DataObjects\PaymentResponse;
use Pay\DataObjects\VerificationResponse;
use Pay\Enums\Status;
use Pay\Exceptions\PaymentException;
use RuntimeException;
use Throwable;
use Wallet\Enums\TransactionStatus;
use Wallet\Services\WalletManagerService;

class WalletDriver implements PaymentGatewayInterface
{
    public function __construct(
        protected LoggerInterface $logger,
        protected WalletManagerService $walletManager,
        protected string $defaultCurrency = 'USD'
    ) {
    }

    public function driver(): string
    {
        return 'wallet';
    }

    public function initialize(PaymentData $data): PaymentResponse
    {
        // For wallet payments, we need the wallet ID.
        // We can expect 'wallet_id' in the metadata, or infer it from the user.
        // Since PaymentData doesn't strictly enforce a User model, we rely on metadata for now.

        $walletId = $data->metadata['wallet_id'] ?? null;

        if (! $walletId) {
            throw new InvalidArgumentException("Wallet ID is required for wallet payments.");
        }

        try {
            // Attempt to debit the wallet immediately.
            // If successful, the payment is complete.

            $transaction = $this->walletManager->debit(
                (int) $walletId,
                $data->amount,
                [
                    'description' => $data->metadata['description'] ?? 'Payment via Wallet',
                    'payment_processor' => 'wallet',
                    'reference_id' => $data->reference, // Use the pay reference as the wallet reference or metadata
                    'metadata' => array_merge($data->metadata, ['pay_reference' => $data->reference])
                ]
            );

            return new PaymentResponse(
                reference: $data->reference,
                status: Status::SUCCESS,
                providerReference: (string) $transaction->id,
                metadata: [
                    'wallet_transaction_id' => $transaction->id,
                    'message' => 'Payment successful'
                ]
            );
        } catch (Throwable $e) {
            $this->logger->error("Wallet payment failed: " . $e->getMessage());

            return new PaymentResponse(
                reference: $data->reference,
                status: Status::FAILED,
                metadata: [
                    'message' => 'Payment failed: ' . $e->getMessage()
                ]
            );
        }
    }

    public function verify(string $reference): VerificationResponse
    {
        // Lookup transaction via manager
        $tx = $this->walletManager->getTransactionByReference($reference);

        if (! $tx) {
            throw new PaymentException("Wallet transaction not found for reference: {$reference}");
        }

        $wallet = $this->walletManager->find($tx->wallet_id);
        $currency = $wallet->currency ?? $this->defaultCurrency;
        $isSuccess = $tx->status === TransactionStatus::COMPLETED->value;

        return new VerificationResponse(
            reference: $reference,
            status: $isSuccess ? Status::SUCCESS : Status::FAILED,
            amount: Money::make((string) $tx->amount, $currency),
            paidAt: $isSuccess ? new DateTimeImmutable($tx->completed_at) : null,
            metadata: json_decode($tx->metadata ?? '{}', true)
        );
    }

    public function validateWebhook(string $payload, string $signature): bool
    {
        return true; // Wallet driver doesn't use external webhooks
    }

    public function processWebhook(array $payload): VerificationResponse
    {
        // Should not strictly be called for Wallet driver
        throw new RuntimeException("Wallet driver does not process webhooks.");
    }
}
