<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Investor;
use App\Models\Investment;
use App\Models\StatementOfAccount;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        return response()->json([
            'statistics' => $this->getStatistics(),
            'investment_distribution' => $this->getInvestmentDistribution(),
            'monthly_cash_flow' => $this->getMonthlyCashFlow(),
            'recent_activity' => $this->getRecentActivity(),
            'pending_actions' => $this->getPendingActions(),
        ]);
    }

    private function getStatistics()
    {
        // Get all statistics using efficient queries
        $totalCompanies = Company::count();
        $totalPartners = Investor::count();
        
        $activeInvestments = Investment::where('status', 'Active')->count();
        $totalInvestedCapital = Investment::where('status', 'Active')->sum('amount') ?? 0;

        $dividendsPaid = StatementOfAccount::where('transaction_type', 'Dividend')
            ->where('status', 'Paid')
            ->sum('amount') ?? 0;

        $pendingDividends = StatementOfAccount::where('transaction_type', 'Dividend')
            ->where('status', 'Pending')
            ->sum('amount') ?? 0;

        $withdrawalsTotal = StatementOfAccount::where('transaction_type', 'Withdrawal')
            ->sum('amount') ?? 0;

        $pendingWithdrawals = StatementOfAccount::where('transaction_type', 'Withdrawal')
            ->where('status', 'Pending')
            ->sum('amount') ?? 0;

        return [
            'total_companies' => $totalCompanies,
            'total_partners' => $totalPartners,
            'active_investments' => $activeInvestments,
            'total_invested_capital' => floatval($totalInvestedCapital),
            'dividends_paid' => floatval($dividendsPaid),
            'pending_dividends' => floatval($pendingDividends),
            'withdrawals_total' => floatval($withdrawalsTotal),
            'pending_withdrawals' => floatval($pendingWithdrawals),
        ];
    }

    private function getInvestmentDistribution()
    {
        // Get investment distribution by company
        $distribution = Investment::select('companies.name', DB::raw('COUNT(*) as count'), DB::raw('SUM(investments.amount) as total_amount'))
            ->join('companies', 'investments.company_id', '=', 'companies.id')
            ->where('investments.status', 'Active')
            ->groupBy('companies.id', 'companies.name')
            ->get()
            ->map(fn($item) => [
                'company' => $item->name,
                'count' => $item->count,
                'total_amount' => floatval($item->total_amount),
            ]);

        return $distribution;
    }

    private function getMonthlyCashFlow()
    {
        // Get monthly dividends and withdrawals for the last 12 months
        $startDate = now()->subMonths(11)->startOfMonth();

        $driver = DB::connection()->getDriverName();
        $monthExpression = $driver === 'sqlite'
            ? 'strftime("%Y-%m", transaction_date)'
            : 'DATE_FORMAT(transaction_date, "%Y-%m")';

        $dividends = StatementOfAccount::select(
            DB::raw("{$monthExpression} as month"),
            DB::raw('SUM(amount) as total')
        )
            ->where('transaction_type', 'Dividend')
            ->where('status', 'Paid')
            ->where('transaction_date', '>=', $startDate)
            ->groupBy(DB::raw($monthExpression))
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $withdrawals = StatementOfAccount::select(
            DB::raw("{$monthExpression} as month"),
            DB::raw('SUM(amount) as total')
        )
            ->where('transaction_type', 'Withdrawal')
            ->where('status', 'Paid')
            ->where('transaction_date', '>=', $startDate)
            ->groupBy(DB::raw($monthExpression))
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        // Build combined monthly data
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->subMonths($i)->format('Y-m');
            $months[] = [
                'month' => $month,
                'dividends' => floatval($dividends[$month]->total ?? 0),
                'withdrawals' => floatval($withdrawals[$month]->total ?? 0),
            ];
        }

        return $months;
    }

    private function getRecentActivity()
    {
        // Get recent transactions (last 10)
        $activity = StatementOfAccount::select('id', 'company_id', 'investor_id', 'transaction_type', 'amount', 'status', 'transaction_date', 'created_at')
            ->with(['company:id,name', 'investor:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'company' => $item->company->name ?? 'Unknown',
                'partner' => $item->investor->name ?? 'Unknown',
                'type' => $item->transaction_type,
                'amount' => floatval($item->amount),
                'status' => $item->status,
                'date' => $item->transaction_date->format('Y-m-d'),
                'timestamp' => $item->created_at ? $item->created_at->toIso8601String() : now()->toIso8601String(),
            ]);

        return $activity;
    }

    private function getPendingActions()
    {
        // Get pending dividends and withdrawals
        $pendingDividends = StatementOfAccount::select('id', 'company_id', 'investor_id', 'amount', 'transaction_date')
            ->where('transaction_type', 'Dividend')
            ->where('status', 'Pending')
            ->with(['company:id,name', 'investor:id,name'])
            ->orderBy('transaction_date')
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'type' => 'Dividend Payment',
                'company' => $item->company->name ?? 'Unknown',
                'partner' => $item->investor->name ?? 'Unknown',
                'amount' => floatval($item->amount),
                'date' => $item->transaction_date->format('Y-m-d'),
                'action' => 'pay_dividend',
            ]);

        $pendingWithdrawals = StatementOfAccount::select('id', 'company_id', 'investor_id', 'amount', 'transaction_date', 'bank_name', 'transfer_reference')
            ->where('transaction_type', 'Withdrawal')
            ->where('status', 'Pending')
            ->with(['company:id,name', 'investor:id,name'])
            ->orderBy('transaction_date')
            ->get()
            ->map(fn($item) => [
                'id' => $item->id,
                'type' => 'Withdrawal Approval',
                'company' => $item->company->name ?? 'Unknown',
                'partner' => $item->investor->name ?? 'Unknown',
                'amount' => floatval($item->amount),
                'bank' => $item->bank_name ?? null,
                'reference' => $item->transfer_reference ?? null,
                'date' => $item->transaction_date->format('Y-m-d'),
                'action' => 'approve_withdrawal',
            ]);

        // Combine and sort by date
        $allActions = $pendingDividends->concat($pendingWithdrawals)
            ->sortBy('date')
            ->values();

        return $allActions;
    }
}
