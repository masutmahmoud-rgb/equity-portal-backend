<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CapitalRaise;
use App\Models\CapitalRaiseContribution;
use App\Models\Company;
use App\Models\InitialCapitalization;
use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Models\Notification;
use App\Models\OwnershipRegister;
use App\Models\OwnershipRegisterItem;
use App\Models\PortfolioValuation;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CapitalRaiseController extends Controller
{
    public function index(Request $request)
    {
        $query = CapitalRaise::with(['company', 'valuation', 'contributions.investor', 'ownershipRegister'])
            ->latest('effective_date')
            ->latest('created_at');

        if ($request->filled('company_id')) {
            $query->where('company_id', (int) $request->query('company_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json([
            'data' => $query->get()->map(fn (CapitalRaise $raise) => $this->formatCapitalRaise($raise)),
        ]);
    }

    public function review(Request $request)
    {
        $payload = $request->validate($this->rules());

        $review = $this->buildReviewData(
            (int) $payload['company_id'],
            $payload['effective_date'],
            (float) $payload['raise_amount'],
            (string) $payload['participation_method'],
            $payload['contributions'] ?? []
        );

        return response()->json(['data' => $review]);
    }

    public function store(Request $request)
    {
        $payload = $request->validate($this->rules());
        $requestedStatus = $payload['status'] ?? CapitalRaise::STATUS_DRAFT;

        $review = $this->buildReviewData(
            (int) $payload['company_id'],
            $payload['effective_date'],
            (float) $payload['raise_amount'],
            (string) $payload['participation_method'],
            $payload['contributions'] ?? []
        );

        $raise = DB::transaction(function () use ($payload, $review, $request, $requestedStatus) {
            $raise = CapitalRaise::create([
                'company_id' => (int) $payload['company_id'],
                'portfolio_valuation_id' => (int) $review['valuation']['id'],
                'effective_date' => $payload['effective_date'],
                'raise_amount' => (float) $payload['raise_amount'],
                'participation_method' => (string) $payload['participation_method'],
                'status' => CapitalRaise::STATUS_DRAFT,
                'created_by' => $request->user()?->id,
            ]);

            foreach ($review['partner_contributions'] as $row) {
                CapitalRaiseContribution::create([
                    'capital_raise_id' => $raise->id,
                    'investor_id' => (int) $row['partner']['id'],
                    'contribution_amount' => (float) $row['contribution_amount'],
                    'current_ownership_percentage' => (float) $row['current_ownership_percentage'],
                    'new_ownership_percentage' => (float) $row['new_ownership_percentage'],
                ]);
            }

            if ($requestedStatus === CapitalRaise::STATUS_PUBLISHED) {
                $this->publishOrFail($raise, $request->user()?->id);
            }

            return $raise;
        });

        return response()->json([
            'data' => $this->formatCapitalRaise($raise->load(['company', 'valuation', 'contributions.investor', 'ownershipRegister'])),
        ], 201);
    }

    public function show(CapitalRaise $capital_raise)
    {
        return response()->json([
            'data' => $this->formatCapitalRaise($capital_raise->load(['company', 'valuation', 'contributions.investor', 'ownershipRegister'])),
        ]);
    }

    public function update(Request $request, CapitalRaise $capital_raise)
    {
        if ($capital_raise->status === CapitalRaise::STATUS_PUBLISHED) {
            return response()->json([
                'message' => 'Published Capital Raise is read-only.',
            ], 422);
        }

        $payload = $request->validate($this->rules());
        $requestedStatus = $payload['status'] ?? CapitalRaise::STATUS_DRAFT;

        $review = $this->buildReviewData(
            (int) $payload['company_id'],
            $payload['effective_date'],
            (float) $payload['raise_amount'],
            (string) $payload['participation_method'],
            $payload['contributions'] ?? []
        );

        DB::transaction(function () use ($capital_raise, $payload, $review, $request, $requestedStatus) {
            $capital_raise->update([
                'company_id' => (int) $payload['company_id'],
                'portfolio_valuation_id' => (int) $review['valuation']['id'],
                'effective_date' => $payload['effective_date'],
                'raise_amount' => (float) $payload['raise_amount'],
                'participation_method' => (string) $payload['participation_method'],
                'status' => CapitalRaise::STATUS_DRAFT,
                'created_by' => $capital_raise->created_by ?? $request->user()?->id,
            ]);

            $capital_raise->contributions()->delete();
            foreach ($review['partner_contributions'] as $row) {
                CapitalRaiseContribution::create([
                    'capital_raise_id' => $capital_raise->id,
                    'investor_id' => (int) $row['partner']['id'],
                    'contribution_amount' => (float) $row['contribution_amount'],
                    'current_ownership_percentage' => (float) $row['current_ownership_percentage'],
                    'new_ownership_percentage' => (float) $row['new_ownership_percentage'],
                ]);
            }

            if ($requestedStatus === CapitalRaise::STATUS_PUBLISHED) {
                $this->publishOrFail($capital_raise, $request->user()?->id);
            }
        });

        return response()->json([
            'data' => $this->formatCapitalRaise($capital_raise->fresh(['company', 'valuation', 'contributions.investor', 'ownershipRegister'])),
        ]);
    }

    public function destroy(CapitalRaise $capital_raise)
    {
        if ($capital_raise->status === CapitalRaise::STATUS_PUBLISHED) {
            return response()->json([
                'message' => 'Published Capital Raise is read-only.',
            ], 422);
        }

        $capital_raise->delete();

        return response()->json([
            'message' => 'Capital Raise deleted successfully.',
        ]);
    }

    public function publish(Request $request, CapitalRaise $capital_raise)
    {
        $this->publishOrFail($capital_raise, $request->user()?->id);

        return response()->json([
            'data' => $this->formatCapitalRaise($capital_raise->fresh(['company', 'valuation', 'contributions.investor', 'ownershipRegister'])),
        ]);
    }

    protected function publishOrFail(CapitalRaise $capitalRaise, ?int $publishedBy): void
    {
        $capitalRaise->loadMissing(['company', 'valuation', 'contributions.investor']);

        if ($capitalRaise->status === CapitalRaise::STATUS_PUBLISHED) {
            abort(422, 'Published Capital Raise is read-only.');
        }

        $valuation = $this->latestEligibleValuation($capitalRaise->company_id);
        if (! $valuation || (int) $valuation->id !== (int) $capitalRaise->portfolio_valuation_id) {
            abort(422, 'A published valuation within the last 30 days is required.');
        }

        if ($capitalRaise->contributions->isEmpty()) {
            abort(422, 'Capital Raise has no contributions to publish.');
        }

        $transactionsCreated = [];

        DB::transaction(function () use ($capitalRaise, $publishedBy, &$transactionsCreated) {
            $currentSnapshot = OwnershipRegister::query()
                ->with('items')
                ->where('company_id', $capitalRaise->company_id)
                ->where('status', OwnershipRegister::STATUS_PUBLISHED)
                ->where('is_current', true)
                ->first();

            if (! $currentSnapshot) {
                abort(422, 'Current ownership snapshot is required before publishing a Capital Raise.');
            }

            $newSnapshotVersion = (int) OwnershipRegister::where('company_id', $capitalRaise->company_id)->max('version') + 1;

            $contributionRows = $capitalRaise->contributions->keyBy('investor_id');

            foreach ($contributionRows as $investorId => $contribution) {
                if ((float) $contribution->contribution_amount <= 0) {
                    continue;
                }

                $investment = Investment::query()
                    ->where('company_id', $capitalRaise->company_id)
                    ->where('investor_id', (int) $investorId)
                    ->latest('created_at')
                    ->first();

                if (! $investment) {
                    $investment = Investment::create([
                        'investor_id' => (int) $investorId,
                        'company_id' => $capitalRaise->company_id,
                        'amount' => 0,
                        'status' => Investment::STATUS_ACTIVE,
                        'invested_at' => $capitalRaise->effective_date,
                        'notes' => 'Auto-created for Capital Raise',
                    ]);
                }

                $transaction = InvestmentTransaction::create([
                    'investment_id' => $investment->id,
                    'transaction_type' => InvestmentTransaction::TYPE_CAPITAL_RAISE,
                    'source' => 'Capital Event',
                    'status' => 'Posted',
                    'amount' => (float) $contribution->contribution_amount,
                    'transaction_date' => $capitalRaise->effective_date,
                    'notes' => 'Auto-generated from Capital Raise',
                    'is_read_only' => true,
                    'capital_raise_id' => $capitalRaise->id,
                ]);

                $transactionsCreated[] = $transaction->id;

                Notification::create([
                    'notification_type' => 'Capital Raise',
                    'title' => 'Capital Raise has been published.',
                    'message' => 'Capital Raise has been published.',
                    'important_notes' => 'Company: ' . ($capitalRaise->company?->name ?? 'N/A'),
                    'publish_date' => now()->toDateString(),
                    'expiry_date' => null,
                    'is_active' => true,
                    'target_investor_id' => (int) $investorId,
                    'valuation_id' => $capitalRaise->portfolio_valuation_id,
                ]);
            }

            OwnershipRegister::where('company_id', $capitalRaise->company_id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $snapshot = OwnershipRegister::create([
                'company_id' => $capitalRaise->company_id,
                'portfolio_valuation_id' => $capitalRaise->portfolio_valuation_id,
                'effective_date' => $capitalRaise->effective_date,
                'status' => OwnershipRegister::STATUS_PUBLISHED,
                'version' => $newSnapshotVersion,
                'is_current' => true,
                'published_at' => now(),
            ]);

            foreach ($capitalRaise->contributions as $row) {
                OwnershipRegisterItem::create([
                    'ownership_register_id' => $snapshot->id,
                    'investor_id' => $row->investor_id,
                    'ownership_percentage' => (float) $row->new_ownership_percentage,
                ]);
            }

            $capitalRaise->update([
                'status' => CapitalRaise::STATUS_PUBLISHED,
                'published_by' => $publishedBy,
                'published_at' => now(),
                'ownership_register_id' => $snapshot->id,
                'generated_transactions' => $transactionsCreated,
            ]);
        });
    }

    protected function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'effective_date' => ['required', 'date'],
            'raise_amount' => ['required', 'numeric', 'min:0.01'],
            'participation_method' => ['required', Rule::in(CapitalRaise::METHODS)],
            'status' => ['nullable', Rule::in(CapitalRaise::STATUSES)],
            'contributions' => ['nullable', 'array'],
            'contributions.*.investor_id' => ['required_with:contributions', 'distinct', 'exists:investors,id'],
            'contributions.*.contribution_amount' => ['required_with:contributions', 'numeric', 'min:0'],
        ];
    }

    protected function buildReviewData(int $companyId, string $effectiveDate, float $raiseAmount, string $method, array $customContributions): array
    {
        $company = Company::find($companyId);
        if (! $company) {
            abort(422, 'Company not found.');
        }

        $initialCapPublished = InitialCapitalization::query()
            ->where('company_id', $companyId)
            ->where('status', InitialCapitalization::STATUS_PUBLISHED)
            ->exists();

        if (! $initialCapPublished) {
            abort(422, 'Initial Capitalization must be completed before creating capital events.');
        }

        $valuation = $this->latestEligibleValuation($companyId);
        if (! $valuation) {
            abort(422, 'A published valuation within the last 30 days is required.');
        }

        $currentSnapshot = OwnershipRegister::query()
            ->with(['items.investor'])
            ->where('company_id', $companyId)
            ->where('status', OwnershipRegister::STATUS_PUBLISHED)
            ->where('is_current', true)
            ->first();

        if (! $currentSnapshot) {
            abort(422, 'Current ownership snapshot is required.');
        }

        $currentRows = $currentSnapshot->items;
        if ($currentRows->isEmpty()) {
            abort(422, 'Current ownership snapshot has no partners.');
        }

        $capitalByInvestor = $this->currentCapitalByInvestor($companyId, $currentRows->pluck('investor_id')->all());
        $newContribs = $this->resolveContributions($method, $raiseAmount, $currentRows, $customContributions);

        $newCapitalByInvestor = collect();
        foreach ($currentRows as $row) {
            $investorId = (int) $row->investor_id;
            $newCapitalByInvestor->put($investorId, (float) ($capitalByInvestor->get($investorId, 0)) + (float) $newContribs->get($investorId, 0));
        }

        $newTotalCapital = round((float) $newCapitalByInvestor->sum(), 2);
        if ($newTotalCapital <= 0) {
            abort(422, 'Unable to calculate new ownership with zero total capital.');
        }

        $partnerRows = $currentRows->map(function ($row, $index) use ($newContribs, $capitalByInvestor, $newCapitalByInvestor, $newTotalCapital, $currentRows) {
            $investorId = (int) $row->investor_id;
            $newOwnership = (($newCapitalByInvestor->get($investorId, 0.0) / $newTotalCapital) * 100);

            if ($index === $currentRows->count() - 1) {
                $sumPrev = 0.0;
                foreach ($currentRows->slice(0, -1) as $prev) {
                    $pid = (int) $prev->investor_id;
                    $sumPrev += round((($newCapitalByInvestor->get($pid, 0.0) / $newTotalCapital) * 100), 4);
                }
                $newOwnership = max(0.0, 100.0 - $sumPrev);
            }

            return [
                'partner' => [
                    'id' => $investorId,
                    'name' => $row->investor?->name,
                    'email' => $row->investor?->email,
                ],
                'contribution_amount' => round((float) $newContribs->get($investorId, 0.0), 2),
                'current_ownership_percentage' => round((float) $row->ownership_percentage, 4),
                'new_ownership_percentage' => round((float) $newOwnership, 4),
                'current_capital' => round((float) $capitalByInvestor->get($investorId, 0.0), 2),
                'new_capital' => round((float) $newCapitalByInvestor->get($investorId, 0.0), 2),
            ];
        })->values();

        return [
            'company' => ['id' => $company->id, 'name' => $company->name],
            'valuation' => [
                'id' => $valuation->id,
                'valuation_period' => $valuation->valuation_period,
                'valuation_date' => optional($valuation->valuation_date)->toDateString(),
                'created_at' => optional($valuation->created_at)->toIso8601String(),
            ],
            'effective_date' => $effectiveDate,
            'raise_amount' => round($raiseAmount, 2),
            'participation_method' => $method,
            'partner_contributions' => $partnerRows,
            'current_ownership' => $partnerRows->map(fn ($row) => ['partner' => $row['partner'], 'ownership_percentage' => $row['current_ownership_percentage']])->values(),
            'new_ownership' => $partnerRows->map(fn ($row) => ['partner' => $row['partner'], 'ownership_percentage' => $row['new_ownership_percentage']])->values(),
            'investment_transactions_to_create' => $partnerRows->filter(fn ($row) => (float) $row['contribution_amount'] > 0)->map(fn ($row) => [
                'partner' => $row['partner'],
                'transaction_type' => InvestmentTransaction::TYPE_CAPITAL_RAISE,
                'source' => 'Capital Event',
                'status' => 'Posted',
                'amount' => $row['contribution_amount'],
                'is_read_only' => true,
            ])->values(),
            'total_contributions' => round((float) $partnerRows->sum('contribution_amount'), 2),
            'ownership_total_after_raise' => round((float) $partnerRows->sum('new_ownership_percentage'), 4),
        ];
    }

    protected function resolveContributions(string $method, float $raiseAmount, Collection $currentRows, array $customContributions): Collection
    {
        $investorIds = $currentRows->pluck('investor_id')->map(fn ($id) => (int) $id)->values();

        if ($method === CapitalRaise::METHOD_PRO_RATA) {
            $contributions = collect();
            $remaining = round($raiseAmount, 2);

            foreach ($currentRows->values() as $index => $row) {
                $investorId = (int) $row->investor_id;
                if ($index === $currentRows->count() - 1) {
                    $amount = $remaining;
                } else {
                    $amount = round($raiseAmount * ((float) $row->ownership_percentage / 100), 2);
                    $remaining = round($remaining - $amount, 2);
                }
                $contributions->put($investorId, max(0, $amount));
            }

            return $contributions;
        }

        $customMap = collect($customContributions)->mapWithKeys(function ($row) {
            return [(int) $row['investor_id'] => round((float) $row['contribution_amount'], 2)];
        });

        $unknown = $customMap->keys()->diff($investorIds);
        if ($unknown->isNotEmpty()) {
            abort(422, 'Custom allocation can only include existing partners from the current ownership snapshot.');
        }

        $normalized = collect();
        foreach ($investorIds as $investorId) {
            $normalized->put($investorId, round((float) ($customMap->get($investorId, 0.0)), 2));
        }

        $total = round((float) $normalized->sum(), 2);
        if (abs($total - round($raiseAmount, 2)) > 0.009) {
            abort(422, 'Total Contributions must equal Raise Amount.');
        }

        return $normalized;
    }

    protected function currentCapitalByInvestor(int $companyId, array $investorIds): Collection
    {
        return InvestmentTransaction::query()
            ->selectRaw('investments.investor_id as investor_id, COALESCE(SUM(investment_transactions.amount), 0) as total_amount')
            ->join('investments', 'investments.id', '=', 'investment_transactions.investment_id')
            ->where('investments.company_id', $companyId)
            ->whereIn('investments.investor_id', $investorIds)
            ->groupBy('investments.investor_id')
            ->pluck('total_amount', 'investor_id')
            ->map(fn ($amount) => (float) $amount);
    }

    protected function latestEligibleValuation(int $companyId): ?PortfolioValuation
    {
        return PortfolioValuation::query()
            ->where('company_id', $companyId)
            ->where('status', PortfolioValuation::STATUS_PUBLISHED)
            ->where('created_at', '>=', now()->subDays(30))
            ->latest('valuation_date')
            ->latest('created_at')
            ->first();
    }

    protected function formatCapitalRaise(CapitalRaise $raise): array
    {
        return [
            'id' => $raise->id,
            'company' => $raise->company ? [
                'id' => $raise->company->id,
                'name' => $raise->company->name,
            ] : null,
            'valuation' => $raise->valuation ? [
                'id' => $raise->valuation->id,
                'valuation_period' => $raise->valuation->valuation_period,
                'valuation_date' => optional($raise->valuation->valuation_date)->toDateString(),
                'created_at' => optional($raise->valuation->created_at)->toIso8601String(),
            ] : null,
            'effective_date' => optional($raise->effective_date)->toDateString(),
            'raise_amount' => (float) $raise->raise_amount,
            'participation_method' => $raise->participation_method,
            'status' => $raise->status,
            'audit' => [
                'created_by' => $raise->created_by,
                'created_at' => optional($raise->created_at)->toIso8601String(),
                'published_by' => $raise->published_by,
                'published_at' => optional($raise->published_at)->toIso8601String(),
                'valuation_used' => $raise->portfolio_valuation_id,
                'generated_transactions' => $raise->generated_transactions ?? [],
                'generated_ownership_snapshot' => $raise->ownership_register_id,
            ],
            'contributions' => $raise->contributions->map(function (CapitalRaiseContribution $row) {
                return [
                    'partner' => $row->investor ? [
                        'id' => $row->investor->id,
                        'name' => $row->investor->name,
                        'email' => $row->investor->email,
                    ] : null,
                    'contribution_amount' => (float) $row->contribution_amount,
                    'current_ownership_percentage' => (float) $row->current_ownership_percentage,
                    'new_ownership_percentage' => (float) $row->new_ownership_percentage,
                ];
            })->values(),
        ];
    }
}
