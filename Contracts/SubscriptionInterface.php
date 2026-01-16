<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * SubscriptionInterface
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Contracts;

use Pay\DataObjects\PaymentResponse;

interface SubscriptionInterface
{
    public function createPlan(array $data): array;

    /**
     * Subscribe a user to a plan.
     */
    public function subscribe(array $data): PaymentResponse;

    /**
     * Cancel a subscription.
     */
    public function unsubscribe(string $subscriptionId): array;
}
