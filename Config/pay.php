<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * pay
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment driver that will be used
    | when one is not explicitly specified.
    |
    */
    'default' => env('PAY_DRIVER', 'paystack'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The default currency to use for transactions.
    |
    */
    'currency' => env('PAY_CURRENCY', 'NGN'),

    /*
    |--------------------------------------------------------------------------
    | Transaction Logging
    |--------------------------------------------------------------------------
    |
    | Enable or disable transaction logging to the database.
    |
    */
    'logging' => [
        'enabled' => true,
        'table' => 'payment_transaction',
        'model' => Pay\Models\PaymentTransaction::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | Configuration for webhook handling.
    |
    */
    'webhooks' => [
        'path' => '/pay/webhook', // Default route path
        'secret' => env('PAY_WEBHOOK_SECRET'), // Generic secret if used, or per-driver
        'queue' => true, // Process webhooks in queue
        'job' => Pay\Jobs\ProcessWebhook::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the settings for each driver.
    |
    */
    'drivers' => [
        'paystack' => [
            'public_key' => env('PAYSTACK_PUBLIC_KEY'),
            'secret_key' => env('PAYSTACK_SECRET_KEY'),
            'payment_url' => 'https://api.paystack.co',
            'merchant_email' => env('PAYSTACK_MERCHANT_EMAIL'),
        ],

        'stripe' => [
            'public_key' => env('STRIPE_PUBLIC_KEY'),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],

        'flutterwave' => [
            'public_key' => env('FLW_PUBLIC_KEY'),
            'secret_key' => env('FLW_SECRET_KEY'),
            'encryption_key' => env('FLW_ENCRYPTION_KEY'),
        ],

        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'mode' => env('PAYPAL_MODE', 'sandbox'), // sandbox or live
        ],

        'monnify' => [
            'api_key' => env('MONNIFY_API_KEY'),
            'secret_key' => env('MONNIFY_SECRET_KEY'),
            'contract_code' => env('MONNIFY_CONTRACT_CODE'),
            'mode' => env('MONNIFY_MODE', 'sandbox'),
        ],

        'square' => [
            'access_token' => env('SQUARE_ACCESS_TOKEN'),
            'location_id' => env('SQUARE_LOCATION_ID'),
            'mode' => env('SQUARE_MODE', 'sandbox'),
        ],

        'opay' => [
            'public_key' => env('OPAY_PUBLIC_KEY'),
            'secret_key' => env('OPAY_SECRET_KEY'),
            'merchant_id' => env('OPAY_MERCHANT_ID'),
            'mode' => env('OPAY_MODE', 'sandbox'),
        ],

        'mollie' => [
            'api_key' => env('MOLLIE_API_KEY'),
        ],

        'nowpayments' => [
            'api_key' => env('NOWPAYMENTS_API_KEY'),
            'mode' => env('NOWPAYMENTS_MODE', 'sandbox'),
        ],

        'wallet' => [
            'mode' => env('WALLET_PAYMENT_MODE', 'live'),
        ],
    ],
];
