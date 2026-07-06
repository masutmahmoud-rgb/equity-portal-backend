<?php

namespace App\Http\Controllers\Api;

use App\Models\Investor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController
{
    /**
     * Verify user credentials for login (UAT testing)
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $investor = Investor::resolveLinkedByEmail((string) $user->email);

        return response()->json([
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'partner' => $investor ? [
                    'id' => $investor->id,
                    'name' => $investor->name,
                    'email' => $investor->email,
                    'status' => $investor->status,
                ] : null,
            ],
        ], 200);
    }

    /**
     * Verify credentials only (for UAT testing)
     */
    public function verifyCredentials(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
                'data' => [
                    'valid' => false,
                ],
            ], 404);
        }

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid password',
                'data' => [
                    'valid' => false,
                ],
            ], 401);
        }

        $investor = Investor::resolveLinkedByEmail((string) $user->email);

        if (! $investor) {
            return response()->json([
                'message' => 'Credentials valid, but no linked partner profile found for this email.',
                'data' => [
                    'valid' => false,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                    'partner' => null,
                ],
            ], 409);
        }

        return response()->json([
            'message' => 'Credentials verified successfully',
            'data' => [
                'valid' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'partner' => [
                    'id' => $investor->id,
                    'name' => $investor->name,
                    'email' => $investor->email,
                    'status' => $investor->status,
                ],
            ],
        ], 200);
    }
}
