<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Payment Data
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\DataObjects;

use Money\Money;
use Pay\Enums\Currency;

class PaymentData
{
    public function __construct(
        public Money $amount,
        public string $email,
        public ?string $reference = null,
        public ?string $callbackUrl = null,
        public array $metadata = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        $currency = isset($data['currency']) ? Currency::from($data['currency']) : Currency::NGN;

        return new self(
            amount: Money::amount($data['amount'], $currency->value),
            email: $data['email'],
            reference: $data['reference'] ?? null,
            callbackUrl: $data['callback_url'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount->getMajorAmount(),
            'currency' => $this->amount->getCurrency()->getCode(),
            'reference' => $this->reference,
            'callback_url' => $this->callbackUrl,
            'metadata' => $this->metadata,
        ];
    }
}
