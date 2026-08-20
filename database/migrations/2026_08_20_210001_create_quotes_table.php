<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('quote_number', 50);
            $table->date('quote_date');
            $table->date('valid_until')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('currency', 3)->default('TND');
            $table->decimal('subtotal', 18, 3)->default(0);
            $table->decimal('discount_total', 18, 3)->default(0);
            $table->decimal('tax_total', 18, 3)->default(0);
            $table->decimal('total', 18, 3)->default(0);
            $table->integer('payment_terms_days')->default(0);
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'quote_number']);
            $table->index('company_id');
            $table->index('customer_id');
            $table->index('quote_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
