<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CurrencySetting;
use Illuminate\Http\Request;

class CurrencySettingController extends Controller
{
    public function index()
    {
        $setting = CurrencySetting::query()->first();

        if (! $setting) {
            $setting = CurrencySetting::create([
                'reporting_currency' => 'USD',
            ]);
        }

        return response()->json([
            'data' => $setting,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'reporting_currency' => ['required', 'string', 'max:10'],
        ]);

        $setting = CurrencySetting::query()->first();

        if (! $setting) {
            $setting = CurrencySetting::create($validated);
        } else {
            $setting->update($validated);
        }

        return response()->json([
            'data' => $setting,
        ]);
    }
}
