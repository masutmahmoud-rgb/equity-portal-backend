<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\InvestorController;
use App\Http\Controllers\Api\InvestmentController;
use App\Http\Controllers\Api\DividendController;
use App\Http\Controllers\Api\StatementOfAccountController;
use App\Http\Controllers\Api\InvestmentStatementController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PartnerPortalController;
use App\Http\Controllers\Api\InvestmentTransactionController;
use App\Http\Controllers\Api\FinancialDataController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PortfolioValuationController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\CurrencySettingController;
use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\PartnerRetainedEarningsController;
use App\Http\Controllers\Api\OwnershipRegisterController;
use App\Http\Controllers\Api\PartnerOwnershipController;
use App\Http\Controllers\Api\CapitalRaiseController;

Route::apiResource('companies', CompanyController::class);
Route::apiResource('investors', InvestorController::class);
Route::apiResource('investments', InvestmentController::class);
Route::apiResource('investments.transactions', InvestmentTransactionController::class);
Route::apiResource('dividends', DividendController::class);
Route::apiResource('statement-of-accounts', StatementOfAccountController::class);
// Backward-compatible aliases for legacy frontend API paths.
Route::get('statement-of-account', [StatementOfAccountController::class, 'index'])->name('statement-of-account.index');
Route::post('statement-of-account', [StatementOfAccountController::class, 'store'])->name('statement-of-account.store');
Route::get('statement-of-account/{statement_of_account}', [StatementOfAccountController::class, 'show'])->name('statement-of-account.show');
Route::match(['put', 'patch'], 'statement-of-account/{statement_of_account}', [StatementOfAccountController::class, 'update'])->name('statement-of-account.update');
Route::delete('statement-of-account/{statement_of_account}', [StatementOfAccountController::class, 'destroy'])->name('statement-of-account.destroy');
Route::get('statement', [StatementOfAccountController::class, 'index'])->name('statement.index');
Route::apiResource('statement-entries', StatementOfAccountController::class)
    ->parameters(['statement-entries' => 'statement_of_account']);
Route::apiResource('investment-statements', InvestmentStatementController::class, ['only' => ['index', 'show']]);
Route::apiResource('financial-data', FinancialDataController::class);
Route::apiResource('notifications', NotificationController::class);
Route::apiResource('portfolio-valuations', PortfolioValuationController::class);
Route::apiResource('capital-raises', CapitalRaiseController::class);
Route::post('capital-raises/review', [CapitalRaiseController::class, 'review'])->name('capital-raises.review');
Route::post('capital-raises/{capital_raise}/publish', [CapitalRaiseController::class, 'publish'])->name('capital-raises.publish');
Route::apiResource('capital-events/capital-raises', CapitalRaiseController::class)
    ->parameters(['capital-raises' => 'capital_raise'])
    ->only(['index', 'store', 'show', 'update', 'destroy']);
