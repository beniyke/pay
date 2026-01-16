<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Payable
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Traits;

use Money\Money;
use Pay\DataObjects\PaymentData;
use Pay\DataObjects\PaymentResponse;
use Pay\Models\PaymentTransaction;
use Pay\Pay;

trait Payable
{
    public function transactions()
    {
        return $this->morphMany(PaymentTransaction::class, 'payable');
    }

    /**
     * Initiate a payment for this entity.
     *
     * The payable info is passed via metadata so the transaction
     * can be linked back to this entity upon verification.
     */
    public function pay(float $amount, array $metadata = [], string $currency = 'NGN'): PaymentResponse
    {
        $metadata['payable_id'] = $this->id;
        $metadata['payable_type'] = get_class($this);

        $data = new PaymentData(
            amount: Money::amount($amount, $currency),
            email: $this->email,
            metadata: $metadata
        );

        return Pay::initialize($data);
    }
}
