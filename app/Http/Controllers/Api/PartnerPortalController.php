<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investor;
use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Models\Company;
use App\Models\StatementOfAccount;
use App\Models\FinancialData;
use App\Models\Notification;
use App\Models\PortfolioValuation;
use App\Models\Announcement;
use App\Models\CurrencySetting;
use App\Models\ExchangeRate;
use App\Models\OwnershipRegister;
use App\Models\OwnershipRegisterItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class PartnerPortalController extends Controller
{
    /**
     * Get authenticated partner's profile
     */
    public function profile($investor_id)
    {
        $investor = $this->resolveLinkedInvestor($investor_id);

        if (!$investor) {
            return response()->json([
                'message' => 'Partner not found',
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $investor->id,
                'name' => $investor->name,
                'email' => $investor->email,
                'phone' => $investor->phone,
                'status' => $investor->status,
                'created_at' => $investor->created_at,
            ],
        ]);
    }

    /**
     * Get list of companies the partner has invested in
     */
    public function companies($investor_id)
    {
        $investor = $this->resolveLinkedInvestor($investor_id);

        if (!$investor) {
            return response()->json([
                'message' => 'Partner not found',
            ], 404);
        }

        $investments = Investment::where('investor_id', $investor_id)
            ->where('status', 'Active')
            ->with(['company', 'transactions'])
            ->get();

        $latestValuationByCompany = $this->latestValuationByCompanyForPartner((int) $investor_id);

        $ownershipByCompany = $this->currentOwnershipByCompanyForValuations((int) $investor_id, $latestValuationByCompany);

        return response()->json([
            'data' => $investments->groupBy('company_id')->map(function ($group, $companyId) use ($latestValuationByCompany, $ownershipByCompany) {
                $company = $group->first()->company;
                $totalInvested = (float) $group->sum(function ($investment) {
                    return $investment->getCurrentBalance();
                });
                $valuation = $latestValuationByCompany->get($companyId);
                $currentValue = $valuation ? (float) $valuation->indicative_value : null;
                $profit = $valuation ? (float) $valuation->profit : null;
                $roi = ($profit !== null && $totalInvested > 0)
                    ? round(($profit / $totalInvested) * 100, 2)
                    : null;
                $ownership = $ownershipByCompany->get((int) $companyId);

                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'description' => $company->description ?? null,
                    'ownership_percentage' => $ownership,
                    'total_invested' => $totalInvested,
                    'current_value' => $currentValue,
                    'profit' => $profit,
                    'roi_percentage' => $roi,
                    'roi_period' => 'Semi-Annual',
                    'valuation_period' => $valuation?->valuation_period,
                    'valuation_date' => $valuation?->valuation_date ? $valuation->valuation_date->toDateString() : null,
                ];
            })->values(),
            'count' => $investments->groupBy('company_id')->count(),
        ]);
    }

    /**
     * Portfolio: per-investment performance breakdown
     */
    public function portfolio($investor_id)
    {
        $investor = $this->resolveLinkedInvestor($investor_id);

        if (!$investor) {
            return response()->json(['message' => 'Partner not found'], 404);
        }

        $investments = Investment::where('investor_id', $investor_id)
            ->with(['company', 'transactions'])
            ->where('status', 'Active')
            ->latest('created_at')
            ->get();

        $latestValuationByCompany = $this->latestValuationByCompanyForPartner((int) $investor_id);

        $data = $investments->map(function ($investment) use ($latestValuationByCompany) {
            $totalInvested = (float) $investment->getCurrentBalance();
            $valuation = $latestValuationByCompany->get((int) $investment->company_id);
            $currentValue = $valuation ? (float) $valuation->indicative_value : null;
            $profit = $valuation ? (float) $valuation->profit : null;
            $roi = ($profit !== null && $totalInvested > 0)
                ? round(($profit / $totalInvested) * 100, 2)
                : null;

            return [
                'id'               => $investment->id,
                'company'          => [
                    'id'   => $investment->company->id,
                    'name' => $investment->company->name,
                ],
                'total_invested'   => (float)$totalInvested,
                'indicative_value' => $currentValue,
                'current_value'    => $currentValue,
                'profit'           => $profit,
                'roi_percentage'   => $roi,
                'roi_period'       => 'Semi-Annual',
                'status'           => 'Active',
                'valuation_date'   => $valuation?->valuation_date
                    ? $valuation->valuation_date->toDateString() : null,
                'valuation_notes'  => $valuation?->notes,
                'invested_at'      => $investment->invested_at
                    ? $investment->invested_at->toIso8601String() : null,
            ];
        })->values();

        $totalInvestedAll = $data->sum('total_invested');
        $totalProfitAll   = $data->whereNotNull('profit')->sum('profit');
        $portfolioROI     = $totalInvestedAll > 0
            ? round(($totalProfitAll / $totalInvestedAll) * 100, 2) : null;

        return response()->json([
            'data'    => $data,
            'summary' => [
                'total_investments'  => $investments->count(),
                'active_investments' => $investments->count(),
                'total_invested'     => (float)$totalInvestedAll,
                'total_profit'       => (float)$totalProfitAll,
                'portfolio_roi'      => $portfolioROI,
            ],
        ]);
    }

    /**
     * Investment Statement: all investment transactions for partner with running balance
     */
    public function investmentStatement($investor_id)
    {
        $investor = $this->resolveLinkedInvestor($investor_id);

        if (!$investor) {
            return response()->json(['message' => 'Partner not found'], 404);
        }

        $transactions = InvestmentTransaction::whereHas('investment', function ($q) use ($investor_id) {
                $q->where('investor_id', $investor_id);
            })
            ->with(['investment.company'])
            ->orderBy('transaction_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $runningBalance = 0.0;
        $rows = $transactions->map(function ($tx) use (&$runningBalance) {
            $runningBalance += (float)$tx->amount;

            return [
                'id'               => $tx->id,
                'date'             => $tx->transaction_date
                    ? $tx->transaction_date->toDateString() : null,
                'investment_id'    => $tx->investment_id,
                'company'          => $tx->investment && $tx->investment->company
                    ? ['id' => $tx->investment->company->id, 'name' => $tx->investment->company->name]
                    : null,
                'transaction_type' => $tx->transaction_type,
                'amount'           => (float)$tx->amount,
                'running_balance'  => $runningBalance,
                'notes'            => $tx->notes,
            ];
        });

        return response()->json([
            'investor' => [
                'id'   => $investor->id,
                'name' => $investor->name,
            ],
            'statement' => $rows->values(),
            'summary'   => [
                'total_transactions' => $transactions->count(),
                'total_invested'     => $runningBalance,
            ],
        ]);
    }

    /**
     * Get partner's investment list
     */
    public function investments($investor_id)
    {
        $investor = $this->resolveLinkedInvestor($investor_id);

        if (!$investor) {
            return response()->json([
                'message' => 'Partner not found',
            ], 404);
        }

        $investments = Investment::where('investor_id', $investor_id)
            ->with(['company', 'transactions'])
            ->where('status', 'Active')
            ->latest('created_at')
            ->get();

        $latestValuationByCompany = $this->latestValuationByCompanyForPartner((int) $investor_id);

        return response()->json([
            'data' => $investments->map(function ($investment) use ($latestValuationByCompany) {
                $totalInvested = (float) $investment->getCurrentBalance();
                $valuation = $latestValuationByCompany->get((int) $investment->company_id);
                $currentValue = $valuation ? (float) $valuation->indicative_value : null;
                $profit = $valuation ? (float) $valuation->profit : null;
                $roi = ($profit !== null && $totalInvested > 0)
                    ? round(($profit / $totalInvested) * 100, 2)
                    : null;
                
                return [
                    'id' => $investment->id,
                    'company' => [
                        'id' => $investment->company->id,
                        'name' => $investment->company->name,
                    ],
                    'total_invested' => $totalInvested,
                    'status' => 'Active',
                    'investment_date' => $investment->invested_at,
                    'created_at' => $investment->created_at,
                    'performance' => [
                        'total_invested' => $totalInvested,
                        'profit' => $profit,
                        'indicative_value' => $currentValue,
                        'current_value' => $currentValue,
                        'roi_percentage' => $roi,
                        'roi_period' => 'Semi-Annual',
                        'valuation_date' => $valuation?->valuation_date,
                        'notes' => $valuation?->notes,
                    ],
                    'transaction_count' => $investment->transactions->count(),
                ];
            })->values(),
            'summary' => [
                'total_investments' => $investments->count(),
                'total_invested' => $investments->sum(function ($inv) {
                    return $inv->getCurrentBalance();
                }),
                'active_investments' => $investments->count(),
                'active_amount' => $investments->sum(function ($inv) {
                    return $inv->getCurrentBalance();
                }),
            ],
        ]);
    }

    /**
     * Get partner's statement of account (ledger with running balance)
     */
    public function statementOfAccount($investor_id)
    {
        $investor = $this->resolveLinkedInvestor($investor_id);

        if (!$investor) {
            return response()->json([
                'message' => 'Partner not found',
            ], 404);
        }

        // Get all transactions for this partner, ordered chronologically
        $transactions = StatementOfAccount::where('investor_id', $investor_id)
            ->with('company')
            ->orderBy('transaction_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        // Calculate running balance
        $runningBalance = 0;
        $ledger = $transactions->map(function ($transaction) use (&$runningBalance, $investor_id) {
            // Credit: Dividend (positive), Debit: Withdrawal (negative)
            if ($transaction->transaction_type === StatementOfAccount::TYPE_DIVIDEND) {
                $runningBalance += $transaction->amount;
                $credit = $transaction->amount;
                $debit = 0;
            } else {
                $runningBalance -= $transaction->amount;
                $credit = 0;
                $debit = $transaction->amount;
            }

            $company = $transaction->company;

            $attachments = $transaction->transaction_type === StatementOfAccount::TYPE_WITHDRAWAL
                ? $this->buildWithdrawalAttachmentsMetadata($transaction, (int) $investor_id)
                : [];

            return [
                'id' => $transaction->id,
                'date' => $transaction->transaction_date,
                'company' => $company ? [
                    'id' => $company->id,
                    'name' => $company->name,
                ] : [
                    'id' => null,
                    'name' => 'Unknown Company',
                ],
                'type' => $transaction->transaction_type,
                'reference' => 'REF-' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT),
                'description' => $transaction->transaction_type === StatementOfAccount::TYPE_DIVIDEND
                    ? 'Dividend Payment from ' . ($company ? $company->name : 'Unknown Company')
                    : 'Withdrawal Request to ' . ($transaction->bank_name ?? 'N/A'),
                'credit' => $credit,
                'debit' => $debit,
                'running_balance' => $runningBalance,
                'notes' => $transaction->notes,
                'status' => $transaction->status,
                'attachments' => $attachments,
                'attachment_urls' => array_values(array_map(function ($attachment) {
                    return $attachment['download_url'] ?? null;
                }, $attachments)),
                'attachment_count' => count($attachments),
            ];
        });

        // Calculate totals
        $totalCredits = $transactions->where('transaction_type', StatementOfAccount::TYPE_DIVIDEND)->sum('amount');
        $totalDebits = $transactions->where('transaction_type', StatementOfAccount::TYPE_WITHDRAWAL)->sum('amount');
        $currentBalance = $totalCredits - $totalDebits;

        return response()->json([
            'investor' => [
                'id' => $investor->id,
                'name' => $investor->name,
            ],
            'statement' => $ledger->values(),
            'summary' => [
                'total_credits' => $totalCredits,
                'total_debits' => $totalDebits,
                'balance' => $currentBalance,
                'transaction_count' => $transactions->count(),
            ],
        ]);
    }

    /**
     * Download a withdrawal attachment for the given partner.
     * Route is signed and ownership is validated before download.
     */
    public function downloadStatementAttachment(Request $request, $investor_id, StatementOfAccount $statement_of_account, $index)
    {
        if ((int) $statement_of_account->investor_id !== (int) $investor_id) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        if ($statement_of_account->transaction_type !== StatementOfAccount::TYPE_WITHDRAWAL) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        $paths = $this->normalizeAttachmentPaths($statement_of_account->attachment_paths ?? []);
        $attachmentIndex = (int) $index;

        if (! isset($paths[$attachmentIndex])) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        $path = $paths[$attachmentIndex];
        if (! is_string($path) || ! Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        return Storage::disk('local')->download($path, $this->extractAttachmentOriginalName($path));
    }

    /**
     * Get partner's portfolio summary
     */
    public function portfolioSummary($investor_id)
    {
        $investor = $this->resolveLinkedInvestor($investor_id);

        if (!$investor) {
            return response()->json([
                'message' => 'Partner not found',
            ], 404);
        }

        $reportingCurrency = CurrencySetting::query()->value('reporting_currency') ?? 'USD';

        $activeRatesByCode = ExchangeRate::active()
            ->whereDate('effective_date', '<=', now()->toDateString())
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy(function ($rate) {
                return strtoupper((string) $rate->currency_code);
            })
            ->map(function ($rates) {
                return (float) $rates->first()->exchange_rate;
            });

        $resolveRate = function (?string $currencyCode) use ($activeRatesByCode, $reportingCurrency): float {
            $code = strtoupper((string) ($currencyCode ?? 'USD'));
            $reporting = strtoupper((string) $reportingCurrency);

            if ($code === $reporting) {
                return 1.0;
            }

            return (float) ($activeRatesByCode->get($code) ?? 1.0);
        };

        $investments = Investment::where('investor_id', $investor_id)
            ->with(['company', 'transactions'])
            ->where('status', 'Active')
            ->get();

        $publishedValuations = $this->publishedValuationsForPartner((int) $investor_id)
            ->with('company')
            ->orderByDesc('valuation_year')
            ->orderByDesc('valuation_half')
            ->orderByDesc('valuation_date')
            ->orderByDesc('created_at')
            ->get();

        $latestValuationByCompany = $publishedValuations->groupBy('company_id')->map(fn ($rows) => $rows->first());
        $ownershipByCompany = $this->currentOwnershipByCompanyForValuations((int) $investor_id, $latestValuationByCompany);

        $companyCards = $investments
            ->groupBy('company_id')
            ->map(function ($group, $companyId) use ($latestValuationByCompany, $resolveRate, $ownershipByCompany) {
            $company = $group->first()->company;
            $totalInvested = (float) $group->sum(function ($investment) {
                return $investment->getCurrentBalance();
            });

            $valuation = $latestValuationByCompany->get($companyId);
            $currentValue = $valuation ? (float) $valuation->indicative_value : null;
            // Profit is entered by admin in portfolio valuation and used as-is.
            $profit = $valuation ? (float) $valuation->profit : null;
            $roi = ($profit !== null && $totalInvested > 0)
                ? round(($profit / $totalInvested) * 100, 2)
                : null;

            $currencyCode = $company && ! empty($company->base_currency)
                ? strtoupper((string) $company->base_currency)
                : 'USD';

            $exchangeRate = $resolveRate($currencyCode);
            $ownership = $ownershipByCompany->get((int) $companyId);

            return [
                'company_id' => (int) $companyId,
                'company' => $company ? [
                    'id' => $company->id,
                    'name' => $company->name,
                ] : null,
                'currency' => $currencyCode,
                'exchange_rate' => $exchangeRate,
                'ownership_percentage' => $ownership,
                'total_invested' => $totalInvested,
                'indicative_value' => $currentValue,
                'current_value' => $currentValue,
                'profit' => $profit,
                'roi_percentage' => $roi,
                'roi_period' => 'Semi-Annual',
                'valuation_period' => $valuation?->valuation_period,
                'valuation_date' => $valuation?->valuation_date ? $valuation->valuation_date->toDateString() : null,
                'notes' => $valuation?->notes,
                'converted' => [
                    'total_invested' => $totalInvested * $exchangeRate,
                    'indicative_value' => $currentValue !== null ? $currentValue * $exchangeRate : 0.0,
                    'profit' => $profit !== null ? $profit * $exchangeRate : 0.0,
                ],
            ];
        })->values();

        $transactions = StatementOfAccount::where('investor_id', $investor_id)
            ->with('company')
            ->get();

        $accountBalanceReporting = $transactions->sum(function ($transaction) {
            $currencyCode = $transaction->company && ! empty($transaction->company->base_currency)
                ? strtoupper((string) $transaction->company->base_currency)
                : 'USD';

            $rate = ($this->resolveDashboardRate($currencyCode) ?? 1.0);
            $amount = (float) $transaction->amount * $rate;
            return $transaction->transaction_type === 'Dividend' ? $amount : -$amount;
        });

        $totalInvestmentsReporting = (float) $companyCards->sum('converted.total_invested');
        $lastDeclaredProfitReporting = (float) $companyCards->sum('converted.profit');
        $portfolioValueReporting = (float) $companyCards->sum('converted.indicative_value');

        return response()->json([
            'data' => [
                'investor' => [
                    'id' => $investor->id,
                    'name' => $investor->name,
                ],
                'reporting_currency' => $reportingCurrency,
                'summary_cards' => [
                    'partner_account_balance' => round($accountBalanceReporting, 2),
                    'total_investments' => round($totalInvestmentsReporting, 2),
                    'last_declared_profit' => round($lastDeclaredProfitReporting, 2),
                    'portfolio_value' => round($portfolioValueReporting, 2),
                ],
                'investment_cards' => $companyCards->map(function ($card) {
                    return [
                        'company' => $card['company'],
                        'currency' => $card['currency'],
                        'ownership_percentage' => $card['ownership_percentage'],
                        'total_invested' => round((float) $card['total_invested'], 2),
                        'indicative_value' => $card['indicative_value'] !== null ? round((float) $card['indicative_value'], 2) : null,
                        'current_value' => $card['current_value'] !== null ? round((float) $card['current_value'], 2) : null,
                        'profit' => $card['profit'] !== null ? round((float) $card['profit'], 2) : null,
                        'roi_percentage' => $card['roi_percentage'],
                        'roi_period' => $card['roi_period'],
                        'valuation_period' => $card['valuation_period'],
                        'valuation_date' => $card['valuation_date'],
                        'notes' => $card['notes'],
                    ];
                })->values(),
            ],
        ]);
    }

    /**
     * Get period-filtered financial data for partner dashboard.
     */
    public function financialData(Request $request, $investor_id)
    {
        $investor = $this->resolveLinkedInvestor($investor_id);

        if (! $investor) {
            return response()->json([
                'message' => 'Partner not found',
            ], 404);
        }

        $year = $request->query('year', (int) now()->format('Y'));
        $halfYear = $request->query('half_year', 'H1');

        $records = FinancialData::where('year', (int) $year)
            ->where('half_year', $halfYear)
            ->get()
            ->keyBy('type');

        $profit = $records->get(FinancialData::TYPE_PROFIT);
        $indicativeValue = $records->get(FinancialData::TYPE_INDICATIVE_VALUE);

        return response()->json([
            'investor' => [
                'id' => $investor->id,
                'name' => $investor->name,
            ],
            'period' => [
                'year' => (int) $year,
                'half_year' => $halfYear,
            ],
            'data' => [
                'profit' => $profit ? [
                    'amount' => (float) $profit->amount,
                    'currency' => $profit->currency,
                    'notes' => $profit->notes,
                ] : null,
                'indicative_value' => $indicativeValue ? [
                    'amount' => (float) $indicativeValue->amount,
                    'currency' => $indicativeValue->currency,
                    'notes' => $indicativeValue->notes,
                ] : null,
            ],
        ]);
    }

    /**
     * Get active notifications for partner dashboard.
     */
    public function notifications($investor_id)
    {
        $investor = $this->resolveLinkedInvestor($investor_id);

        if (! $investor) {
            return response()->json([
                'message' => 'Partner not found',
            ], 404);
        }

        $notifications = Notification::active()
            ->with(['valuation.company'])
            ->where(function ($query) use ($investor_id) {
                $query->whereNull('target_investor_id')
                    ->orWhere('target_investor_id', $investor_id);
            })
            ->latest('publish_date')
            ->latest('created_at')
            ->get()
            ->map(function (Notification $notification) use ($investor_id) {
                return [
                    'id' => 'notification-' . $notification->id,
                    'source' => 'notification',
                    'notification_type' => $notification->notification_type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'important_notes' => $notification->important_notes,
                    'publish_date' => $notification->publish_date ? $notification->publish_date->toDateString() : null,
                    'expiry_date' => $notification->expiry_date ? $notification->expiry_date->toDateString() : null,
                    'is_active' => $notification->is_active,
                    'valuation_id' => $notification->valuation_id,
                    'target_investor_id' => $notification->target_investor_id,
                    'link' => $notification->valuation_id ? "/partner-portal/{$investor_id}/valuations/{$notification->valuation_id}" : null,
                    'valuation' => $notification->valuation ? [
                        'id' => $notification->valuation->id,
                        'company' => $notification->valuation->company ? [
                            'id' => $notification->valuation->company->id,
                            'name' => $notification->valuation->company->name,
                        ] : null,
                    ] : null,
                ];
            });

        $companyIds = Investment::where('investor_id', $investor_id)
            ->pluck('company_id')
            ->unique()
            ->values();

        $announcementItems = Announcement::published()
            ->where(function ($query) use ($investor_id, $companyIds) {
                $query->whereRaw('LOWER(audience_type) = ?', [strtolower(Announcement::AUDIENCE_ALL)])
                    ->orWhere(function ($sub) use ($investor_id) {
                        $sub->whereRaw('LOWER(audience_type) = ?', [strtolower(Announcement::AUDIENCE_PARTNER)])
                            ->where('investor_id', $investor_id);
                    })
                    ->orWhere(function ($sub) use ($companyIds) {
                        $sub->whereRaw('LOWER(audience_type) = ?', [strtolower(Announcement::AUDIENCE_COMPANY)])
                            ->whereIn('company_id', $companyIds);
                    });
            })
            ->with('company')
            ->latest('publish_date')
            ->latest('created_at')
            ->get()
            ->map(function (Announcement $announcement) use ($investor_id) {
                return [
                    'id' => 'announcement-' . $announcement->id,
                    'source' => 'announcement',
                    'notification_type' => 'Announcement',
                    'title' => $announcement->title,
                    'message' => $announcement->message,
                    'important_notes' => null,
                    'publish_date' => $announcement->publish_date ? $announcement->publish_date->toDateString() : null,
                    'expiry_date' => $announcement->expiry_date ? $announcement->expiry_date->toDateString() : null,
                    'is_active' => true,
                    'valuation_id' => null,
                    'target_investor_id' => $investor_id,
                    'link' => "/partner-portal/{$investor_id}/announcements",
                    'valuation' => null,
                ];
            });

        $feed = $notifications
            ->concat($announcementItems)
            ->sortByDesc(function ($item) {
                return $item['publish_date'] ?? '1900-01-01';
            })
            ->values();

        return response()->json([
            'investor' => [
                'id' => $investor->id,
                'name' => $investor->name,
            ],
            'data' => $feed,
        ]);
    }

    /**
     * Get latest published portfolio valuation for partner.
     */
    public function latestValuation(Request $request, $investor_id)
    {
        $investor = $this->resolveLinkedInvestor($investor_id);

        if (! $investor) {
            return response()->json([
                'message' => 'Partner not found',
            ], 404);
        }

        $partnerCompanyIds = Investment::query()
            ->where('investor_id', $investor_id)
            ->pluck('company_id')
            ->unique()
            ->values();

        $query = $this->publishedValuationsForPartner((int) $investor_id)
            ->with('company')
            ->whereIn('company_id', $partnerCompanyIds);

        if ($request->filled('company_id')) {
            $query->where('company_id', (int) $request->query('company_id'));
        }

        $valuation = $query->latest('valuation_date')->latest('created_at')->first();

        if (! $valuation) {
            return response()->json([
                'investor' => [
                    'id' => $investor->id,
                    'name' => $investor->name,
                ],
                'data' => null,
            ]);
        }

        $totalInvested = (float) InvestmentTransaction::whereHas('investment', function ($q) use ($investor_id, $valuation) {
            $q->where('investor_id', $investor_id)
                ->where('company_id', $valuation->company_id);
        })->sum('amount');

        $profit = (float) $valuation->profit;
        $roi = $totalInvested > 0 ? round(($profit / $totalInvested) * 100, 2) : null;

        return response()->json([
            'investor' => [
                'id' => $investor->id,
                'name' => $investor->name,
            ],
            'data' => [
                'id' => $valuation->id,
                'company' => $valuation->company ? [
                    'id' => $valuation->company->id,
                    'name' => $valuation->company->name,
                ] : null,
                'valuation_period' => $valuation->valuation_period,
                'total_invested' => $totalInvested,
                'indicative_value' => (float) $valuation->indicative_value,
                'current_value' => (float) $valuation->indicative_value,
                'profit' => $profit,
                'roi_percentage' => $roi,
                'roi_period' => 'Semi-Annual',
                'valuation_date' => $valuation->valuation_date ? $valuation->valuation_date->toDateString() : null,
                'notes' => $valuation->notes,
                'status' => $valuation->status,
            ],
        ]);
    }

    /**
     * Get all published valuations for partner.
     */
    public function valuationHistory(Request $request, $investor_id)
    {
        $investor = $this->resolveLinkedInvestor($investor_id);

        if (! $investor) {
            return response()->json([
                'message' => 'Partner not found',
            ], 404);
        }

        $partnerCompanyIds = Investment::query()
            ->where('investor_id', $investor_id)
            ->pluck('company_id')
            ->unique()
            ->values();

        $query = $this->publishedValuationsForPartner((int) $investor_id)
            ->with('company')
            ->whereIn('company_id', $partnerCompanyIds)
            ->latest('valuation_date')
            ->latest('created_at');

        if ($request->filled('company_id')) {
            $query->where('company_id', (int) $request->query('company_id'));
        }

        $valuations = $query->get()->map(function (PortfolioValuation $valuation) use ($investor) {
            $totalInvested = (float) InvestmentTransaction::whereHas('investment', function ($q) use ($valuation, $investor) {
                $q->where('investor_id', $investor->id)
                    ->where('company_id', $valuation->company_id);
            })->sum('amount');

            $profit = (float) $valuation->profit;
            $roi = $totalInvested > 0 ? round(($profit / $totalInvested) * 100, 2) : null;

            return [
                'id' => $valuation->id,
                'company' => $valuation->company ? [
                    'id' => $valuation->company->id,
                    'name' => $valuation->company->name,
                ] : null,
                'valuation_period' => $valuation->valuation_period,
                'total_invested' => $totalInvested,
                'indicative_value' => (float) $valuation->indicative_value,
                'current_value' => (float) $valuation->indicative_value,
                'profit' => $profit,
                'roi_percentage' => $roi,
                'roi_period' => 'Semi-Annual',
                'valuation_date' => $valuation->valuation_date ? $valuation->valuation_date->toDateString() : null,
                'notes' => $valuation->notes,
                'status' => $valuation->status,
            ];
        })->values();

        return response()->json([
            'investor' => [
                'id' => $investor->id,
                'name' => $investor->name,
            ],
            'data' => $valuations,
        ]);
    }

    /**
     * Get a single published valuation for partner.
     */
    public function valuationShow($investor_id, $valuation_id)
    {
        $investor = $this->resolveLinkedInvestor($investor_id);

        if (! $investor) {
            return response()->json([
                'message' => 'Partner not found',
            ], 404);
        }

        $partnerCompanyIds = Investment::query()
            ->where('investor_id', $investor_id)
            ->pluck('company_id')
            ->unique()
            ->values();

        $valuation = $this->publishedValuationsForPartner((int) $investor_id)
            ->with('company')
            ->where('id', $valuation_id)
            ->whereIn('company_id', $partnerCompanyIds)
            ->first();

        if (! $valuation) {
            return response()->json([
                'message' => 'Valuation not found',
            ], 404);
        }

        $totalInvested = (float) InvestmentTransaction::whereHas('investment', function ($q) use ($investor_id, $valuation) {
            $q->where('investor_id', $investor_id)
                ->where('company_id', $valuation->company_id);
        })->sum('amount');

        $profit = (float) $valuation->profit;
        $roi = $totalInvested > 0 ? round(($profit / $totalInvested) * 100, 2) : null;

        return response()->json([
            'investor' => [
                'id' => $investor->id,
                'name' => $investor->name,
            ],
            'data' => [
                'id' => $valuation->id,
                'company' => $valuation->company ? [
                    'id' => $valuation->company->id,
                    'name' => $valuation->company->name,
                ] : null,
                'valuation_period' => $valuation->valuation_period,
                'total_invested' => $totalInvested,
                'indicative_value' => (float) $valuation->indicative_value,
                'current_value' => (float) $valuation->indicative_value,
                'profit' => $profit,
                'roi_percentage' => $roi,
                'roi_period' => 'Semi-Annual',
                'valuation_date' => $valuation->valuation_date ? $valuation->valuation_date->toDateString() : null,
                'notes' => $valuation->notes,
                'status' => $valuation->status,
            ],
        ]);
    }

    /**
     * Get published announcements targeted to this partner.
     */
    public function announcements($investor_id)
    {
        $investor = $this->resolveLinkedInvestor($investor_id);

        if (! $investor) {
            return response()->json([
                'message' => 'Partner not found',
            ], 404);
        }

        $companyIds = Investment::where('investor_id', $investor_id)
            ->pluck('company_id')
            ->unique()
            ->values();

        $announcements = Announcement::published()
            ->where(function ($query) use ($investor_id, $companyIds) {
                $query->whereRaw('LOWER(audience_type) = ?', [strtolower(Announcement::AUDIENCE_ALL)])
                    ->orWhere(function ($sub) use ($investor_id) {
                        $sub->whereRaw('LOWER(audience_type) = ?', [strtolower(Announcement::AUDIENCE_PARTNER)])
                            ->where('investor_id', $investor_id);
                    })
                    ->orWhere(function ($sub) use ($companyIds) {
                        $sub->whereRaw('LOWER(audience_type) = ?', [strtolower(Announcement::AUDIENCE_COMPANY)])
                            ->whereIn('company_id', $companyIds);
                    });
            })
            ->latest('publish_date')
            ->get();

        return response()->json([
            'investor' => [
                'id' => $investor->id,
                'name' => $investor->name,
            ],
            'data' => $announcements,
        ]);
    }

    protected function resolveDashboardRate(string $currencyCode): ?float
    {
        $reportingCurrency = strtoupper((string) (CurrencySetting::query()->value('reporting_currency') ?? 'USD'));
        $base = strtoupper((string) $currencyCode);

        if ($base === $reportingCurrency) {
            return 1.0;
        }

        $rate = ExchangeRate::active()
            ->where('currency_code', $base)
            ->whereDate('effective_date', '<=', now()->toDateString())
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->value('exchange_rate');

        return $rate !== null ? (float) $rate : 1.0;
    }

    protected function resolveLinkedInvestor($investorId): ?Investor
    {
        $id = (int) $investorId;
        if ($id <= 0) {
            return null;
        }

        $investor = Investor::find($id);
        if ($investor) {
            return $investor;
        }

        $user = User::find($id);
        if (! $user || empty($user->email)) {
            return null;
        }

        return Investor::resolveLinkedByEmail((string) $user->email);
    }

    protected function publishedValuationsForPartner(int $investorId)
    {
        return PortfolioValuation::query()
            ->where('status', PortfolioValuation::STATUS_PUBLISHED)
            ->where(function ($query) use ($investorId) {
                $query->where('investor_id', $investorId)
                    ->orWhereNull('investor_id');
            });
    }

    protected function latestValuationByCompanyForPartner(int $investorId)
    {
        return $this->publishedValuationsForPartner($investorId)
            ->orderByDesc('valuation_year')
            ->orderByDesc('valuation_half')
            ->orderByDesc('valuation_date')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('company_id')
            ->map(fn ($rows) => $rows->first());
    }

    protected function currentOwnershipByCompanyForValuations(int $investorId, $latestValuationByCompany)
    {
        $valuationByCompany = collect($latestValuationByCompany)
            ->filter()
            ->mapWithKeys(function ($valuation, $companyId) {
                return [(int) $companyId => (int) $valuation->id];
            });

        if ($valuationByCompany->isEmpty()) {
            return collect();
        }

        $valuationIds = collect($latestValuationByCompany)
            ->filter()
            ->map(fn ($valuation) => (int) $valuation->id)
            ->unique()
            ->values();

        if ($valuationIds->isEmpty()) {
            return collect();
        }

        $items = OwnershipRegisterItem::query()
            ->with(['register:id,company_id,portfolio_valuation_id,status,is_current,effective_date,created_at'])
            ->where('investor_id', $investorId)
            ->whereHas('register', function ($query) use ($valuationIds) {
                $query->whereIn('portfolio_valuation_id', $valuationIds);
            })
            ->get();

        $itemByCompanyAndValuation = $items
            ->groupBy(function (OwnershipRegisterItem $item) {
                return sprintf('%d|%d', (int) ($item->register?->company_id ?? 0), (int) ($item->register?->portfolio_valuation_id ?? 0));
            })
            ->map(function ($rows) {
                $latest = $rows->sortByDesc(function (OwnershipRegisterItem $item) {
                    return [
                        ($item->register?->is_current ? 1 : 0),
                        optional($item->register?->effective_date)->timestamp ?? 0,
                        optional($item->register?->created_at)->timestamp ?? 0,
                        (int) ($item->register?->id ?? 0),
                    ];
                })->first();

                return $latest ? (float) $latest->ownership_percentage : null;
            });

        return $valuationByCompany->mapWithKeys(function (int $valuationId, int $companyId) use ($itemByCompanyAndValuation) {
            return [$companyId => $itemByCompanyAndValuation->get(sprintf('%d|%d', $companyId, $valuationId))];
        })->union(
            collect($latestValuationByCompany)->mapWithKeys(function ($valuation, $companyId) {
                if ($valuation) {
                    return [];
                }

                return [(int) $companyId => null];
            })
        );
    }

    protected function buildWithdrawalAttachmentsMetadata(StatementOfAccount $statement, int $investorId): array
    {
        if ($statement->transaction_type !== StatementOfAccount::TYPE_WITHDRAWAL) {
            return [];
        }

        $paths = $this->normalizeAttachmentPaths($statement->attachment_paths ?? []);
        if (empty($paths)) {
            return [];
        }

        $expiresAt = now()->addMinutes(30);

        return collect($paths)
            ->map(function ($path, $index) use ($statement, $investorId, $expiresAt) {
                if (! is_string($path)) {
                    return null;
                }

                $exists = Storage::disk('local')->exists($path);
                $fileName = $this->extractAttachmentOriginalName($path);

                return [
                    'id' => (int) $index,
                    'original_file_name' => $fileName,
                    'file_type' => $exists
                        ? (Storage::disk('local')->mimeType($path) ?: pathinfo($fileName, PATHINFO_EXTENSION))
                        : pathinfo($fileName, PATHINFO_EXTENSION),
                    'file_size' => $exists ? (int) Storage::disk('local')->size($path) : null,
                    'download_url' => URL::temporarySignedRoute(
                        'partner-portal.statement-attachment-download',
                        $expiresAt,
                        [
                            'investor_id' => $investorId,
                            'statement_of_account' => $statement->id,
                            'index' => (int) $index,
                        ]
                    ),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function normalizeAttachmentPaths(mixed $rawPaths): array
    {
        if (is_array($rawPaths)) {
            return array_values(array_filter($rawPaths, fn ($path) => is_string($path) && trim($path) !== ''));
        }

        if (! is_string($rawPaths) || trim($rawPaths) === '') {
            return [];
        }

        $decoded = json_decode($rawPaths, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter($decoded, fn ($path) => is_string($path) && trim($path) !== ''));
        }

        return [trim($rawPaths)];
    }

    protected function extractAttachmentOriginalName(string $path): string
    {
        $baseName = basename($path);
        $markerPos = strpos($baseName, '__');

        if ($markerPos === false) {
            return $baseName;
        }

        $original = substr($baseName, $markerPos + 2);

        return $original !== '' ? $original : $baseName;
    }
}
