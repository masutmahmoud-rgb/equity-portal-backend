<?php

use App\Models\Company;
use App\Models\Dividend;
use App\Models\StatementOfAccount;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        StatementOfAccount::query()
            ->where('transaction_type', StatementOfAccount::TYPE_DIVIDEND)
            ->whereNotNull('source_dividend_id')
            ->orderBy('id')
            ->chunkById(200, function ($statements): void {
                foreach ($statements as $statement) {
                    $dividend = Dividend::query()->find($statement->source_dividend_id);
                    if (! $dividend) {
                        continue;
                    }

                    $company = Company::query()->find($statement->company_id);
                    $companyCurrency = strtoupper((string) ($company?->base_currency ?? 'EGP'));

                    $sourceCurrency = strtoupper((string) ($dividend->original_currency ?? $companyCurrency));
                    $exchangeRate = (float) ($dividend->exchange_rate ?? 1.0);

                    if ($sourceCurrency === 'EGP') {
                        $exchangeRate = 1.0;
                    }

                    if ($exchangeRate <= 0) {
                        $exchangeRate = 1.0;
                    }

                    $originalAmount = round((float) $dividend->amount, 2);
                    $statementAmount = round($originalAmount * $exchangeRate, 2);

                    $statement->forceFill([
                        'amount' => $statementAmount,
                        'original_amount' => $originalAmount,
                        'original_currency' => $sourceCurrency,
                        'exchange_rate' => round($exchangeRate, 6),
                    ])->save();
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: this backfill intentionally does not attempt to restore old values.
    }
};