Route::post('capital-events/capital-raises/review', [CapitalRaiseController::class, 'review'])->name('capital-events.capital-raises.review');
Route::post('capital-events/capital-raises/{capital_raise}/publish', [CapitalRaiseController::class, 'publish'])->name('capital-events.capital-raises.publish');
Route::apiResource('ownership-registers', OwnershipRegisterController::class)->only(['index', 'store', 'show']);
Route::post('ownership-registers/{ownership_register}/publish', [OwnershipRegisterController::class, 'publish'])->name('ownership-registers.publish');
Route::post('ownership-registers/manual-set', [OwnershipRegisterController::class, 'manualSet'])->name('ownership-registers.manual-set');
Route::get('ownership-records', [OwnershipRegisterController::class, 'index'])->name('ownership-records.index');
Route::post('ownership-records', [OwnershipRegisterController::class, 'store'])->name('ownership-records.store');
Route::get('ownership-records/{ownership_register}', [OwnershipRegisterController::class, 'show'])->name('ownership-records.show');
Route::post('ownership-records/{ownership_register}/publish', [OwnershipRegisterController::class, 'publish'])->name('ownership-records.publish');
Route::post('ownership-records/manual-set', [OwnershipRegisterController::class, 'manualSet'])->name('ownership-records.manual-set');
Route::apiResource('announcements', AnnouncementController::class);
Route::get('currency-settings', [CurrencySettingController::class, 'index']);
Route::put('currency-settings', [CurrencySettingController::class, 'update']);
Route::apiResource('exchange-rates', ExchangeRateController::class);
Route::get('portfolio-valuations/latest/{investor_id}', [PortfolioValuationController::class, 'latestForPartner'])->name('portfolio-valuations.latest-for-partner');
Route::get('statement-of-accounts/{statement_of_account}/attachment', [StatementOfAccountController::class, 'attachment'])->name('statement-of-accounts.attachment');
Route::get('statement-of-accounts/{statement_of_account}/attachments/{index}', [StatementOfAccountController::class, 'downloadAttachment'])->name('statement-of-accounts.downloadAttachment');
Route::post('statement-of-accounts/declarations/dividend', [StatementOfAccountController::class, 'declareDividend'])->name('statement-of-accounts.declareDividend');
Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
Route::get('ledger/investor/{investor_id}', [LedgerController::class, 'partnerLedger'])->name('ledger.partnerLedger');
Route::get('ledger/all', [LedgerController::class, 'allLedgers'])->name('ledger.allLedgers');

// Authentication routes (UAT testing)
Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
Route::post('auth/verify-credentials', [AuthController::class, 'verifyCredentials'])->name('auth.verifyCredentials');

Route::prefix('partner')->middleware('auth.basic')->group(function () {
    Route::get('retained-earnings', [PartnerRetainedEarningsController::class, 'index'])->name('partner.retained-earnings');
    Route::get('ownership', [PartnerOwnershipController::class, 'currentAuthenticated'])->name('partner.ownership.current');
    Route::get('ownership-history', [PartnerOwnershipController::class, 'historyAuthenticated'])->name('partner.ownership.history');
});

// Partner Portal routes (authenticated partner data only)
Route::prefix('partner-portal/{investor_id}')->group(function () {
    Route::get('profile', [PartnerPortalController::class, 'profile'])->name('partner-portal.profile');
    Route::get('companies', [PartnerPortalController::class, 'companies'])->name('partner-portal.companies');
    Route::get('investments', [PartnerPortalController::class, 'investments'])->name('partner-portal.investments');
    Route::get('portfolio', [PartnerPortalController::class, 'portfolio'])->name('partner-portal.portfolio');
    Route::get('investment-statement', [PartnerPortalController::class, 'investmentStatement'])->name('partner-portal.investment-statement');
    Route::get('statement-of-account', [PartnerPortalController::class, 'statementOfAccount'])->name('partner-portal.statement-of-account');
    Route::get('statement-of-account/{statement_of_account}/attachments/{index}/download', [PartnerPortalController::class, 'downloadStatementAttachment'])
        ->middleware('signed')
        ->name('partner-portal.statement-attachment-download');
    Route::get('portfolio-summary', [PartnerPortalController::class, 'portfolioSummary'])->name('partner-portal.portfolio-summary');
    Route::get('financial-data', [PartnerPortalController::class, 'financialData'])->name('partner-portal.financial-data');
    Route::get('notifications', [PartnerPortalController::class, 'notifications'])->name('partner-portal.notifications');
    Route::get('latest-valuation', [PartnerPortalController::class, 'latestValuation'])->name('partner-portal.latest-valuation');
    Route::get('valuation-history', [PartnerPortalController::class, 'valuationHistory'])->name('partner-portal.valuation-history');
    Route::get('valuations/{valuation_id}', [PartnerPortalController::class, 'valuationShow'])->name('partner-portal.valuation-show');
    Route::get('announcements', [PartnerPortalController::class, 'announcements'])->name('partner-portal.announcements');
    Route::get('ownership', [PartnerOwnershipController::class, 'current'])->middleware('auth.basic')->name('partner-portal.ownership.current');
    Route::get('ownership-history', [PartnerOwnershipController::class, 'history'])->middleware('auth.basic')->name('partner-portal.ownership.history');
});