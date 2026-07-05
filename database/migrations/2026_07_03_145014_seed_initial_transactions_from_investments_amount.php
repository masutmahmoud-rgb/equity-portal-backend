<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Find every investment that has a stored amount > 0 but no investment_transactions rows.
     * These are pre-transaction-system records. Seed one "Initial Investment" transaction
     * per investment so that getCurrentBalance() returns the correct historical value.
     */
    public function up(): void
    {
        $investments = DB::table('investments')
            ->leftJoin('investment_transactions', 'investment_transactions.investment_id', '=', 'investments.id')
            ->whereNull('investment_transactions.id')
            ->where('investments.amount', '>', 0)
            ->select(
                'investments.id as investment_id',
                'investments.amount',
                'investments.invested_at',
                'investments.created_at'
            )
            ->get();

        $now = now();

        foreach ($investments as $investment) {
            DB::table('investment_transactions')->insert([
                'investment_id'    => $investment->investment_id,
                'transaction_type' => 'Initial Investment',
                'amount'           => $investment->amount,
                'transaction_date' => $investment->invested_at ?? $investment->created_at,
                'notes'            => 'Migrated from investments.amount',
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }
    }

    /**
     * Remove only the migration-seeded rows (identified by their notes).
     */
    public function down(): void
    {
        DB::table('investment_transactions')
            ->where('notes', 'Migrated from investments.amount')
            ->delete();
    }
};
