<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dividend;
use App\Models\Investment;
use App\Models\StatementOfAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DividendController extends Controller
{
    /**
     * Display a listing of dividends.
     */
    public function index()
    {
        return response()->json([
            'data' => Dividend::with(['company', 'investment.investor'])->latest('created_at')->get(),
        ]);
    }

    /**
     * Store a newly created dividend.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->validationRules($request));
        $validated = $this->resolveInvestmentId($validated);

        $dividend = Dividend::create($validated);
        $this->syncStatementOfAccountForDividend($dividend);

        return response()->json([
            'data' => $dividend->load(['company', 'investment.investor']),
        ], 201);
    }

    /**
     * Display the specified dividend.
     */
    public function show(Dividend $dividend)
    {
        return response()->json([
            'data' => $dividend->load(['company', 'investment.investor']),
        ]);
    }

    /**
     * Update the specified dividend.
     */
    public function update(Request $request, Dividend $dividend)
    {
        $validated = $request->validate($this->validationRules($request));
        $validated = $this->resolveInvestmentId($validated, $dividend);

        $dividend->update($validated);
        $this->syncStatementOfAccountForDividend($dividend);

        return response()->json([
            'data' => $dividend->load(['company', 'investment.investor']),
        ]);
    }

    /**
     * Remove the specified dividend.
     */
    public function destroy(Dividend $dividend)
    {
        StatementOfAccount::query()
            ->where('source_dividend_id', $dividend->id)
            ->delete();

        $dividend->delete();

        return response()->json([
            'message' => 'Dividend deleted successfully.',
        ]);
    }

    protected function validationRules(Request $request): array
    {
        $required = $request->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'company_id' => [$required, 'integer', Rule::exists('companies', 'id')],
            'investment_id' => ['nullable', 'integer', Rule::exists('investments', 'id')],
            'investor_id' => ['nullable', 'integer', Rule::exists('investors', 'id')],
            'amount' => [$required, 'numeric'],
            'status' => [$required, 'string', Rule::in(Dividend::STATUSES)],
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ];
    }

    protected function resolveInvestmentId(array $validated, ?Dividend $existing = null): array
    {
        if (! empty($validated['investment_id'])) {
            return $validated;
        }

        $companyId = $validated['company_id'] ?? $existing?->company_id;
        $investorId = $validated['investor_id'] ?? $existing?->investment?->investor_id;

        if (! $companyId || ! $investorId) {
            abort(422, 'investment_id is required when investor_id is not provided.');
        }

        $investment = Investment::where('company_id', $companyId)
            ->where('investor_id', $investorId)
            ->where('status', Investment::STATUS_ACTIVE)
            ->latest('created_at')
            ->first();

        if (! $investment) {
            abort(422, 'No active investment found for the selected company and partner.');
        }

        $validated['investment_id'] = $investment->id;

        return $validated;
    }

    protected function syncStatementOfAccountForDividend(Dividend $dividend): void
    {
        $dividend->loadMissing('investment');

        $investment = $dividend->investment;
        if (! $investment) {
            return;
        }

        $transactionDate = $dividend->payment_date
            ? $dividend->payment_date->toDateTimeString()
            : now()->toDateTimeString();

        StatementOfAccount::updateOrCreate(
            ['source_dividend_id' => $dividend->id],
            [
                'company_id' => $dividend->company_id,
                'investment_id' => $dividend->investment_id,
                'investor_id' => $investment->investor_id,
                'transaction_type' => StatementOfAccount::TYPE_DIVIDEND,
                'amount' => (float) $dividend->amount,
                'status' => $dividend->status,
                'transaction_date' => $transactionDate,
                'notes' => $dividend->notes,
            ]
        );
    }
}
