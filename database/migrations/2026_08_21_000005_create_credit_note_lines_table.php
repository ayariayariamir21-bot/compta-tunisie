<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_note_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
            $table->foreignId('invoice_line_id')->nullable()->constrained('invoice_lines')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('description', 500);
            $table->decimal('quantity', 18, 3);
            $table->string('unit', 50)->default('unit');
            $table->decimal('unit_price', 18, 3);
            $table->decimal('discount_percent', 8, 3)->default(0);
            $table->decimal('discount_amount', 18, 3)->default(0);
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->string('tax_code', 50)->nullable();
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->decimal('tax_amount', 18, 3)->default(0);
            $table->decimal('line_subtotal', 18, 3)->default(0);
            $table->decimal('line_total', 18, 3)->default(0);
            $table->foreignId('sales_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('credit_note_id');
            $table->index('invoice_line_id');
            $table->index('product_id');
            $table->index('sales_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_lines');
    }
};
