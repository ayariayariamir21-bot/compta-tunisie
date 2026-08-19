<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->constrained();

            $table->foreignId('fiscal_year_id')
                ->constrained();

            $table->foreignId('accounting_period_id')
                ->constrained();

            $table->foreignId('journal_id')
                ->constrained();

            $table->string('entry_number', 30);

            $table->date('entry_date');

            $table->string('reference', 100)->nullable();

            $table->text('description')->nullable();

            $table->string('status', 20)->default('draft');

            $table->foreignId('created_by')
                ->constrained('users');

            $table->timestamp('posted_at')->nullable();

            $table->timestamps();

            $table->unique(['company_id', 'fiscal_year_id', 'journal_id', 'entry_number']);

            $table->index(['company_id', 'fiscal_year_id']);
            $table->index(['journal_id']);
            $table->index(['accounting_period_id']);
            $table->index(['entry_date']);
            $table->index(['status']);
            $table->index(['created_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
