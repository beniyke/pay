<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Payment Failed
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Events;

use Pay\Models\PaymentTransaction;

class PaymentFailedEvent
{
    public function __construct(
        public PaymentTransaction $transaction,
        public ?string $reason = null
    ) {
    }
}
