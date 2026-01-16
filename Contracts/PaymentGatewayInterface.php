<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * PaymentGatewayInterface
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Contracts;

use Pay\DataObjects\PaymentData;
use Pay\DataObjects\PaymentResponse;
use Pay\DataObjects\VerificationResponse;

interface PaymentGatewayInterface
{
    /**
     * Initialize a payment transaction.
     *
     * @param PaymentData $data Transaction data
     *
     * @return PaymentResponse Response from the payment provider
     */
    public function initialize(PaymentData $data): PaymentResponse;

    /**
     * Verify a transaction by reference.
     *
     * @param string $reference Transaction reference or ID
     *
     * @return VerificationResponse Verified transaction details
     */
    public function verify(string $reference): VerificationResponse;

    public function driver(): string;

    public function validateWebhook(string $payload, string $signature): bool;

    public function processWebhook(array $payload): VerificationResponse;
}
