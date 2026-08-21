<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->restrictOnDelete();
            $table->foreignId('accounting_period_id')->constrained('accounting_periods')->restrictOnDelete();
            $table->foreignId('journal_id')->constrained('journals')->restrictOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->string('invoice_number');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('status')->default('draft');
            $table->string('currency', 3)->default('TND');
            $table->decimal('subtotal', 18, 3)->default(0);
            $table->decimal('discount_total', 18, 3)->default(0);
            $table->decimal('tax_total', 18, 3)->default(0);
            $table->decimal('total', 18, 3)->default(0);
            $table->integer('payment_terms_days')->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'invoice_number']);
            $table->index('company_id');
            $table->index('customer_id');
            $table->index('fiscal_year_id');
            $table->index('accounting_period_id');
            $table->index('journal_id');
            $table->index('invoice_date');
            $table->index('due_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
