<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * 2025_12_29_000001_add_payable_to_payment_transaction_table
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

use Database\Migration\BaseMigration;
use Database\Schema\Schema;
use Database\Schema\SchemaBuilder;

class AddPayableToPaymentTransactionTable extends BaseMigration
{
    public function up(): void
    {
        Schema::table('payment_transaction', function (SchemaBuilder $table) {
            // Polymorphic columns for robust entity linking
            $table->unsignedBigInteger('payable_id')->nullable()->after('id');
            $table->string('payable_type')->nullable()->after('payable_id');

            // Composite index for polymorphic lookups
            $table->index(['payable_type', 'payable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_transaction', function (SchemaBuilder $table) {
            $table->dropIndexByColumns(['payable_type', 'payable_id']);
            $table->dropColumn('payable_id');
            $table->dropColumn('payable_type');
        });
    }
}
