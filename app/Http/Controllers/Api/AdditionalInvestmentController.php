<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdditionalInvestment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdditionalInvestmentController extends Controller
{
    public function index(Request $request)
    {
        $query = AdditionalInvestment::query()
            ->with('investor')
            ->latest('valuation_year')
            ->latest('valuation_half')
            ->latest('created_at');

        if ($request->filled('investor_id')) {
            $query->where('investor_id', (int) $request->query('investor_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        return response()->json([
            'data' => $query->get()->map(fn (AdditionalInvestment $row) => $this->formatRow($row)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        $row = AdditionalInvestment::query()->create($validated);

        return response()->json([
            'data' => $this->formatRow($row->load('investor')),
        ], 201);
    }

    public function show(AdditionalInvestment $additional_investment)
    {
        return response()->json([
            'data' => $this->formatRow($additional_investment->load('investor')),
        ]);
    }

    public function update(Request $request, AdditionalInvestment $additional_investment)
    {
        $validated = $request->validate($this->rules(true));

        $additional_investment->update($validated);

        return response()->json([
            'data' => $this->formatRow($additional_investment->fresh('investor')),
        ]);
    }

    public function destroy(AdditionalInvestment $additional_investment)
    {
        $additional_investment->delete();

        return response()->json([
            'message' => 'Additional investment deleted successfully.',
        ]);
    }

    protected function rules(bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return [
            'investor_id' => [$required, 'integer', Rule::exists('investors', 'id')],
            'valuation_year' => [$required, 'integer', 'between:2000,2100'],
            'valuation_half' => [$required, 'string', Rule::in(AdditionalInvestment::HALVES)],
            'card_label' => ['nullable', 'string', 'max:120'],
            'investment_amount' => [$required, 'numeric', 'min:0'],
            'profit' => [$required, 'numeric'],
            'status' => [$required, 'string', Rule::in(AdditionalInvestment::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function formatRow(AdditionalInvestment $row): array
    {
        return [
            'id' => $row->id,
            'investor_id' => (int) $row->investor_id,
            'partner_id' => (int) $row->investor_id,
            'partner_name' => $row->investor?->name,
            'investor' => $row->investor ? [
                'id' => $row->investor->id,
                'name' => $row->investor->name,
            ] : null,
            'valuation_year' => (int) $row->valuation_year,
            'valuation_half' => $row->valuation_half,
            'valuation_period' => $row->valuation_period,
            'card_label' => $row->card_label,
            'investment_amount' => (float) $row->investment_amount,
            'profit' => (float) $row->profit,
            'status' => $row->status,
            'notes' => $row->notes,
            'created_at' => optional($row->created_at)->toIso8601String(),
            'updated_at' => optional($row->updated_at)->toIso8601String(),
        ];
    }
}
