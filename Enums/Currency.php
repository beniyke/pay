<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Currency
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Enums;

enum Currency: string
{
    case NGN = 'NGN';
    case USD = 'USD';
    case GBP = 'GBP';
    case EUR = 'EUR';
    case KES = 'KES';
    case GHS = 'GHS';
    case ZAR = 'ZAR';
}
