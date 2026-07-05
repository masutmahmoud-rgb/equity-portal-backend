<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    /**
     * Display all companies.
     */
    public function index()
    {
        return response()->json([
            'data' => Company::query()->latest('created_at')->get(),
        ]);
    }

    /**
     * Store a new company.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->validationRules());
        $validated['base_currency'] = strtoupper(trim((string) $validated['base_currency']));

        $company = Company::create($validated);

        return response()->json([
            'data' => $company,
        ], 201);
    }

    /**
     * Display one company.
     */
    public function show(Company $company)
    {
        return response()->json([
            'data' => $company,
        ]);
    }

    /**
     * Update company.
     */
    public function update(Request $request, Company $company)
    {
        $validated = $request->validate($this->validationRules($request));

        if (array_key_exists('base_currency', $validated)) {
            $validated['base_currency'] = strtoupper(trim((string) $validated['base_currency']));
        }

        $company->update($validated);

        return response()->json([
            'data' => $company,
        ]);
    }

    /**
     * Delete company.
     */
    public function destroy(Company $company)
    {
        try {
            DB::transaction(function () use ($company) {
                $companyId = (int) $company->id;

                if (Schema::hasTable('statement_of_accounts')) {
                    DB::table('statement_of_accounts')->where('company_id', $companyId)->delete();
                }

                if (Schema::hasTable('capital_raises')) {
                    $capitalRaiseIds = DB::table('capital_raises')
                        ->where('company_id', $companyId)
                        ->pluck('id');

                    if ($capitalRaiseIds->isNotEmpty() && Schema::hasTable('capital_raise_contributions')) {
                        DB::table('capital_raise_contributions')
                            ->whereIn('capital_raise_id', $capitalRaiseIds)
                            ->delete();
                    }

                    DB::table('capital_raises')->where('company_id', $companyId)->delete();
                }

                if (Schema::hasTable('ownership_registers')) {
                    $ownershipRegisterIds = DB::table('ownership_registers')
                        ->where('company_id', $companyId)
                        ->pluck('id');

                    if ($ownershipRegisterIds->isNotEmpty() && Schema::hasTable('ownership_register_items')) {
                        DB::table('ownership_register_items')
                            ->whereIn('ownership_register_id', $ownershipRegisterIds)
                            ->delete();
                    }

                    DB::table('ownership_registers')->where('company_id', $companyId)->delete();
                }

                if (Schema::hasTable('initial_capitalizations')) {
                    $initialCapitalizationIds = DB::table('initial_capitalizations')
                        ->where('company_id', $companyId)
                        ->pluck('id');

                    if ($initialCapitalizationIds->isNotEmpty() && Schema::hasTable('initial_capitalization_items')) {
                        DB::table('initial_capitalization_items')
                            ->whereIn('initial_capitalization_id', $initialCapitalizationIds)
                            ->delete();
                    }

                    DB::table('initial_capitalizations')->where('company_id', $companyId)->delete();
                }

                if (Schema::hasTable('portfolio_valuations')) {
                    DB::table('portfolio_valuations')->where('company_id', $companyId)->delete();
                }

                if (Schema::hasTable('investments')) {
                    DB::table('investments')->where('company_id', $companyId)->delete();
                }

                $company->delete();
            });
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Unable to delete company due to related records. Please remove dependent records and try again.',
                'error' => $e->getCode(),
            ], 409);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Unexpected error while deleting company.',
            ], 500);
        }

        return response()->json([
            'message' => 'Company deleted successfully.',
        ]);
    }

    /**
     * Validation rules for company requests.
     */
    protected function validationRules(Request $request): array
    {
        $required = $request->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'industry' => 'nullable|string|max:255',
            'total_equity' => [$required, 'numeric'],
            'latest_valuation' => [$required, 'numeric'],
            'base_currency' => [$required, 'string', 'min:3', 'max:10'],
            'exchange_rate' => 'nullable|numeric|min:0.000001',
            'status' => [$required, 'string', Rule::in(Company::STATUSES)],
            'notes' => 'nullable|string',
        ];
    }
}