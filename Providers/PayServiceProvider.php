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

use App\Models\User;
use Core\Services\ServiceProvider;
use Database\Relations\HasMany;
use Helpers\String\Str;
use Pay\Contracts\PaymentGatewayInterface;
use Pay\Models\Customer;
use Pay\Models\PaymentTransaction;
use Pay\Models\Subscription;
use Pay\PayManager;

class PayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(PayManager::class);

        $this->container->bind(PaymentGatewayInterface::class, function ($container) {
            return $container->make(PayManager::class)->driver();
        });
    }

    public function boot(): void
    {
        PaymentTransaction::creating(function ($transaction) {
            if (empty($transaction->refid)) {
                $transaction->refid = Str::refid();
            }
        });

        $this->registerUserMacros();
    }

    protected function registerUserMacros(): void
    {
        $container = $this->container;

        User::macro('payments', function (): HasMany {
            return $this->hasMany(PaymentTransaction::class, 'user_id');
        });

        User::macro('subscriptions', function (): HasMany {
            return $this->hasMany(Subscription::class, 'user_id');
        });

        User::macro('customers', function (): HasMany {
            return $this->hasMany(Customer::class, 'user_id');
        });

        User::macro('createAsCustomer', function (array $options = []) use ($container) {
            return $container->get(PayManager::class)->createCustomer($this, $options);
        });

        User::macro('hasPaymentProvider', function () {
            return $this->customers()->exists();
        });

        User::macro('charge', function (int $amount, string $paymentMethodId, array $options = []) use ($container) {
            return $container->get(PayManager::class)->charge($this, $amount, $paymentMethodId, $options);
        });

        User::macro('refund', function (string $paymentId, ?int $amount = null, array $options = []) use ($container) {
            return $container->get(PayManager::class)->refund($paymentId, $amount, $options);
        });
    }
}
