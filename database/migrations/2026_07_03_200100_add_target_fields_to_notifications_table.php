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
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('target_investor_id')->nullable()->after('is_active')->constrained('investors')->nullOnDelete();
            $table->foreignId('valuation_id')->nullable()->after('target_investor_id')->constrained('portfolio_valuations')->nullOnDelete();
            $table->index(['target_investor_id', 'is_active', 'publish_date']);
            $table->index(['valuation_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('valuation_id');
            $table->dropConstrainedForeignId('target_investor_id');
        });
    }
};