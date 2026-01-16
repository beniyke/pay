<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Payment Builder
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay;

use Pay\DataObjects\PaymentData;
use Pay\DataObjects\PaymentResponse;
use Throwable;

/**
 * Fluent builder for payment initialization.
 */
class PaymentBuilder
{
    private mixed $amount = null;

    private string $email = '';

    private ?string $reference = null;

    private ?string $callbackUrl = null;

    private array $metadata = [];

    private ?string $currency = null;

    private ?string $driver = null;

    private array $fallbackDrivers = [];

    public function __construct(private PayManager $manager)
    {
        $this->currency = $manager->getDefaultCurrency();
    }

    public function amount(mixed $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function email(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Set a unique transaction reference.
     */
    public function reference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function callbackUrl(string $url): self
    {
        $this->callbackUrl = $url;

        return $this;
    }

    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Set the transaction currency (overrides default).
     */
    public function currency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Set the driver to use for this specific transaction.
     */
    public function driver(string $driver): self
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * Set fallback drivers if the primary driver fails.
     */
    public function fallback(array $drivers): self
    {
        $this->fallbackDrivers = $drivers;

        return $this;
    }

    /**
     * Initialize the payment with the configured data.
     */
    public function initialize(): PaymentResponse
    {
        $data = PaymentData::fromArray([
            'amount' => $this->amount,
            'email' => $this->email,
            'reference' => $this->reference,
            'callback_url' => $this->callbackUrl,
            'metadata' => $this->metadata,
            'currency' => $this->currency,
        ]);

        $drivers = array_filter(array_merge([$this->driver], $this->fallbackDrivers));

        if (empty($drivers)) {
            return $this->manager->driver()->initialize($data);
        }

        $lastException = null;

        foreach ($drivers as $driver) {
            try {
                return $this->manager->driver($driver)->initialize($data);
            } catch (Throwable $e) {
                $lastException = $e;
                continue;
            }
        }

        throw $lastException;
    }
}
