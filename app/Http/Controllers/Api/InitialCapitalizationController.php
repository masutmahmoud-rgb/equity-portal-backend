<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InitialCapitalization;
use App\Models\InitialCapitalizationItem;
use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Models\OwnershipRegister;
use App\Models\OwnershipRegisterItem;
use App\Models\PortfolioValuation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InitialCapitalizationController extends Controller
{
    public function index(Request $request)
    {
        $query = InitialCapitalization::with(['company', 'items.investor'])->latest('created_at');

        if ($request->filled('company_id')) {
            $query->where('company_id', (int) $request->query('company_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json([
            'data' => $query->get()->map(fn (InitialCapitalization $record) => $this->formatRecord($record)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(true));
        $this->assertTotals($validated['partners']);

        $record = DB::transaction(function () use ($validated) {
            $record = InitialCapitalization::create([
                'company_id' => (int) $validated['company_id'],
                'effective_date' => $validated['effective_date'],
                'status' => InitialCapitalization::STATUS_DRAFT,
            ]);

            foreach ($validated['partners'] as $row) {
                InitialCapitalizationItem::create([
                    'initial_capitalization_id' => $record->id,
                    'investor_id' => (int) $row['investor_id'],
                    'initial_investment' => (float) $row['initial_investment'],
                    'ownership_percentage' => (float) $row['ownership_percentage'],
                ]);
            }

            if (($validated['status'] ?? InitialCapitalization::STATUS_DRAFT) === InitialCapitalization::STATUS_PUBLISHED) {
                $this->publishOrFail($record);
            }

            return $record;
        });

        return response()->json([
            'data' => $this->formatRecord($record->load(['company', 'items.investor'])),
        ], 201);
    }

    public function show(InitialCapitalization $initial_capitalization)
    {
        return response()->json([
            'data' => $this->formatRecord($initial_capitalization->load(['company', 'items.investor'])),
        ]);
    }

    public function update(Request $request, InitialCapitalization $initial_capitalization)
    {
        if ($initial_capitalization->status === InitialCapitalization::STATUS_PUBLISHED) {
            return response()->json([
                'message' => 'Initial Capitalization is read-only after publishing.',
            ], 422);
        }

        $validated = $request->validate($this->rules(false));
        $this->assertTotals($validated['partners']);

        $initial_capitalization->update([
            'effective_date' => $validated['effective_date'],
        ]);

        DB::transaction(function () use ($initial_capitalization, $validated) {
            $initial_capitalization->items()->delete();

            foreach ($validated['partners'] as $row) {
                InitialCapitalizationItem::create([
                    'initial_capitalization_id' => $initial_capitalization->id,
                    'investor_id' => (int) $row['investor_id'],
                    'initial_investment' => (float) $row['initial_investment'],
                    'ownership_percentage' => (float) $row['ownership_percentage'],
                ]);
            }

            if (($validated['status'] ?? InitialCapitalization::STATUS_DRAFT) === InitialCapitalization::STATUS_PUBLISHED) {
                $this->publishOrFail($initial_capitalization);
            }
        });

        return response()->json([
            'data' => $this->formatRecord($initial_capitalization->fresh(['company', 'items.investor'])),
        ]);
    }

    public function destroy(InitialCapitalization $initial_capitalization)
    {
        if ($initial_capitalization->status === InitialCapitalization::STATUS_PUBLISHED) {
            return response()->json([
                'message' => 'Initial Capitalization is read-only after publishing.',
            ], 422);
        }

        $initial_capitalization->delete();

        return response()->json([
            'message' => 'Initial Capitalization deleted successfully.',
        ]);
    }

    public function publish(InitialCapitalization $initial_capitalization)
    {
        $this->publishOrFail($initial_capitalization);

        return response()->json([
            'data' => $this->formatRecord($initial_capitalization->fresh(['company', 'items.investor'])),
        ]);
    }

    protected function publishOrFail(InitialCapitalization $initial_capitalization): void
    {
        $initial_capitalization->loadMissing(['company', 'items.investor']);

        if ($initial_capitalization->status === InitialCapitalization::STATUS_PUBLISHED) {
            abort(422, 'Initial Capitalization is already published and read-only.');
        }

        $this->assertTotals($initial_capitalization->items->map(function (InitialCapitalizationItem $item) {
            return [
                'investor_id' => $item->investor_id,
                'initial_investment' => (float) $item->initial_investment,
                'ownership_percentage' => (float) $item->ownership_percentage,
            ];
        })->all());

        $valuation = PortfolioValuation::query()
            ->where('company_id', $initial_capitalization->company_id)
            ->where('status', PortfolioValuation::STATUS_PUBLISHED)
            ->latest('valuation_date')
            ->latest('created_at')
            ->first();

        if (! $valuation) {
            abort(422, 'Cannot publish Initial Capitalization without at least one published valuation for this company.');
        }

        DB::transaction(function () use ($initial_capitalization, $valuation) {
            OwnershipRegister::where('company_id', $initial_capitalization->company_id)
                ->where('portfolio_valuation_id', $valuation->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $nextVersion = (int) OwnershipRegister::where('company_id', $initial_capitalization->company_id)->max('version') + 1;

            foreach ($initial_capitalization->items as $item) {
                $investment = Investment::create([
                    'investor_id' => $item->investor_id,
                    'company_id' => $initial_capitalization->company_id,
                    'amount' => (float) $item->initial_investment,
                    'status' => Investment::STATUS_ACTIVE,
                    'notes' => 'Created from Initial Capitalization',
                    'invested_at' => $initial_capitalization->effective_date,
                ]);

                InvestmentTransaction::create([
                    'investment_id' => $investment->id,
                    'transaction_type' => InvestmentTransaction::TYPE_INITIAL_CAPITALIZATION,
                    'amount' => (float) $item->initial_investment,
                    'transaction_date' => $initial_capitalization->effective_date,
                    'notes' => 'Auto-created from Initial Capitalization',
                ]);
            }

            $snapshot = OwnershipRegister::create([
                'company_id' => $initial_capitalization->company_id,
                'portfolio_valuation_id' => $valuation->id,
                'effective_date' => $initial_capitalization->effective_date,
                'status' => OwnershipRegister::STATUS_PUBLISHED,
                'version' => $nextVersion,
                'is_current' => true,
                'published_at' => now(),
            ]);

            foreach ($initial_capitalization->items as $item) {
                OwnershipRegisterItem::create([
                    'ownership_register_id' => $snapshot->id,
                    'investor_id' => $item->investor_id,
                    'ownership_percentage' => (float) $item->ownership_percentage,
                ]);
            }

            $initial_capitalization->update([
                'status' => InitialCapitalization::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);
        });
    }

    protected function rules(bool $isCreate): array
    {
        $companyRules = ['required', 'exists:companies,id'];
        if ($isCreate) {
            $companyRules[] = Rule::unique('initial_capitalizations', 'company_id');
        }

        return [
            'company_id' => $companyRules,
            'effective_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(InitialCapitalization::STATUSES)],
            'partners' => ['required', 'array', 'min:1'],
            'partners.*.investor_id' => ['required', 'distinct', 'exists:investors,id'],
            'partners.*.initial_investment' => ['required', 'numeric', 'min:0'],
            'partners.*.ownership_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function assertTotals(array $partners): void
    {
        if (count($partners) < 1) {
            abort(422, 'At least one partner is required.');
        }

        $totalOwnership = round((float) collect($partners)->sum(function (array $row) {
            return (float) $row['ownership_percentage'];
        }), 4);

        if (abs($totalOwnership - 100.0) > 0.0001) {
            abort(422, 'Total Ownership must equal exactly 100.00%.');
        }

        $totalInvestment = round((float) collect($partners)->sum(function (array $row) {
            return (float) $row['initial_investment'];
        }), 2);

        if ($totalInvestment <= 0) {
            abort(422, 'Total Investment must be greater than zero.');
        }
    }

    protected function formatRecord(InitialCapitalization $record): array
    {
        return [
            'id' => $record->id,
            'company' => $record->company ? [
                'id' => $record->company->id,
                'name' => $record->company->name,
            ] : null,
            'effective_date' => optional($record->effective_date)->toDateString(),
            'status' => $record->status,
            'published_at' => optional($record->published_at)->toIso8601String(),
            'partners' => $record->items->map(function (InitialCapitalizationItem $item) {
                return [
                    'partner' => $item->investor ? [
                        'id' => $item->investor->id,
                        'name' => $item->investor->name,
                        'email' => $item->investor->email,
                    ] : null,
                    'initial_investment' => (float) $item->initial_investment,
                    'ownership_percentage' => (float) $item->ownership_percentage,
                ];
            })->values(),
            'total_investment' => round((float) $record->items->sum('initial_investment'), 2),
            'total_ownership' => round((float) $record->items->sum('ownership_percentage'), 4),
            'created_at' => optional($record->created_at)->toIso8601String(),
            'updated_at' => optional($record->updated_at)->toIso8601String(),
        ];
    }
}
