<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CurrencySetting;
use App\Models\ExchangeRate;
use App\Models\Investor;
use App\Models\PortfolioValuation;
use App\Models\StatementOfAccount;
use Illuminate\Http\Request;

class PartnerRetainedEarningsController extends Controller
{
    /**
     * Read-only retained earnings ledger for the authenticated partner.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $investor = Investor::resolveLinkedByEmail((string) $user->email);

        if (! $investor) {
            return response()->json([
                'message' => 'Authenticated user is not linked to a partner profile.',
            ], 403);
        }

        $reportingCurrency = strtoupper((string) (CurrencySetting::query()->value('reporting_currency') ?? 'USD'));

        $activeRatesByCode = ExchangeRate::active()
            ->whereDate('effective_date', '<=', now()->toDateString())
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy(function (ExchangeRate $rate) {
                return strtoupper((string) $rate->currency_code);
            })
            ->map(function ($rates) {
                return (float) $rates->first()->exchange_rate;
            });

        $resolveRate = function (?string $currencyCode) use ($activeRatesByCode, $reportingCurrency): float {
            $source = strtoupper((string) ($currencyCode ?? 'USD'));

            if ($source === $reportingCurrency) {
                return 1.0;
            }

            return (float) ($activeRatesByCode->get($source) ?? 1.0);
        };

        $credits = PortfolioValuation::query()
            ->with('company')
            ->where('investor_id', $investor->id)
            ->where('status', PortfolioValuation::STATUS_PUBLISHED)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->unique(function (PortfolioValuation $valuation) {
                return $valuation->company_id . '|' . ($valuation->valuation_period ?? '');
            })
            ->map(function (PortfolioValuation $valuation) use ($resolveRate) {
                $amount = (float) ($valuation->profit ?? 0);
                $companyCurrency = strtoupper((string) ($valuation->company?->base_currency ?? 'USD'));
                $exchangeRate = $resolveRate($companyCurrency);

                return [
                    'date' => optional($valuation->valuation_date ?? $valuation->created_at)->toDateString(),
                    'company_id' => $valuation->company_id,
                    'company' => $valuation->company?->name,
                    'description' => 'Latest Published Profit',
                    'currency' => $companyCurrency,
                    'exchange_rate' => $exchangeRate,
                    'credit_original' => $amount,
                    'debit_original' => 0.0,
                    'credit' => $amount * $exchangeRate,
                    'debit' => 0.0,
                    'period' => $valuation->valuation_period,
                    'sort_date' => optional($valuation->valuation_date ?? $valuation->created_at)->timestamp ?? 0,
                    'sort_time' => optional($valuation->updated_at)->timestamp ?? 0,
                ];
            });

        $debits = StatementOfAccount::query()
            ->with('company')
            ->where('investor_id', $investor->id)
            ->where('transaction_type', StatementOfAccount::TYPE_DIVIDEND)
            ->where('status', StatementOfAccount::STATUS_PAID)
            ->orderBy('transaction_date')
            ->orderBy('created_at')
            ->get()
            ->map(function (StatementOfAccount $statement) use ($resolveRate) {
                $amount = (float) $statement->amount;
                $companyCurrency = strtoupper((string) ($statement->company?->base_currency ?? 'USD'));
                $exchangeRate = $resolveRate($companyCurrency);

                return [
                    'date' => optional($statement->transaction_date ?? $statement->created_at)->toDateString(),
                    'company_id' => $statement->company_id,
                    'company' => $statement->company?->name,
                    'description' => 'Dividend Paid',
                    'currency' => $companyCurrency,
                    'exchange_rate' => $exchangeRate,
                    'credit_original' => 0.0,
                    'debit_original' => $amount,
                    'credit' => 0.0,
                    'debit' => $amount * $exchangeRate,
                    'period' => optional($statement->transaction_date)->format('Y-m'),
                    'sort_date' => optional($statement->transaction_date ?? $statement->created_at)->timestamp ?? 0,
                    'sort_time' => optional($statement->created_at)->timestamp ?? 0,
                ];
            });

        $ledger = $credits
            ->concat($debits)
            ->sort(function (array $a, array $b) {
                return [$a['sort_date'], $a['sort_time']] <=> [$b['sort_date'], $b['sort_time']];
            })
            ->values();

        $runningBalanceReporting = 0.0;
        $companyBalances = [];

        $rows = $ledger->map(function (array $entry) use (&$runningBalanceReporting, &$companyBalances) {
            $runningBalanceReporting += ((float) $entry['credit'] - (float) $entry['debit']);

            $companyKey = (string) ($entry['company_id'] ?? 0);
            if (! isset($companyBalances[$companyKey])) {
                $companyBalances[$companyKey] = [
                    'company_id' => $entry['company_id'],
                    'company' => $entry['company'],
                    'currency' => $entry['currency'],
                    'exchange_rate' => (float) $entry['exchange_rate'],
                    'credit_original' => 0.0,
                    'debit_original' => 0.0,
                    'balance_original' => 0.0,
                    'balance_reporting_currency' => 0.0,
                ];
            }

            $companyBalances[$companyKey]['credit_original'] += (float) $entry['credit_original'];
            $companyBalances[$companyKey]['debit_original'] += (float) $entry['debit_original'];
            $companyBalances[$companyKey]['balance_original'] += ((float) $entry['credit_original'] - (float) $entry['debit_original']);
            $companyBalances[$companyKey]['balance_reporting_currency'] += ((float) $entry['credit'] - (float) $entry['debit']);

            return [
                'date' => $entry['date'],
                'company' => $entry['company'],
                'description' => $entry['description'],
                'credit' => round((float) $entry['credit'], 2),
                'debit' => round((float) $entry['debit'], 2),
                'balance' => round($runningBalanceReporting, 2),
                'period' => $entry['period'],
                'source_currency' => $entry['currency'],
                'exchange_rate' => round((float) $entry['exchange_rate'], 6),
                'credit_original' => round((float) $entry['credit_original'], 2),
                'debit_original' => round((float) $entry['debit_original'], 2),
            ];
        });

        $companySummaries = collect($companyBalances)
            ->values()
            ->map(function (array $summary) {
                return [
                    'company_id' => $summary['company_id'],
                    'company' => $summary['company'],
                    'currency' => $summary['currency'],
                    'exchange_rate' => round((float) $summary['exchange_rate'], 6),
                    'credit_original' => round((float) $summary['credit_original'], 2),
                    'debit_original' => round((float) $summary['debit_original'], 2),
                    'balance_original' => round((float) $summary['balance_original'], 2),
                    'balance_reporting_currency' => round((float) $summary['balance_reporting_currency'], 2),
                ];
            })
            ->all();

        return response()->json([
            'data' => [
                'partner' => [
                    'id' => $investor->id,
                    'name' => $investor->name,
                    'email' => $investor->email,
                ],
                'reporting_currency' => $reportingCurrency,
                'ledger' => $rows->values(),
                'company_summaries' => $companySummaries,
                'current_retained_earnings_balance' => round($runningBalanceReporting, 2),
            ],
        ]);
    }
}
