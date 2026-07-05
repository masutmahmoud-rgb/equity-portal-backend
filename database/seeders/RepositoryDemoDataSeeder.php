<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Investment;
use App\Models\Investor;
use App\Models\User;
use Illuminate\Database\Seeder;

class RepositoryDemoDataSeeder extends Seeder
{
    /**
     * Seed repository-safe demo data (no production/private records).
     */
    public function run(): void
    {
        $companies = [
            [
                'name' => 'Nova Foods Ltd',
                'industry' => 'Food & Beverage',
                'total_equity' => 5000000,
                'latest_valuation' => 6800000,
                'base_currency' => 'USD',
                'exchange_rate' => 1,
                'status' => Company::STATUS_OPERATING,
                'notes' => 'Repository demo company.',
            ],
            [
                'name' => 'Atlas Mobility',
                'industry' => 'Transportation',
                'total_equity' => 7200000,
                'latest_valuation' => 9050000,
                'base_currency' => 'USD',
                'exchange_rate' => 1,
                'status' => Company::STATUS_OPERATING,
                'notes' => 'Repository demo company.',
            ],
        ];

        foreach ($companies as $payload) {
            Company::query()->updateOrCreate(
                ['name' => $payload['name']],
                $payload
            );
        }

        $investors = [
            [
                'name' => 'Mahmoud Demo',
                'email' => 'mahmoud.demo@example.com',
                'phone' => '+201000000001',
                'status' => Investor::STATUS_ACTIVE,
                'notes' => 'Repository demo investor.',
            ],
            [
                'name' => 'Amina Demo',
                'email' => 'amina.demo@example.com',
                'phone' => '+201000000002',
                'status' => Investor::STATUS_ACTIVE,
                'notes' => 'Repository demo investor.',
            ],
        ];

        foreach ($investors as $payload) {
            $investor = Investor::query()->updateOrCreate(
                ['email' => $payload['email']],
                $payload
            );

            User::query()->updateOrCreate(
                ['email' => $investor->email],
                [
                    'name' => $investor->name,
                    'password' => 'TempPass@2026!',
                    'email_verified_at' => now(),
                ]
            );
        }

        $novaFoods = Company::query()->where('name', 'Nova Foods Ltd')->first();
        $atlasMobility = Company::query()->where('name', 'Atlas Mobility')->first();
        $mahmoud = Investor::query()->where('email', 'mahmoud.demo@example.com')->first();
        $amina = Investor::query()->where('email', 'amina.demo@example.com')->first();

        if ($novaFoods && $mahmoud) {
            Investment::query()->updateOrCreate(
                [
                    'investor_id' => $mahmoud->id,
                    'company_id' => $novaFoods->id,
                ],
                [
                    'amount' => 250000,
                    'status' => Investment::STATUS_ACTIVE,
                    'notes' => 'Repository demo investment.',
                    'invested_at' => now()->subMonths(8),
                ]
            );
        }

        if ($atlasMobility && $amina) {
            Investment::query()->updateOrCreate(
                [
                    'investor_id' => $amina->id,
                    'company_id' => $atlasMobility->id,
                ],
                [
                    'amount' => 320000,
                    'status' => Investment::STATUS_ACTIVE,
                    'notes' => 'Repository demo investment.',
                    'invested_at' => now()->subMonths(5),
                ]
            );
        }
    }
}
