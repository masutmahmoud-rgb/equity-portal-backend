<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statement_of_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('statement_of_accounts', 'source_dividend_id')) {
                $table->unsignedBigInteger('source_dividend_id')->nullable()->after('investor_id');
                $table->unique('source_dividend_id');
            }
        });

        if (! Schema::hasTable('dividends')) {
            return;
        }

        $dividends = DB::table('dividends')
            ->select('id', 'company_id', 'investment_id', 'amount', 'status', 'payment_date', 'notes', 'created_at')
            ->orderBy('id')
            ->get();

        foreach ($dividends as $dividend) {
            $investment = DB::table('investments')
                ->select('investor_id')
                ->where('id', $dividend->investment_id)
                ->first();

            if (! $investment) {
                continue;
            }

            $exists = DB::table('statement_of_accounts')
                ->where('source_dividend_id', $dividend->id)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('statement_of_accounts')->insert([
                'company_id' => $dividend->company_id,
                'investment_id' => $dividend->investment_id,
                'investor_id' => $investment->investor_id,
                'source_dividend_id' => $dividend->id,
                'transaction_type' => 'Dividend',
                'amount' => $dividend->amount,
                'status' => $dividend->status,
                'transaction_date' => $dividend->payment_date ?? $dividend->created_at,
                'notes' => $dividend->notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('statement_of_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('statement_of_accounts', 'source_dividend_id')) {
                $table->dropUnique('statement_of_accounts_source_dividend_id_unique');
                $table->dropColumn('source_dividend_id');
            }
        });
    }
};
