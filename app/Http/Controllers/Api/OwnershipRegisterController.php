<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\OwnershipRegister;
use App\Models\OwnershipRegisterItem;
use App\Models\PortfolioValuation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OwnershipRegisterController extends Controller
{
    public function manualSet(Request $request)
    {
        $this->normalizeManualSetPayload($request);

        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'investor_id' => ['required', 'exists:investors,id'],
            'ownership_percentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,3'],
            'effective_date' => ['nullable', 'date'],
            'portfolio_valuation_id' => ['nullable', 'exists:portfolio_valuations,id'],
        ]);

        $companyId = (int) $validated['company_id'];
        $investorId = (int) $validated['investor_id'];
        $ownershipPercentage = (float) $validated['ownership_percentage'];

        $valuation = isset($validated['portfolio_valuation_id'])
            ? PortfolioValuation::query()->findOrFail((int) $validated['portfolio_valuation_id'])
            : PortfolioValuation::query()
                ->where('company_id', $companyId)
                ->latest('valuation_date')
                ->latest('created_at')
                ->first();

        if (! $valuation) {
            return response()->json([
                'message' => 'Manual ownership update requires at least one portfolio valuation for this company.',
            ], 422);
        }

        if ((int) $valuation->company_id !== $companyId) {
            return response()->json([
                'message' => 'Selected valuation does not belong to the selected company.',
            ], 422);
        }

        $register = DB::transaction(function () use ($companyId, $investorId, $ownershipPercentage, $validated, $valuation) {
            $currentRegister = OwnershipRegister::query()
                ->with('items')
                ->where('company_id', $companyId)
                ->where('portfolio_valuation_id', (int) $valuation->id)
                ->where('is_current', true)
                ->first();

            $requiredPartnerIds = $this->companyPartnerIds($companyId);
            if (empty($requiredPartnerIds)) {
                abort(422, 'Ownership update requires at least one active partner investment for this company.');
            }

            if (! in_array($investorId, $requiredPartnerIds, true)) {
                abort(422, 'Selected partner has no active investment in the selected company.');
            }

            $ownerships = [];
            if ($currentRegister) {
                foreach ($currentRegister->items as $item) {
                    $ownerships[(int) $item->investor_id] = (float) $item->ownership_percentage;
                }
            }

            // Keep exactly one ownership row per active partner in this company/period.
            $ownerships = array_intersect_key($ownerships, array_flip($requiredPartnerIds));
            foreach ($requiredPartnerIds as $requiredPartnerId) {
                if (! array_key_exists($requiredPartnerId, $ownerships)) {
                    $ownerships[$requiredPartnerId] = 0.0;
                }
            }

            $ownerships[$investorId] = $ownershipPercentage;
            $nextVersion = (int) OwnershipRegister::query()->where('company_id', $companyId)->max('version') + 1;

            OwnershipRegister::query()
                ->where('company_id', $companyId)
                ->where('portfolio_valuation_id', (int) $valuation->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $newRegister = OwnershipRegister::query()->create([
                'company_id' => $companyId,
                'portfolio_valuation_id' => (int) $valuation->id,
                'effective_date' => $validated['effective_date'] ?? now()->toDateString(),
                'status' => OwnershipRegister::STATUS_PUBLISHED,
                'version' => $nextVersion,
                'is_current' => true,
                'published_at' => now(),
            ]);

            foreach ($ownerships as $ownershipInvestorId => $percentage) {
                OwnershipRegisterItem::query()->create([
                    'ownership_register_id' => $newRegister->id,
                    'investor_id' => (int) $ownershipInvestorId,
                    'ownership_percentage' => (float) $percentage,
                ]);
            }

            return $newRegister->load(['company', 'valuation', 'items.investor']);
        });

        return response()->json([
            'message' => 'Ownership percentage updated successfully.',
            'data' => $this->formatRegister($register),
            'meta' => [
                'manual_update' => true,
                'note' => 'This quick update endpoint does not enforce total ownership to equal 100%.',
            ],
        ]);
    }

    protected function normalizeManualSetPayload(Request $request): void
    {
        $normalized = [];

        if (! $request->filled('investor_id') && $request->filled('partner_id')) {
            $normalized['investor_id'] = $request->input('partner_id');
        }

        if (! $request->filled('ownership_percentage') && $request->filled('ownership')) {
            $normalized['ownership_percentage'] = $request->input('ownership');
        }

        if (! empty($normalized)) {
            $request->merge($normalized);
        }
    }

    public function index(Request $request)
    {
        $query = OwnershipRegister::with(['company', 'valuation', 'items.investor'])
            ->latest('effective_date')
            ->latest('created_at');

        if ($request->filled('company_id')) {
            $query->where('company_id', (int) $request->query('company_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('is_current')) {
            $query->where('is_current', filter_var($request->query('is_current'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'data' => $query->get()->map(fn (OwnershipRegister $register) => $this->formatRegister($register)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());
        $this->assertCompleteOwnershipRows((int) $validated['company_id'], $validated['ownerships']);

        $requestedStatus = $validated['status'] ?? OwnershipRegister::STATUS_DRAFT;

        $valuation = PortfolioValuation::query()->findOrFail((int) $validated['portfolio_valuation_id']);
        $this->assertValuationEligibility($valuation, (int) $validated['company_id']);

        $register = DB::transaction(function () use ($validated, $requestedStatus) {
            $version = (int) OwnershipRegister::where('company_id', (int) $validated['company_id'])->max('version') + 1;

            OwnershipRegister::query()
                ->where('company_id', (int) $validated['company_id'])
                ->where('portfolio_valuation_id', (int) $validated['portfolio_valuation_id'])
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $register = OwnershipRegister::create([
                'company_id' => (int) $validated['company_id'],
                'portfolio_valuation_id' => (int) $validated['portfolio_valuation_id'],
                'effective_date' => $validated['effective_date'],
                'status' => $requestedStatus,
                'version' => $version,
                'is_current' => true,
                'published_at' => $requestedStatus === OwnershipRegister::STATUS_PUBLISHED ? now() : null,
            ]);

            foreach ($validated['ownerships'] as $ownership) {
                OwnershipRegisterItem::create([
                    'ownership_register_id' => $register->id,
                    'investor_id' => (int) $ownership['investor_id'],
                    'ownership_percentage' => (float) $ownership['ownership_percentage'],
                ]);
            }

            return $register;
        });

        return response()->json([
            'data' => $this->formatRegister($register->load(['company', 'valuation', 'items.investor'])),
        ], 201);
    }

    public function show(OwnershipRegister $ownership_register)
    {
        return response()->json([
            'data' => $this->formatRegister($ownership_register->load(['company', 'valuation', 'items.investor'])),
        ]);
    }

    public function publish(OwnershipRegister $ownership_register)
    {
        $ownership_register->loadMissing(['valuation', 'items', 'company']);

        $this->assertValuationEligibility($ownership_register->valuation, (int) $ownership_register->company_id);

        DB::transaction(function () use ($ownership_register) {
            OwnershipRegister::where('company_id', $ownership_register->company_id)
                ->where('portfolio_valuation_id', $ownership_register->portfolio_valuation_id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $ownership_register->update([
                'status' => OwnershipRegister::STATUS_PUBLISHED,
                'is_current' => true,
                'published_at' => now(),
            ]);
        });

        return response()->json([
            'data' => $this->formatRegister($ownership_register->fresh(['company', 'valuation', 'items.investor'])),
        ]);
    }

    protected function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'portfolio_valuation_id' => ['required', 'exists:portfolio_valuations,id'],
            'effective_date' => ['required', 'date'],
            'status' => ['nullable', Rule::in(OwnershipRegister::STATUSES)],
            'ownerships' => ['required', 'array', 'min:1'],
            'ownerships.*.investor_id' => ['required', 'distinct', 'exists:investors,id'],
            'ownerships.*.ownership_percentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,3'],
        ];
    }

    protected function assertValuationEligibility(PortfolioValuation $valuation, int $companyId): void
    {
        if ((int) $valuation->company_id !== $companyId) {
            abort(422, 'Selected valuation does not belong to the selected company.');
        }
    }

    public function update(Request $request, OwnershipRegister $ownership_register)
    {
        $validated = $request->validate([
            'ownerships' => ['required', 'array', 'min:1'],
            'ownerships.*.investor_id' => ['required', 'distinct', 'exists:investors,id'],
            'ownerships.*.ownership_percentage' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,3'],
        ]);

        $ownership_register->loadMissing(['items']);
        $validated['ownerships'] = $this->completeOwnershipRowsForUpdate(
            (int) $ownership_register->company_id,
            $ownership_register->items,
            $validated['ownerships']
        );

        DB::transaction(function () use ($ownership_register, $validated) {
            // Delete existing items for this register
            $ownership_register->items()->delete();

            // Insert updated item percentages
            foreach ($validated['ownerships'] as $ownership) {
                OwnershipRegisterItem::create([
                    'ownership_register_id' => $ownership_register->id,
                    'investor_id' => (int) $ownership['investor_id'],
                    'ownership_percentage' => (float) $ownership['ownership_percentage'],
                ]);
            }
        });

        return response()->json([
            'data' => $this->formatRegister($ownership_register->fresh(['company', 'valuation', 'items.investor'])),
        ]);
    }

    protected function completeOwnershipRowsForUpdate(int $companyId, $existingItems, array $inputRows): array
    {
        $requiredPartnerIds = $this->companyPartnerIds($companyId);

        if (empty($requiredPartnerIds)) {
            abort(422, 'Ownership register requires at least one active partner investment for this company.');
        }

        $ownerships = collect($existingItems)
            ->mapWithKeys(function (OwnershipRegisterItem $item) {
                return [(int) $item->investor_id => (float) $item->ownership_percentage];
            });

        foreach ($inputRows as $row) {
            $ownerships[(int) $row['investor_id']] = (float) $row['ownership_percentage'];
        }

        foreach ($requiredPartnerIds as $requiredPartnerId) {
            if (! $ownerships->has($requiredPartnerId)) {
                $ownerships[$requiredPartnerId] = 0.0;
            }
        }

        $extra = $ownerships->keys()->diff($requiredPartnerIds)->values()->all();
        if (! empty($extra)) {
            abort(422, sprintf(
                'Ownership rows contain unexpected partner IDs: [%s].',
                implode(', ', $extra)
            ));
        }

        return $ownerships
            ->map(fn ($percentage, $investorId) => [
                'investor_id' => (int) $investorId,
                'ownership_percentage' => round((float) $percentage, 3),
            ])
            ->sortBy('investor_id')
            ->values()
            ->all();
    }

    protected function assertCompleteOwnershipRows(int $companyId, array $ownershipRows): void
    {
        $requiredPartnerIds = $this->companyPartnerIds($companyId);
        if (empty($requiredPartnerIds)) {
            abort(422, 'Ownership register requires at least one active partner investment for this company.');
        }

        $providedPartnerIds = collect($ownershipRows)
            ->pluck('investor_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $missing = array_values(array_diff($requiredPartnerIds, $providedPartnerIds));
        $extra = array_values(array_diff($providedPartnerIds, $requiredPartnerIds));

        if (! empty($missing) || ! empty($extra)) {
            abort(422, sprintf(
                'Ownership rows must include exactly one record for each active company partner. Missing partner IDs: [%s]. Unexpected partner IDs: [%s].',
                implode(', ', $missing),
                implode(', ', $extra)
            ));
        }
    }

    protected function companyPartnerIds(int $companyId): array
    {
        return Investment::query()
            ->where('company_id', $companyId)
            ->where('status', Investment::STATUS_ACTIVE)
            ->distinct()
            ->pluck('investor_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();
    }

    protected function formatRegister(OwnershipRegister $register): array
    {
        return [
            'id' => $register->id,
            'company' => $register->company ? [
                'id' => $register->company->id,
                'name' => $register->company->name,
            ] : null,
            'valuation' => $register->valuation ? [
                'id' => $register->valuation->id,
                'valuation_period' => $register->valuation->valuation_period,
                'status' => $register->valuation->status,
                'created_at' => optional($register->valuation->created_at)->toIso8601String(),
            ] : null,
            'effective_date' => optional($register->effective_date)->toDateString(),
            'status' => $register->status,
            'version' => $register->version,
            'is_current' => (bool) $register->is_current,
            'published_at' => optional($register->published_at)->toIso8601String(),
            'ownerships' => $register->items->map(function (OwnershipRegisterItem $item) {
                return [
                    'partner' => $item->investor ? [
                        'id' => $item->investor->id,
                        'name' => $item->investor->name,
                        'email' => $item->investor->email,
                    ] : null,
                    'ownership_percentage' => round((float) $item->ownership_percentage, 3),
                ];
            })->values(),
            'total_ownership' => round((float) $register->items->sum('ownership_percentage'), 3),
            'created_at' => optional($register->created_at)->toIso8601String(),
            'updated_at' => optional($register->updated_at)->toIso8601String(),
        ];
    }
}
