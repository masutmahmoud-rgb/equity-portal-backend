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
        Schema::table('investments', function (Blueprint $table) {
            $table->decimal('indicative_value', 15, 2)->nullable()->after('amount')->comment('Current valuation of investment');
            $table->decimal('profit', 15, 2)->nullable()->after('indicative_value')->comment('Realized or unrealized profit');
            $table->date('valuation_date')->nullable()->after('profit')->comment('Date of last valuation');
            $table->text('valuation_notes')->nullable()->after('valuation_date')->comment('Notes on valuation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn(['indicative_value', 'profit', 'valuation_date', 'valuation_notes']);
        });
    }
};
