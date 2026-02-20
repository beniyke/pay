<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class CreatePayTransactionTable extends BaseMigration
{
    public function up(): void
    {
        Schema::createIfNotExists('pay_transaction', function (SchemaBuilder $table) {
            $table->id();
            $table->unsignedBigInteger('payable_id')->nullable()->index();
            $table->string('payable_type')->nullable()->index();
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
        Schema::dropIfExists('pay_transaction');
    }
}
