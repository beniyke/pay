<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Webhook Service
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Services;

use Core\Event;
use Helpers\File\Contracts\LoggerInterface;
use Pay\Enums\Status;
use Pay\Events\PaymentFailedEvent;
use Pay\Events\PaymentSuccessfulEvent;
use Pay\Models\PaymentTransaction;
use Pay\PayManager;
use Throwable;

class WebhookService
{
    public function __construct(
        protected PayManager $manager,
        protected LoggerInterface $logger
    ) {
    }

    public function handle(string $driver, string $payload, string $signature): void
    {
        try {
            $gateway = $this->manager->driver($driver);

            if (! $gateway->validateWebhook($payload, $signature)) {
                $this->logger->error("Webhook validation failed for driver: {$driver}");

                return;
            }

            $decodedPayload = json_decode($payload, true);
            if (! $decodedPayload) {
                parse_str($payload, $decodedPayload);
            }

            if (! is_array($decodedPayload)) {
                $this->logger->error("Webhook payload invalid for driver: {$driver}");

                return;
            }

            $response = $gateway->processWebhook($decodedPayload);

            $transaction = $this->findTransaction($response->reference);

            if (! $transaction) {
                $this->logger->info("Webhook ignored: Transaction not found for reference {$response->reference}");

                return;
            }

            $transaction->update([
                'status' => $response->status,
            ]);

            $this->fireEvent($transaction, $response->status, $decodedPayload);

            $this->logger->info("Webhook processed for transaction: {$response->reference}");
        } catch (Throwable $e) {
            $this->logger->error("Webhook Error ({$driver}): " . $e->getMessage());
        }
    }

    protected function findTransaction(string $reference): ?PaymentTransaction
    {
        return PaymentTransaction::where('reference', $reference)->first();
    }

    protected function fireEvent(PaymentTransaction $transaction, Status $status, array $gatewayResponse): void
    {
        if ($status === Status::SUCCESS) {
            Event::dispatch(new PaymentSuccessfulEvent($transaction, $gatewayResponse));
            $this->logger->info("Payment Successful event fired for: {$transaction->reference}");
        } elseif ($status === Status::FAILED) {
            Event::dispatch(new PaymentFailedEvent($transaction));
            $this->logger->info("Payment Failed event fired for: {$transaction->reference}");
        }
    }
}
