<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Provider
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay\Enums;

enum Provider: string
{
    case PAYSTACK = 'paystack';
    case STRIPE = 'stripe';
    case PAYPAL = 'paypal';
    case FLUTTERWAVE = 'flutterwave';
    case MONNIFY = 'monnify';
    case SQUARE = 'square';
    case OPAY = 'opay';
}
