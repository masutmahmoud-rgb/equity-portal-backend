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
        if (! Schema::hasTable('portfolio_valuations')) {
            return;
        }

        try {
            Schema::table('portfolio_valuations', function (Blueprint $table) {
                $table->dropUnique('portfolio_valuations_company_year_half_unique');
            });
        } catch (\Throwable $e) {
            // Index may not exist in all environments.
        }

        try {
            Schema::table('portfolio_valuations', function (Blueprint $table) {
                $table->dropUnique('portfolio_valuations_company_investor_year_half_unique');
            });
        } catch (\Throwable $e) {
            // Index may already be absent; re-create below.
        }

        try {
            Schema::table('portfolio_valuations', function (Blueprint $table) {
            $table->unique(
                ['company_id', 'investor_id', 'valuation_year', 'valuation_half'],
                'portfolio_valuations_company_investor_year_half_unique'
            );
            });
        } catch (\Throwable $e) {
            // Unique index may already exist.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('portfolio_valuations')) {
            return;
        }

        try {
            Schema::table('portfolio_valuations', function (Blueprint $table) {
                $table->dropUnique('portfolio_valuations_company_investor_year_half_unique');
            });
        } catch (\Throwable $e) {
            // Index may not exist in all environments.
        }

        try {
            Schema::table('portfolio_valuations', function (Blueprint $table) {
            $table->unique(
                ['company_id', 'valuation_year', 'valuation_half'],
                'portfolio_valuations_company_year_half_unique'
            );
            });
        } catch (\Throwable $e) {
            // Unique index may already exist.
        }
    }
};
