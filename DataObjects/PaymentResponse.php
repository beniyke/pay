<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Payment Response
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\DataObjects;

use Pay\Enums\Status;

final readonly class PaymentResponse
{
    public function __construct(
        public string $reference,
        public Status $status,
        public ?string $authorizationUrl = null,
        public ?string $providerReference = null,
        public array $metadata = []
    ) {
    }

    public static function make(array $data): self
    {
        return new self(
            reference: $data['reference'],
            status: $data['status'] instanceof Status ? $data['status'] : Status::from($data['status']),
            authorizationUrl: $data['authorization_url'] ?? null,
            providerReference: $data['provider_reference'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'status' => $this->status->value,
            'authorization_url' => $this->authorizationUrl,
            'provider_reference' => $this->providerReference,
            'metadata' => $this->metadata,
        ];
    }

    public function isSuccessful(): bool
    {
        return $this->status === Status::SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === Status::FAILED;
    }
}
