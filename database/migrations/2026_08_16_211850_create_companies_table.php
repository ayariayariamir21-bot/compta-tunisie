<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('legal_name')->nullable();

            $table->string('tax_identifier', 50)->nullable()->unique();
            $table->string('registration_number', 100)->nullable();

            $table->string('legal_form', 50)->nullable();

            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->default('TN');

            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();

            $table->string('currency', 3)->default('TND');

            $table->date('fiscal_year_start')->nullable();
            $table->date('fiscal_year_end')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
