<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->foreignId('sales_tax_account_id')->nullable()->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->dropForeign(['sales_tax_account_id']);
            $table->dropColumn('sales_tax_account_id');
        });
    }
};
