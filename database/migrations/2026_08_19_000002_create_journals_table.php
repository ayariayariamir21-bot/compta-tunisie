<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('fiscal_year_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('code', 10);
            $table->string('name');
            $table->string('type', 30);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['company_id', 'fiscal_year_id', 'code']);

            $table->index(['company_id', 'fiscal_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};
