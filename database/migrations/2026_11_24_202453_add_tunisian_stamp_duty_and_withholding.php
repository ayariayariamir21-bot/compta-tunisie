<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tunisian fiscal obligations: droit de timbre on invoices and
     * retenue à la source on customer and supplier payments.
     */
    public function up(): void
    {
        Schema::table('company_accounting_settings', function (Blueprint $table) {
            $table->decimal('stamp_duty_amount', 18, 3)->default(0);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('is_stamp_duty_exempt')->default(false);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('stamp_duty', 18, 3)->default(0);
        });

        foreach (['customer_payments', 'supplier_payments'] as $paymentTable) {
            Schema::table($paymentTable, function (Blueprint $table) {
                $table->decimal('withholding_rate', 6, 3)->default(0);
                $table->decimal('withholding_amount', 18, 3)->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach (['customer_payments', 'supplier_payments'] as $paymentTable) {
            Schema::table($paymentTable, function (Blueprint $table) {
                $table->dropColumn(['withholding_rate', 'withholding_amount']);
            });
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('stamp_duty');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('is_stamp_duty_exempt');
        });

        Schema::table('company_accounting_settings', function (Blueprint $table) {
            $table->dropColumn('stamp_duty_amount');
        });
    }
};
