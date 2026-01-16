<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * 2025_12_27_102745_create_payment_transaction_table
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreatePaymentTransactionTable extends BaseMigration
{
    public function up(): void
    {
        Schema::create('payment_transaction', function (SchemaBuilder $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('refid')->unique()->index();
            $table->string('driver'); // paystack, stripe, paypal
            $table->string('status')->default('pending'); // pending, success, failed
            $table->integer('amount');
            $table->string('currency')->default('NGN');
            $table->string('email')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transaction');
    }
}
