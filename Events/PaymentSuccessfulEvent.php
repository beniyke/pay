<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Payment Successful
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Events;

use Pay\Models\PaymentTransaction;

class PaymentSuccessfulEvent
{
    public function __construct(
        public PaymentTransaction $transaction,
        public array $gatewayResponse
    ) {
    }
}
