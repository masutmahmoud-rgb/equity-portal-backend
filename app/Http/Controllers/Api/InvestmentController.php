<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\InvestmentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvestmentController extends Controller
{
    /**
     * Display a listing of investments.
     */
    public function index()
    {
        return response()->json([
            'data' => Investment::with(['investor', 'company'])->latest('created_at')->get(),
        ]);
    }

    /**
     * Store a newly created investment.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->storeValidationRules());

        $investment = DB::transaction(function () use ($validated) {
            $amount = (float) $validated['amount'];
            $investedAt = $validated['invested_at'] ?? now()->toDateString();

            $investment = Investment::create([
                'investor_id' => (int) $validated['investor_id'],
                'company_id' => (int) $validated['company_id'],
                'amount' => $amount,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'invested_at' => $validated['invested_at'] ?? null,
                'indicative_value' => $validated['indicative_value'] ?? null,
                'profit' => $validated['profit'] ?? null,
                'valuation_date' => $validated['valuation_date'] ?? null,
                'valuation_notes' => $validated['valuation_notes'] ?? null,
            ]);

            // Every new investment starts with an initial transaction.
            InvestmentTransaction::create([
                'investment_id' => $investment->id,
                'transaction_type' => InvestmentTransaction::TYPE_INITIAL,
                'source' => 'Manual',
                'status' => 'Posted',
                'amount' => $amount,
                'transaction_date' => $investedAt,
                'notes' => 'Auto-created from investment creation',
                'is_read_only' => false,
            ]);

            return $investment;
        });

        return response()->json([
            'data' => $investment->load(['investor', 'company']),
        ], 201);
    }

    /**
     * Display the specified investment.
     */
    public function show(Investment $investment)
    {
        return response()->json([
            'data' => $investment->load(['investor', 'company']),
        ]);
    }

    /**
     * Update the specified investment.
     */
    public function update(Request $request, Investment $investment)
    {
        $validated = $request->validate($this->updateValidationRules());

        $investment->update($validated);

        return response()->json([
            'data' => $investment->load(['investor', 'company']),
        ]);
    }

    /**
     * Remove the specified investment.
     */
    public function destroy(Investment $investment)
    {
        $dependencies = $this->investmentDependencyCounts((int) $investment->id);
        $blocking = array_filter($dependencies, fn (int $count) => $count > 0);

        if (! empty($blocking)) {
            return response()->json([
                'message' => 'Cannot delete investment because related records exist. Remove dependencies first.',
                'code' => 'investment_has_dependencies',
                'dependencies' => $blocking,
            ], 409);
        }

        $investment->delete();

        return response()->json([
            'message' => 'Investment deleted successfully.',
        ]);
    }

    protected function investmentDependencyCounts(int $investmentId): array
    {
        $counts = [];

        if (Schema::hasTable('investment_transactions')) {
            $counts['investment_transactions'] = (int) \DB::table('investment_transactions')
                ->where('investment_id', $investmentId)
                ->count();
        }

        if (Schema::hasTable('statement_of_accounts')) {
            $counts['statement_of_accounts'] = (int) \DB::table('statement_of_accounts')
                ->where('investment_id', $investmentId)
                ->count();
        }

        if (Schema::hasTable('dividends')) {
            $counts['dividends'] = (int) \DB::table('dividends')
                ->where('investment_id', $investmentId)
                ->count();
        }

        return $counts;
    }

    /**
     * Validation rules for investment requests.
     */
    protected function storeValidationRules(): array
    {
        return [
            'investor_id' => ['required', 'integer', Rule::exists('investors', 'id')],
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'amount' => 'required|numeric',
            'status' => ['required', 'string', Rule::in(Investment::STATUSES)],
            'notes' => 'nullable|string',
            'invested_at' => 'nullable|date',
            'indicative_value' => 'nullable|numeric|min:0',
            'profit' => 'nullable|numeric',
            'valuation_date' => 'nullable|date',
            'valuation_notes' => 'nullable|string',
        ];
    }

    protected function updateValidationRules(): array
    {
        return [
            'investor_id' => ['sometimes', 'integer', Rule::exists('investors', 'id')],
            'company_id' => ['sometimes', 'integer', Rule::exists('companies', 'id')],
            'status' => ['sometimes', 'string', Rule::in(Investment::STATUSES)],
            'notes' => 'nullable|string',
            'invested_at' => 'nullable|date',
            'indicative_value' => 'nullable|numeric|min:0',
            'profit' => 'nullable|numeric',
            'valuation_date' => 'nullable|date',
            'valuation_notes' => 'nullable|string',
        ];
    }
}
