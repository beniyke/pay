<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Verification Response
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\DataObjects;

use DateTimeImmutable;
use Money\Money;
use Pay\Enums\Status;

final readonly class VerificationResponse
{
    public function __construct(
        public string $reference,
        public Status $status,
        public Money $amount,
        public ?DateTimeImmutable $paidAt = null,
        public array $metadata = []
    ) {
    }

    public static function make(array $data): self
    {
        return new self(
            reference: $data['reference'],
            status: $data['status'] instanceof Status ? $data['status'] : Status::from($data['status']),
            amount: $data['amount'],
            paidAt: isset($data['paid_at']) ? new DateTimeImmutable($data['paid_at']) : null,
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'amount' => $this->amount->getAmount(),
            'currency' => $this->amount->getCurrency()->getCode(),
            'paid_at' => $this->paidAt?->format('Y-m-d H:i:s'),
            'metadata' => $this->metadata,
        ];
    }

    public function isSuccessful(): bool
    {
        return $this->status === Status::SUCCESS;
    }
}
