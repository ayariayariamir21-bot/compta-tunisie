<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('account_id')
                ->nullable()
                ->constrained('accounts')
                ->nullOnDelete();

            $table->string('code', 20);
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('customer_type', 30);

            $table->string('tax_identifier', 50)->nullable();
            $table->string('rne', 50)->nullable();

            $table->string('address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('governorate', 100)->nullable();
            $table->string('country', 2)->default('TN');

            $table->string('phone', 50)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('website', 255)->nullable();

            $table->integer('payment_terms_days')->default(0);
            $table->decimal('credit_limit', 18, 3)->nullable();

            $table->boolean('is_active')->default(true);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'code']);

            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
