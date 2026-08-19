<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_accounting_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('default_currency', 3)->default('TND');
            $table->unsignedTinyInteger('decimal_precision')->default(3);
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1);

            $table->foreignId('default_sales_journal_id')
                ->nullable()
                ->constrained('journals')
                ->nullOnDelete();

            $table->foreignId('default_purchase_journal_id')
                ->nullable()
                ->constrained('journals')
                ->nullOnDelete();

            $table->foreignId('default_bank_journal_id')
                ->nullable()
                ->constrained('journals')
                ->nullOnDelete();

            $table->foreignId('default_cash_journal_id')
                ->nullable()
                ->constrained('journals')
                ->nullOnDelete();

            $table->foreignId('default_misc_journal_id')
                ->nullable()
                ->constrained('journals')
                ->nullOnDelete();

            $table->foreignId('default_customer_account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();

            $table->foreignId('default_supplier_account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();

            $table->foreignId('default_sales_account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();

            $table->foreignId('default_purchase_account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();

            $table->foreignId('default_bank_account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();

            $table->foreignId('default_cash_account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();

            $table->string('invoice_prefix', 20)->nullable()->default('FAC');
            $table->unsignedInteger('invoice_next_number')->default(1);
            $table->string('quote_prefix', 20)->nullable()->default('DEV');
            $table->unsignedInteger('quote_next_number')->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_accounting_settings');
    }
};
