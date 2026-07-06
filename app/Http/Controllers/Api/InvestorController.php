<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class InvestorController extends Controller
{
    /**
     * Display a listing of investors.
     */
    public function index()
    {
        return response()->json([
            'data' => Investor::query()->latest('created_at')->get(),
        ]);
    }

    /**
     * Store a newly created investor.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->validationRules());

        // Create the investor
        $investor = Investor::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        // Create user account for authentication
        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'data' => $investor,
            'message' => 'Investor created successfully. User account activated for portal login.',
        ], 201);
    }

    /**
     * Display the specified investor.
     */
    public function show(Investor $investor)
    {
        return response()->json([
            'data' => $investor,
        ]);
    }

    /**
     * Update the specified investor.
     */
    public function update(Request $request, Investor $investor)
    {
        $validated = $request->validate($this->validationRules($investor->id, isUpdate: true));
        $originalEmail = $investor->email;

        // Update investor data
        $investorData = [
            'name' => $validated['name'] ?? $investor->name,
            'phone' => array_key_exists('phone', $validated) ? $validated['phone'] : $investor->phone,
            'status' => $validated['status'] ?? $investor->status,
            'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $investor->notes,
        ];

        // Only update email if it changed
        if (array_key_exists('email', $validated) && $validated['email'] !== $investor->email) {
            $investorData['email'] = $validated['email'];
        }

        $investor->update($investorData);

        // Update or create user account if email or password changed
        $user = User::where('email', $originalEmail)->first();
        
        if ($user) {
            // Update existing user
            $userData = ['name' => $validated['name'] ?? $investor->name];
            
            // Update password only if provided
            if (!empty($validated['password'])) {
                $userData['password'] = Hash::make($validated['password']);
            }
            
            // Update email if it changed
            if (array_key_exists('email', $validated) && $validated['email'] !== $originalEmail) {
                $userData['email'] = $validated['email'];
            }
            
            $user->update($userData);
        } else {
            // Create new user if doesn't exist
            User::create([
                'name' => $validated['name'] ?? $investor->name,
                'email' => $validated['email'] ?? $investor->email,
                'password' => Hash::make($validated['password'] ?? 'TempPass@2026!'),
            ]);
        }

        return response()->json([
            'data' => $investor,
            'message' => 'Investor updated successfully.',
        ]);
    }

    /**
     * Remove the specified investor.
     */
    public function destroy(Investor $investor)
    {
        $dependencies = $this->investorDependencyCounts((int) $investor->id);
        $blocking = array_filter($dependencies, fn (int $count) => $count > 0);

        if (! empty($blocking)) {
            return response()->json([
                'message' => 'Cannot delete partner because related records exist. Remove dependencies first.',
                'code' => 'investor_has_dependencies',
                'dependencies' => $blocking,
            ], 409);
        }

        User::where('email', $investor->email)->delete();
        $investor->delete();

        return response()->json([
            'message' => 'Investor deleted successfully.',
        ]);
    }

    protected function investorDependencyCounts(int $investorId): array
    {
        $counts = [];

        if (Schema::hasTable('investments')) {
            $counts['investments'] = (int) \DB::table('investments')->where('investor_id', $investorId)->count();
        }

        if (Schema::hasTable('statement_of_accounts')) {
            $counts['statement_of_accounts'] = (int) \DB::table('statement_of_accounts')->where('investor_id', $investorId)->count();
        }

        if (Schema::hasTable('capital_raise_contributions')) {
            $counts['capital_raise_contributions'] = (int) \DB::table('capital_raise_contributions')->where('investor_id', $investorId)->count();
        }

        if (Schema::hasTable('ownership_register_items')) {
            $counts['ownership_register_items'] = (int) \DB::table('ownership_register_items')->where('investor_id', $investorId)->count();
        }

        if (Schema::hasTable('initial_capitalization_items')) {
            $counts['initial_capitalization_items'] = (int) \DB::table('initial_capitalization_items')->where('investor_id', $investorId)->count();
        }

        if (Schema::hasTable('portfolio_valuations')) {
            $counts['portfolio_valuations'] = (int) \DB::table('portfolio_valuations')->where('investor_id', $investorId)->count();
        }

        return $counts;
    }

    /**
     * Validation rules for investor requests.
     */
    protected function validationRules(?int $ignoreId = null, bool $isUpdate = false): array
    {
        $emailRule = [$isUpdate ? 'sometimes' : 'required', 'email', 'max:255'];
        if ($ignoreId) {
            $emailRule[] = Rule::unique('investors', 'email')->ignore($ignoreId);
        } else {
            $emailRule[] = Rule::unique('investors', 'email');
        }

        $rules = [
            'name' => ($isUpdate ? 'sometimes' : 'required') . '|string|max:255',
            'email' => $emailRule,
            'phone' => 'nullable|string|max:50',
            'status' => [$isUpdate ? 'sometimes' : 'required', 'string', Rule::in(Investor::STATUSES)],
            'notes' => 'nullable|string',
        ];

        // Password is required on creation, optional on update
        if ($isUpdate) {
            $rules['password'] = 'nullable|string|min:8|confirmed';
            $rules['password_confirmation'] = 'nullable|string';
        } else {
            $rules['password'] = 'required|string|min:8|confirmed';
            $rules['password_confirmation'] = 'required|string';
        }

        return $rules;
    }
}
