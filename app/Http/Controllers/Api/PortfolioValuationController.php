<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\PortfolioValuation;
use App\Models\InvestmentTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PortfolioValuationController extends Controller
{
    public function index(Request $request)
    {
        $query = PortfolioValuation::with(['company', 'investor'])
            ->latest('valuation_date')
            ->latest('created_at');

        if ($request->filled('company_id')) {
            $query->where('company_id', (int) $request->query('company_id'));
        }

        $partnerId = $request->query('partner_id', $request->query('investor_id'));
        if ($partnerId !== null && $partnerId !== '') {
            $query->where('investor_id', (int) $partnerId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('valuation_period')) {
            $query->where('valuation_period', $request->query('valuation_period'));
        }

        return response()->json([
            'data' => $query->get()->map(fn (PortfolioValuation $valuation) => $this->formatValuation($valuation)),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge($this->normalizePeriodFields($request->all()));
        $validated = $request->validate($this->storeRules($request), $this->validationMessages());
        $validated = $this->normalizePartnerField($validated);

        $valuation = PortfolioValuation::create($validated);
        $valuation->load(['company', 'investor']);
        $this->syncPublishedNotification($valuation);

        return response()->json([
            'data' => $this->formatValuation($valuation),
        ], 201);
    }

    public function show(PortfolioValuation $portfolio_valuation)
    {
        return response()->json([
            'data' => $this->formatValuation($portfolio_valuation->load(['company', 'investor'])),
        ]);
    }

    public function update(Request $request, PortfolioValuation $portfolio_valuation)
    {
        $request->merge($this->normalizePeriodFields($request->all()));
        $request->merge([
            'company_id' => $request->input('company_id', $portfolio_valuation->company_id),
            'investor_id' => $request->input('investor_id', $request->input('partner_id', $portfolio_valuation->investor_id)),
            'valuation_year' => $request->input('valuation_year', $portfolio_valuation->valuation_year),
            'valuation_half' => $request->input('valuation_half', $portfolio_valuation->valuation_half),
            'valuation_period' => $request->input('valuation_period', $portfolio_valuation->valuation_period),
        ]);

        $validated = $request->validate($this->updateRules($request, $portfolio_valuation), $this->validationMessages());
        $validated = $this->normalizePartnerField($validated);

        $portfolio_valuation->update($validated);
        $portfolio_valuation->load(['company', 'investor']);
        $this->syncPublishedNotification($portfolio_valuation);

        return response()->json([
            'data' => $this->formatValuation($portfolio_valuation),
        ]);
    }

    public function destroy(PortfolioValuation $portfolio_valuation)
    {
        $portfolio_valuation->delete();

        return response()->json([
            'message' => 'Portfolio valuation deleted successfully.',
        ]);
    }

    public function latestForPartner(Request $request, $investor_id)
    {
        $query = PortfolioValuation::with(['company', 'investor'])
            ->where('investor_id', (int) $investor_id)
            ->where('status', PortfolioValuation::STATUS_PUBLISHED);

        if ($request->filled('company_id')) {
            $query->where('company_id', (int) $request->query('company_id'));
        }

        $valuation = $query->latest('valuation_date')->latest('created_at')->first();

        return response()->json([
            'data' => $valuation ? $this->formatValuation($valuation) : null,
        ]);
    }

    protected function formatValuation(PortfolioValuation $valuation): array
    {
        $totalInvested = (float) InvestmentTransaction::whereHas('investment', function ($q) use ($valuation) {
            $q->where('investor_id', $valuation->investor_id)
                ->where('company_id', $valuation->company_id);
        })->sum('amount');

        $profit = (float) $valuation->profit;
        $roi = $totalInvested > 0 ? round(($profit / $totalInvested) * 100, 2) : null;

        return [
            'id' => $valuation->id,
            'partner_id' => $valuation->investor_id,
            'investor_id' => $valuation->investor_id,
            'partner_name' => $valuation->investor?->name,
            'investor_name' => $valuation->investor?->name,
            'company' => $valuation->company ? [
                'id' => $valuation->company->id,
                'name' => $valuation->company->name,
            ] : null,
            'investor' => $valuation->investor ? [
                'id' => $valuation->investor->id,
                'name' => $valuation->investor->name,
            ] : null,
            'partner' => $valuation->investor ? [
                'id' => $valuation->investor->id,
                'name' => $valuation->investor->name,
            ] : null,
            'valuation_year' => $valuation->valuation_year,
            'valuation_half' => $valuation->valuation_half,
            'valuation_period' => $valuation->valuation_period,
            'total_invested' => $totalInvested,
            'indicative_value' => (float) $valuation->indicative_value,
            'current_value' => (float) $valuation->indicative_value,
            'profit' => $profit,
            'roi_percentage' => $roi,
            'roi_period' => 'Semi-Annual',
            'valuation_date' => $valuation->valuation_date ? $valuation->valuation_date->toDateString() : null,
            'notes' => $valuation->notes,
            'status' => $valuation->status,
            'created_at' => optional($valuation->created_at)->toIso8601String(),
            'updated_at' => optional($valuation->updated_at)->toIso8601String(),
        ];
    }

    protected function storeRules(Request $request): array
    {
        $uniquePeriodRule = Rule::unique('portfolio_valuations')->where(function ($query) use ($request) {
            return $query
                ->where('company_id', $request->input('company_id'))
                ->where('valuation_year', $request->input('valuation_year'))
                ->where('valuation_half', $request->input('valuation_half'));
        });

        return [
            'company_id' => ['required', 'exists:companies,id'],
            'investor_id' => ['nullable', 'exists:investors,id', 'required_without:partner_id'],
            'partner_id' => ['nullable', 'exists:investors,id', 'required_without:investor_id'],
            'valuation_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'valuation_half' => ['required', Rule::in(PortfolioValuation::HALVES), $uniquePeriodRule],
            'valuation_period' => ['required', 'string', 'max:20'],
            'indicative_value' => ['required', 'numeric'],
            'profit' => ['required', 'numeric'],
            'valuation_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', Rule::in(PortfolioValuation::STATUSES)],
        ];
    }

    protected function updateRules(Request $request, PortfolioValuation $portfolioValuation): array
    {
        $valuationId = $portfolioValuation->id;
        $uniquePeriodRule = Rule::unique('portfolio_valuations')
            ->ignore($valuationId)
            ->where(function ($query) use ($request) {
                return $query
                    ->where('company_id', $request->input('company_id'))
                    ->where('valuation_year', $request->input('valuation_year'))
                    ->where('valuation_half', $request->input('valuation_half'));
            });

        return [
            'company_id' => ['sometimes', 'exists:companies,id'],
            'investor_id' => ['sometimes', 'exists:investors,id'],
            'partner_id' => ['sometimes', 'exists:investors,id'],
            'valuation_year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'valuation_half' => ['sometimes', Rule::in(PortfolioValuation::HALVES), $uniquePeriodRule],
            'valuation_period' => ['sometimes', 'string', 'max:20'],
            'indicative_value' => ['sometimes', 'numeric'],
            'profit' => ['sometimes', 'numeric'],
            'valuation_date' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(PortfolioValuation::STATUSES)],
        ];
    }

    protected function normalizePartnerField(array $validated): array
    {
        if (array_key_exists('partner_id', $validated) && ! array_key_exists('investor_id', $validated)) {
            $validated['investor_id'] = $validated['partner_id'];
        }

        unset($validated['partner_id']);

        return $validated;
    }

    protected function normalizePeriodFields(array $input): array
    {
        if (! array_key_exists('valuation_year', $input) || ! array_key_exists('valuation_half', $input)) {
            if (array_key_exists('valuation_period', $input) && is_string($input['valuation_period'])) {
                if (preg_match('/^(\d{4})-(H1|H2)$/', $input['valuation_period'], $matches) === 1) {
                    $input['valuation_year'] = (int) $matches[1];
                    $input['valuation_half'] = $matches[2];
                }
            }
        }

        if (array_key_exists('valuation_year', $input) && array_key_exists('valuation_half', $input)) {
            $input['valuation_period'] = sprintf('%d-%s', (int) $input['valuation_year'], strtoupper((string) $input['valuation_half']));
        }

        return $input;
    }

    protected function syncPublishedNotification(PortfolioValuation $valuation): void
    {
        $notification = Notification::where('valuation_id', $valuation->id)->first();

        if ($valuation->status !== PortfolioValuation::STATUS_PUBLISHED) {
            if ($notification) {
                $notification->update([
                    'is_active' => false,
                ]);
            }

            return;
        }

        $message = sprintf(
            '%s valuation for %s was published for %s.',
            $valuation->company?->name ?? 'Portfolio',
            $valuation->valuation_period,
            $valuation->investor?->name ?? 'the partner'
        );

        $payload = [
            'notification_type' => 'Portfolio Valuation',
            'title' => sprintf('New valuation published for %s', $valuation->company?->name ?? 'your portfolio'),
            'message' => $message,
            'important_notes' => $valuation->notes,
            'publish_date' => now()->toDateString(),
            'expiry_date' => null,
            'is_active' => true,
            'target_investor_id' => $valuation->investor_id,
            'valuation_id' => $valuation->id,
        ];

        if ($notification) {
            $notification->update($payload);
            return;
        }

        Notification::create($payload);
    }

    protected function validationMessages(): array
    {
        return [
            'valuation_half.unique' => 'A valuation already exists for this Company, Partner and Period.',
        ];
    }
}
