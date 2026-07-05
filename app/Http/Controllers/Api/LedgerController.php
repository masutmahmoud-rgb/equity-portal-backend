<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StatementOfAccount;
use App\Models\Investor;
use Illuminate\Support\Facades\DB;

class LedgerController extends Controller
{
    /**
     * Get the financial ledger for a specific partner.
     * Dynamically computed from dividends and withdrawals.
     */
    public function partnerLedger($investor_id)
    {
        // Verify investor exists
        $investor = Investor::findOrFail($investor_id);

        // Get all transactions for this investor, sorted by date and reference
        $transactions = StatementOfAccount::where('investor_id', $investor_id)
            ->with('company')
            ->orderBy('transaction_date')
            ->orderBy('id') // secondary sort by id as reference
            ->get();

        // Compute ledger with running balance
        $ledger = [];
        $runningBalance = 0;

        foreach ($transactions as $transaction) {
            // Determine if credit or debit
            $credit = $transaction->transaction_type === 'Dividend' ? floatval($transaction->amount) : 0;
            $debit = $transaction->transaction_type === 'Withdrawal' ? floatval($transaction->amount) : 0;

            $runningBalance += $credit - $debit;

            $ledger[] = [
                'id' => $transaction->id,
                'date' => $transaction->transaction_date->format('Y-m-d'),
                'company' => $transaction->company->name ?? 'Unknown',
                'transaction_type' => $transaction->transaction_type,
                'reference' => 'REF-' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT),
                'description' => $transaction->transaction_type === 'Dividend' 
                    ? 'Dividend Payment from ' . ($transaction->company->name ?? 'Company')
                    : 'Withdrawal Request to ' . ($transaction->bank_name ?? 'Bank Account'),
                'credit' => $credit,
                'debit' => $debit,
                'running_balance' => floatval($runningBalance),
                'status' => $transaction->status,
            ];
        }

        return response()->json([
            'investor' => [
                'id' => $investor->id,
                'name' => $investor->name,
            ],
            'ledger' => $ledger,
            'summary' => [
                'total_credits' => floatval(array_sum(array_column($ledger, 'credit'))),
                'total_debits' => floatval(array_sum(array_column($ledger, 'debit'))),
                'balance' => floatval($runningBalance),
                'transaction_count' => count($ledger),
            ],
        ]);
    }

    /**
     * Get ledger for all partners (admin view).
     */
    public function allLedgers()
    {
        $investors = Investor::all();
        $allLedgers = [];

        foreach ($investors as $investor) {
            $transactions = StatementOfAccount::where('investor_id', $investor->id)
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get();

            $ledger = [];
            $runningBalance = 0;

            foreach ($transactions as $transaction) {
                $credit = $transaction->transaction_type === 'Dividend' ? floatval($transaction->amount) : 0;
                $debit = $transaction->transaction_type === 'Withdrawal' ? floatval($transaction->amount) : 0;
                $runningBalance += $credit - $debit;

                $ledger[] = [
                    'id' => $transaction->id,
                    'date' => $transaction->transaction_date->format('Y-m-d'),
                    'company' => $transaction->company->name ?? 'Unknown',
                    'transaction_type' => $transaction->transaction_type,
                    'reference' => 'REF-' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT),
                    'credit' => $credit,
                    'debit' => $debit,
                    'running_balance' => floatval($runningBalance),
                    'status' => $transaction->status,
                ];
            }

            if (count($ledger) > 0) {
                $allLedgers[] = [
                    'investor' => [
                        'id' => $investor->id,
                        'name' => $investor->name,
                    ],
                    'balance' => floatval($runningBalance),
                    'transaction_count' => count($ledger),
                    'ledger' => $ledger,
                ];
            }
        }

        return response()->json([
            'data' => $allLedgers,
        ]);
    }
}
