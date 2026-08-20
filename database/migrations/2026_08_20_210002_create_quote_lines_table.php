<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('description', 500);
            $table->decimal('quantity', 18, 3);
            $table->string('unit', 50)->default('unit');
            $table->decimal('unit_price', 18, 3);
            $table->decimal('discount_percent', 8, 3)->default(0);
            $table->decimal('discount_amount', 18, 3)->default(0);
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->decimal('tax_amount', 18, 3)->default(0);
            $table->decimal('line_subtotal', 18, 3)->default(0);
            $table->decimal('line_total', 18, 3)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('quote_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_lines');
    }
};
