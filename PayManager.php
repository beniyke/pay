<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Pay Manager
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay;

use Core\Services\ConfigServiceInterface;
use Helpers\File\Contracts\LoggerInterface;
use Pay\Contracts\PaymentGatewayInterface;
use Pay\DataObjects\PaymentData;
use Pay\Drivers\FlutterwaveDriver;
use Pay\Drivers\MollieDriver;
use Pay\Drivers\MonnifyDriver;
use Pay\Drivers\NowPaymentsDriver;
use Pay\Drivers\OPayDriver;
use Pay\Drivers\PayPalDriver;
use Pay\Drivers\PaystackDriver;
use Pay\Drivers\SquareDriver;
use Pay\Drivers\StripeDriver;
use Pay\Drivers\WalletDriver;
use Pay\Exceptions\PaymentException;
use Pay\Services\PayAnalyticsService;
use Wallet\Services\WalletManagerService;

class PayManager
{
    protected array $config;

    protected array $drivers = [];

    public function __construct(ConfigServiceInterface $config)
    {
        $this->config = $config->get('pay') ?? [];
    }

    public function getDefaultCurrency(): string
    {
        return $this->config['currency'] ?? 'NGN';
    }

    public function amount(mixed $amount): PaymentBuilder
    {
        return (new PaymentBuilder($this))->amount($amount);
    }

    public function email(string $email): PaymentBuilder
    {
        return (new PaymentBuilder($this))->email($email);
    }

    public function reference(string $reference): PaymentBuilder
    {
        return (new PaymentBuilder($this))->reference($reference);
    }

    public function driver(?string $driver = null): PaymentGatewayInterface
    {
        $driver = $driver ?? $this->config['default'] ?? 'paystack';

        if (! isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    protected function createDriver(string $driver): PaymentGatewayInterface
    {
        switch ($driver) {
            case 'paystack':
                return new PaystackDriver($this->config['drivers']['paystack']['secret_key'] ?? '');

            case 'stripe':
                return new StripeDriver(
                    $this->config['drivers']['stripe']['secret_key'] ?? '',
                    $this->config['drivers']['stripe']['webhook_secret'] ?? ''
                );

            case 'flutterwave':
                return new FlutterwaveDriver(
                    $this->config['drivers']['flutterwave']['secret_key'] ?? '',
                    $this->config['drivers']['flutterwave']['secret_hash'] ?? ''
                );

            case 'paypal':
                $config = $this->config['drivers']['paypal'] ?? [];

                return new PayPalDriver(
                    $config['client_id'] ?? '',
                    $config['client_secret'] ?? '',
                    $config['mode'] ?? 'sandbox'
                );

            case 'monnify':
                $config = $this->config['drivers']['monnify'] ?? [];

                return new MonnifyDriver(
                    $config['api_key'] ?? '',
                    $config['secret_key'] ?? '',
                    $config['contract_code'] ?? '',
                    ($config['mode'] ?? 'sandbox') === 'sandbox'
                );

            case 'square':
                $config = $this->config['drivers']['square'] ?? [];

                return new SquareDriver(
                    $config['access_token'] ?? '',
                    $config['location_id'] ?? '',
                    $config['signature_key'] ?? '',
                    ($config['mode'] ?? 'sandbox') === 'sandbox'
                );

            case 'opay':
                $config = $this->config['drivers']['opay'] ?? [];

                return new OPayDriver(
                    $config['public_key'] ?? '',
                    $config['secret_key'] ?? '',
                    $config['merchant_id'] ?? '',
                    $config['mode'] ?? 'sandbox'
                );

            case 'mollie':
                return new MollieDriver($this->config['drivers']['mollie']['api_key'] ?? '');

            case 'nowpayments':
                $config = $this->config['drivers']['nowpayments'] ?? [];

                return new NowPaymentsDriver(
                    $config['api_key'] ?? '',
                    $config['ipn_secret'] ?? '',
                    ($config['mode'] ?? 'sandbox') === 'sandbox'
                );

            case 'wallet':
                return new WalletDriver(
                    resolve(LoggerInterface::class),
                    resolve(WalletManagerService::class),
                    $this->getDefaultCurrency()
                );

            default:
                throw new PaymentException("Payment driver [{$driver}] not supported.");
        }
    }

    public function __call($method, $parameters)
    {
        if ($method === 'initialize' && isset($parameters[0]) && is_array($parameters[0])) {
            $parameters[0] = PaymentData::fromArray($parameters[0]);
        }

        return $this->driver()->$method(...$parameters);
    }

    public function analytics(): PayAnalyticsService
    {
        return resolve(PayAnalyticsService::class);
    }
}
