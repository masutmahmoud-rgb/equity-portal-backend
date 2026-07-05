<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExchangeRateController extends Controller
{
    public function index(Request $request)
    {
        $query = ExchangeRate::query()
            ->latest('effective_date')
            ->latest('created_at');

        if ($request->filled('currency_code')) {
            $query->where('currency_code', strtoupper((string) $request->query('currency_code')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->storeRules());
        $validated['currency_code'] = strtoupper((string) $validated['currency_code']);

        $rate = ExchangeRate::create($validated);

        return response()->json([
            'data' => $rate,
        ], 201);
    }

    public function show(ExchangeRate $exchange_rate)
    {
        return response()->json([
            'data' => $exchange_rate,
        ]);
    }

    public function update(Request $request, ExchangeRate $exchange_rate)
    {
        $validated = $request->validate($this->updateRules($exchange_rate->id));

        if (array_key_exists('currency_code', $validated)) {
            $validated['currency_code'] = strtoupper((string) $validated['currency_code']);
        }

        $exchange_rate->update($validated);

        return response()->json([
            'data' => $exchange_rate,
        ]);
    }

    public function destroy(ExchangeRate $exchange_rate)
    {
        $exchange_rate->delete();

        return response()->json([
            'message' => 'Exchange rate deleted successfully.',
        ]);
    }

    protected function storeRules(): array
    {
        return [
            'currency_code' => ['required', 'string', 'max:10'],
            'exchange_rate' => ['required', 'numeric', 'min:0.000001'],
            'effective_date' => ['required', 'date'],
            'status' => ['required', Rule::in(ExchangeRate::STATUSES)],
        ];
    }

    protected function updateRules(int $exchangeRateId): array
    {
        return [
            'currency_code' => ['sometimes', 'string', 'max:10'],
            'exchange_rate' => ['sometimes', 'numeric', 'min:0.000001'],
            'effective_date' => ['sometimes', 'date'],
            'status' => ['sometimes', Rule::in(ExchangeRate::STATUSES)],
        ];
    }
}
