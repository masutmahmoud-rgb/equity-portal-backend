<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialData;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinancialDataController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = FinancialData::query()->latest('year')->latest('half_year')->latest('created_at');

        if (request()->filled('type')) {
            $query->where('type', request('type'));
        }

        if (request()->filled('year')) {
            $query->where('year', (int) request('year'));
        }

        if (request()->filled('half_year')) {
            $query->where('half_year', request('half_year'));
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->storeRules());

        $record = FinancialData::create($validated);

        return response()->json([
            'data' => $record,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(FinancialData $financial_data)
    {
        return response()->json([
            'data' => $financial_data,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FinancialData $financial_data)
    {
        $validated = $request->validate($this->updateRules());

        $financial_data->update($validated);

        return response()->json([
            'data' => $financial_data,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FinancialData $financial_data)
    {
        $financial_data->delete();

        return response()->json([
            'message' => 'Financial data deleted successfully.',
        ]);
    }

    protected function storeRules(): array
    {
        return [
            'type' => ['required', Rule::in(FinancialData::TYPES)],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'half_year' => ['required', Rule::in(FinancialData::HALF_YEARS)],
            'amount' => ['required', 'numeric'],
            'currency' => ['required', 'string', 'max:10'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(FinancialData::TYPES)],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'half_year' => ['sometimes', Rule::in(FinancialData::HALF_YEARS)],
            'amount' => ['sometimes', 'numeric'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
