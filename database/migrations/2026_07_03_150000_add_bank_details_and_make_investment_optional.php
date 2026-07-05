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
        Schema::table('statement_of_accounts', function (Blueprint $table) {
            // Make investment_id nullable to support Dividends/Withdrawals without explicit investment selection
            $table->unsignedBigInteger('investment_id')->nullable()->change();
            
            // Add optional bank details for Withdrawals
            $table->string('bank_name')->nullable();
            $table->string('transfer_reference')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('statement_of_accounts', function (Blueprint $table) {
            // Revert investment_id to not nullable
            $table->unsignedBigInteger('investment_id')->nullable(false)->change();
            
            // Remove bank details columns
            $table->dropColumn(['bank_name', 'transfer_reference']);
        });
    }
};
