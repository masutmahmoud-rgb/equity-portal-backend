<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use Illuminate\Http\Request;

class InvestmentStatementController extends Controller
{
    /**
     * List all investment transactions (Investment Statement data)
     * Supports filtering by investor_id, company_id, and status
     */
    public function index(Request $request)
    {
        $query = Investment::with(['investor', 'company']);

        // Optional filters
        if ($request->has('investor_id')) {
            $query->where('investor_id', $request->investor_id);
        }
        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json([
            'data' => $query->orderByDesc('created_at')->get(),
        ]);
    }

    /**
     * Show single investment statement with transactions and performance metrics
     */
    public function show(Investment $investment_statement)
    {
        $inv = $investment_statement;

        // Load relationships
        $inv->load(['investor', 'company', 'transactions']);

        // Calculate metrics
        $totalInvested = $inv->getCurrentBalance();
        $roi = $inv->getROI();
        $currentValue = $inv->getCurrentValue();
        $unrealizedGain = null;

        if ($currentValue !== null && $totalInvested > 0) {
            $unrealizedGain = $currentValue - $totalInvested;
        }

        // Build transactions array
        $transactionsArray = [];
        if ($inv->transactions && count($inv->transactions) > 0) {
            foreach ($inv->transactions as $transaction) {
                $transactionsArray[] = [
                    'id' => $transaction->id,
                    'transaction_type' => $transaction->transaction_type,
                    'amount' => (float)$transaction->amount,
                    'transaction_date' => $transaction->transaction_date ? $transaction->transaction_date->toDateString() : null,
                    'notes' => $transaction->notes,
                    'created_at' => $transaction->created_at->toIso8601String(),
                ];
            }
        }

        // Calculate summary
        $transactionCount = count($transactionsArray);
        $transactionTotal = 0;
        $earliestDate = null;
        $latestDate = null;

        foreach ($transactionsArray as $tx) {
            $transactionTotal += $tx['amount'];
            if ($earliestDate === null || $tx['transaction_date'] < $earliestDate) {
                $earliestDate = $tx['transaction_date'];
            }
            if ($latestDate === null || $tx['transaction_date'] > $latestDate) {
                $latestDate = $tx['transaction_date'];
            }
        }

        return response()->json([
            'data' => [
                'investment' => [
                    'id' => $inv->id,
                    'investor' => [
                        'id' => $inv->investor->id,
                        'name' => $inv->investor->name,
                        'email' => $inv->investor->email,
                    ],
                    'company' => [
                        'id' => $inv->company->id,
                        'name' => $inv->company->name,
                    ],
                    'status' => $inv->status,
                    'notes' => $inv->notes,
                    'invested_at' => $inv->invested_at ? $inv->invested_at->toIso8601String() : null,
                    'created_at' => $inv->created_at->toIso8601String(),
                    'updated_at' => $inv->updated_at->toIso8601String(),
                ],
                'performance' => [
                    'total_invested' => (float)$totalInvested,
                    'profit' => $inv->profit !== null ? (float)$inv->profit : null,
                    'indicative_value' => $currentValue,
                    'unrealized_gain' => $unrealizedGain,
                    'roi_percentage' => $roi,
                    'valuation_date' => $inv->valuation_date ? $inv->valuation_date->toDateString() : null,
                    'valuation_notes' => $inv->valuation_notes,
                ],
                'transactions' => $transactionsArray,
                'summary' => [
                    'total_transactions' => $transactionCount,
                    'transaction_total' => (float)$transactionTotal,
                    'earliest_transaction' => $earliestDate,
                    'latest_transaction' => $latestDate,
                ],
            ],
        ]);
    }
}
