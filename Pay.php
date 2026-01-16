<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Pay
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Pay;

use Core\Services\ConfigServiceInterface;
use Pay\Contracts\PaymentGatewayInterface;
use Pay\DataObjects\PaymentData;
use Pay\DataObjects\PaymentResponse;
use Pay\DataObjects\VerificationResponse;
use Pay\Services\PayAnalyticsService;
use RuntimeException;

/**
 * @method static PaymentBuilder          amount(mixed $amount)
 * @method static PaymentBuilder          email(string $email)
 * @method static PaymentBuilder          reference(string $reference)
 * @method static PaymentResponse         initialize(array|PaymentData $data)
 * @method static VerificationResponse    verify(string $reference)
 * @method static PaymentGatewayInterface driver(?string $driver = null)
 * @method static PayAnalyticsService     analytics()
 */
class Pay
{
    private static ?PayManager $instance = null;

    public static function instance(): PayManager
    {
        if (self::$instance === null) {
            $config = resolve(ConfigServiceInterface::class);

            if (! $config) {
                throw new RuntimeException("ConfigServiceInterface could not be resolved.");
            }

            self::$instance = new PayManager($config);
        }

        return self::$instance;
    }

    public static function __callStatic(string $method, array $arguments): mixed
    {
        return self::instance()->$method(...$arguments);
    }
}
