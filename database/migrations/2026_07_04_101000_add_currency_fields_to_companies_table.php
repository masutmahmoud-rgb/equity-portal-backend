<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('base_currency', 10)->default('USD')->after('latest_valuation');
            $table->decimal('exchange_rate', 15, 6)->default(1)->after('base_currency');
            $table->index('base_currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['base_currency']);
            $table->dropColumn(['base_currency', 'exchange_rate']);
        });
    }
};