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
        Schema::table('portfolio_valuations', function (Blueprint $table) {
            $table->unsignedSmallInteger('valuation_year')->nullable()->after('investor_id');
            $table->string('valuation_half', 2)->nullable()->after('valuation_year');
        });

        $rows = DB::table('portfolio_valuations')->select('id', 'valuation_period', 'valuation_date')->get();

        foreach ($rows as $row) {
            $year = null;
            $half = null;

            if (is_string($row->valuation_period) && preg_match('/^(\\d{4})-(H1|H2)$/', $row->valuation_period, $matches) === 1) {
                $year = (int) $matches[1];
                $half = $matches[2];
            } elseif (! empty($row->valuation_date)) {
                $parsedYear = (int) date('Y', strtotime((string) $row->valuation_date));
                $month = (int) date('n', strtotime((string) $row->valuation_date));
                $year = $parsedYear > 0 ? $parsedYear : null;
                $half = $month <= 6 ? 'H1' : 'H2';
            }

            DB::table('portfolio_valuations')
                ->where('id', $row->id)
                ->update([
                    'valuation_year' => $year,
                    'valuation_half' => $half,
                ]);
        }

        Schema::table('portfolio_valuations', function (Blueprint $table) {
            $table->index(['valuation_year', 'valuation_half']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('portfolio_valuations', function (Blueprint $table) {
            $table->dropIndex(['valuation_year', 'valuation_half']);
            $table->dropColumn(['valuation_year', 'valuation_half']);
        });
    }
};