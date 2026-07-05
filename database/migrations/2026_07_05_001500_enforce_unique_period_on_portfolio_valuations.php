<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // Remove older duplicates and keep the latest record per company+investor+year+half.
        $groups = DB::table('portfolio_valuations')
            ->select('company_id', 'investor_id', 'valuation_year', 'valuation_half', DB::raw('COUNT(*) as total'))
            ->whereNotNull('valuation_year')
            ->whereNotNull('valuation_half')
            ->groupBy('company_id', 'investor_id', 'valuation_year', 'valuation_half')
            ->having('total', '>', 1)
            ->get();

        foreach ($groups as $group) {
            $idsToDelete = DB::table('portfolio_valuations')
                ->where('company_id', $group->company_id)
                ->where('investor_id', $group->investor_id)
                ->where('valuation_year', $group->valuation_year)
                ->where('valuation_half', $group->valuation_half)
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('id')
                ->slice(1)
                ->values();

            if ($idsToDelete->isNotEmpty()) {
                DB::table('notifications')
                    ->whereIn('valuation_id', $idsToDelete->all())
                    ->delete();

                DB::table('ownership_registers')
                    ->whereIn('portfolio_valuation_id', $idsToDelete->all())
                    ->update(['portfolio_valuation_id' => null]);

                DB::table('portfolio_valuations')
                    ->whereIn('id', $idsToDelete->all())
                    ->delete();
            }
        }

        Schema::table('portfolio_valuations', function (Blueprint $table) {
            $table->unique(
                ['company_id', 'investor_id', 'valuation_year', 'valuation_half'],
                'portfolio_valuations_company_investor_year_half_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('portfolio_valuations')) {
            return;
        }

        Schema::table('portfolio_valuations', function (Blueprint $table) {
            $table->dropUnique('portfolio_valuations_company_investor_year_half_unique');
        });
    }
};
