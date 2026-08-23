<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignId('expense_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('label', 500);
            $table->decimal('quantity', 18, 3)->nullable();
            $table->decimal('unit_price', 18, 3);
            $table->decimal('discount_percent', 8, 3)->default(0);
            $table->decimal('gross_amount', 18, 3)->default(0);
            $table->decimal('discount_amount', 18, 3)->default(0);
            $table->decimal('line_subtotal', 18, 3)->default(0);
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->nullOnDelete();
            $table->string('tax_code', 50)->nullable();
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->decimal('tax_amount', 18, 3)->default(0);
            $table->decimal('line_total', 18, 3)->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('expense_id');
            $table->index('expense_account_id');
            $table->index('tax_rate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_lines');
    }
};
