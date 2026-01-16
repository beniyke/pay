<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Customer Data
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\DataObjects;

class CustomerData
{
    public function __construct(
        public string $email,
        public ?string $name = null,
        public ?string $phone = null
    ) {
    }
}
