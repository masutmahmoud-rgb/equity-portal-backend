<?php

namespace Database\Seeders;

use App\Models\CurrencySetting;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class TestingBaselineSeeder extends Seeder
{
    /**
     * Seed minimum data required for a clean E2E testing environment.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'System Admin',
                'password' => 'Admin@123456',
                'email_verified_at' => now(),
            ]
        );

        if (Schema::hasTable('roles')) {
            $adminRole = Role::query()->firstOrCreate([
                'name' => 'Admin',
                'guard_name' => 'web',
            ]);

            // Assign role only if the User model has role methods enabled.
            if (method_exists($admin, 'assignRole')) {
                $admin->assignRole($adminRole);
            }
        }

        if (Schema::hasTable('currency_settings')) {
            CurrencySetting::query()->updateOrCreate(
                ['id' => 1],
                [
                    'reporting_currency' => 'USD',
                ]
            );
        }

        if (Schema::hasTable('exchange_rates')) {
            $existingUsdRate = ExchangeRate::query()
                ->where('currency_code', 'USD')
                ->orderByDesc('effective_date')
                ->first();

            if ($existingUsdRate) {
                $existingUsdRate->update([
                    'exchange_rate' => 1,
                    'status' => ExchangeRate::STATUS_ACTIVE,
                ]);
            } else {
                ExchangeRate::query()->create([
                    'currency_code' => 'USD',
                    'effective_date' => now()->toDateString(),
                    'exchange_rate' => 1,
                    'status' => ExchangeRate::STATUS_ACTIVE,
                ]);
            }
        }
    }
}
