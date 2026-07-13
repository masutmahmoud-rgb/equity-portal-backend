<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Dividend;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\StatementOfAccount;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DividendController extends Controller
{
    /**
     * Display a listing of dividends.
     */
    public function index(Request $request)
    {
        $query = Dividend::with(['company', 'investment.investor'])
            ->latest('created_at');

        if ($request->filled('company_id')) {
            $query->where('company_id', (int) $request->query('company_id'));
        }

        $requestedInvestorId = $request->filled('investor_id')
            ? (int) $request->query('investor_id')
            : null;

        if ($requestedInvestorId) {
            $query->whereHas('investment', function ($subQuery) use ($requestedInvestorId) {
                $subQuery->where('investor_id', $requestedInvestorId);
            });
        } else {
            $user = $request->user();
            if ($user && ! empty($user->email)) {
                $linkedInvestor = Investor::resolveLinkedByEmail((string) $user->email);

                if ($linkedInvestor) {
                    $query->whereHas('investment', function ($subQuery) use ($linkedInvestor) {
                        $subQuery->where('investor_id', (int) $linkedInvestor->id);
                    });
                }
            }
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    /**
     * Store a newly created dividend.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->validationRules($request));
        $validated = $this->resolveInvestmentId($validated);
        $validated = $this->normalizeDividendCurrency($validated);

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
        $validated = $this->normalizeDividendCurrency($validated, $dividend);

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
            'original_amount' => ['nullable', 'numeric', 'min:0.01'],
            'original_currency' => ['nullable', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.000001'],
            'status' => [$required, 'string', Rule::in(Dividend::STATUSES)],
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ];
    }

    protected function normalizeDividendCurrency(array $validated, ?Dividend $existing = null): array
    {
        $companyId = (int) ($validated['company_id'] ?? $existing?->company_id ?? 0);

        if ($companyId <= 0) {
            abort(422, 'company_id is required.');
        }

        $company = Company::query()->find($companyId);
        if (! $company) {
            abort(422, 'Selected company was not found.');
        }

        $companyCurrency = strtoupper((string) ($company->base_currency ?? 'EGP'));

        $inputCurrency = strtoupper((string) (
            $validated['original_currency']
            ?? $existing?->original_currency
            ?? $companyCurrency
        ));

        if ($inputCurrency !== $companyCurrency) {
            abort(422, "Dividend currency must match company currency ({$companyCurrency}).");
        }

        $exchangeRate = isset($validated['exchange_rate'])
            ? (float) $validated['exchange_rate']
            : (float) ($existing?->exchange_rate ?? 1.0);

        if ($companyCurrency !== 'EGP' && ! isset($validated['exchange_rate']) && ! $existing?->exchange_rate) {
            abort(422, 'Exchange rate is required for non-EGP dividends.');
        }

        if ($companyCurrency === 'EGP') {
            if (isset($validated['exchange_rate']) && abs((float) $validated['exchange_rate'] - 1.0) > 0.000001) {
                abort(422, 'Company base currency is EGP, so exchange rate must be 1. Please verify company currency settings.');
            }
            $exchangeRate = 1.0;
        }

        if ($exchangeRate <= 0) {
            abort(422, 'Exchange rate must be greater than zero.');
        }

        if (isset($validated['original_amount'])) {
            $companyAmount = (float) $validated['original_amount'];
        } elseif (isset($validated['amount'])) {
            $rawAmount = (float) $validated['amount'];

            // If original amount is missing but exchange rate is provided for non-EGP,
            // interpret the incoming amount as EGP and back-calculate company currency.
            if ($companyCurrency !== 'EGP' && isset($validated['exchange_rate']) && $exchangeRate > 0) {
                $companyAmount = round($rawAmount / $exchangeRate, 2);
            } else {
                $companyAmount = $rawAmount;
            }
        } else {
            $companyAmount = (float) ($existing?->amount ?? 0);
        }

        if ($companyAmount <= 0) {
            abort(422, 'Dividend amount must be greater than zero.');
        }

        $validated['amount'] = round($companyAmount, 2);
        $validated['original_currency'] = $companyCurrency;
        $validated['exchange_rate'] = round($exchangeRate, 6);

        unset($validated['original_amount']);

        return $validated;
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
        $dividend->loadMissing(['investment', 'company']);

        $investment = $dividend->investment;
        if (! $investment) {
            return;
        }

        $sourceCurrency = strtoupper((string) ($dividend->original_currency ?: ($dividend->company?->base_currency ?? 'EGP')));
        $exchangeRate = (float) ($dividend->exchange_rate ?? 1.0);

        if ($sourceCurrency === 'EGP') {
            $exchangeRate = 1.0;
        }

        if ($exchangeRate <= 0) {
            $exchangeRate = 1.0;
        }

        $originalAmount = (float) $dividend->amount;
        $statementAmount = round($originalAmount * $exchangeRate, 2);

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
                'amount' => $statementAmount,
                'original_amount' => round($originalAmount, 2),
                'original_currency' => $sourceCurrency,
                'exchange_rate' => round($exchangeRate, 6),
                'status' => $dividend->status,
                'transaction_date' => $transactionDate,
                'notes' => $dividend->notes,
            ]
        );
    }
}
