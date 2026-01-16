<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Pay Service Provider
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Providers;

use Core\Services\ServiceProvider;
use Helpers\String\Str;
use Pay\Models\PaymentTransaction;
use Pay\PayManager;

class PayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(PayManager::class);
    }

    public function boot(): void
    {
        PaymentTransaction::creating(function ($transaction) {
            if (empty($transaction->refid)) {
                $transaction->refid = Str::random('secure');
            }
        });
    }
}
