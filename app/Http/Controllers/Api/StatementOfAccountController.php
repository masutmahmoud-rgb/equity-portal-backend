<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Models\StatementOfAccount;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StatementOfAccountController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => StatementOfAccount::with(['company', 'investment.investor', 'investor'])->latest('created_at')->get(),
        ]);
    }

    public function withdrawalsIndex()
    {
        return response()->json([
            'data' => StatementOfAccount::with(['company', 'investment.investor', 'investor'])
                ->where('transaction_type', StatementOfAccount::TYPE_WITHDRAWAL)
                ->latest('created_at')
                ->get(),
        ]);
    }

    /**
     * Declare a company-wide dividend and create per-investor statement records.
     */
    public function declareDividend(Request $request)
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'total_amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'notes' => 'nullable|string',
            'status' => ['nullable', 'string', Rule::in(StatementOfAccount::STATUSES)],
        ]);

        $created = $this->createCompanyWideDividendStatements([
            'company_id' => $data['company_id'],
            'amount' => $data['total_amount'],
            'status' => $data['status'] ?? StatementOfAccount::STATUS_PENDING,
            'transaction_date' => $data['transaction_date'],
            'description' => $data['notes'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return response()->json(['data' => $created], 201);
    }

    public function store(Request $request)
    {
        $this->normalizeDepositDirection($request);
        $validated = $request->validate($this->validationRules($request));
        $validated = $this->resolveInvestmentId($validated);
        $this->validateAttachments($validated);

        if ($this->shouldDistributeDividend($request, $validated)) {
            $created = $this->createCompanyWideDividendStatements($validated);

            return response()->json([
                'data' => $created,
            ], 201);
        }

        // Attachments are allowed for manual statement entries (Withdrawal/Deposit),
        // but not for generated dividend declaration payloads.
        if ($request->hasFile('attachments')) {
            if (! in_array(($validated['transaction_type'] ?? null), [StatementOfAccount::TYPE_WITHDRAWAL, StatementOfAccount::TYPE_DEPOSIT], true)) {
                return response()->json(['message' => 'Attachments are only allowed for withdrawal or addition transactions.'], 422);
            }

            $paths = [];
            foreach ($request->file('attachments') as $file) {
                $paths[] = $file->storeAs('statement_of_accounts', $this->buildStoredAttachmentName($file), 'local');
            }

            $validated['attachment_paths'] = $paths;
        }

        $statement = StatementOfAccount::create($validated);

        return response()->json([
            'data' => $this->loadRelations($statement),
        ], 201);
    }

    public function withdrawalsStore(Request $request)
    {
        $request->merge([
            'transaction_type' => StatementOfAccount::TYPE_WITHDRAWAL,
        ]);

        return $this->store($request);
    }

    public function show(StatementOfAccount $statement_of_account)
    {
        return response()->json([
            'data' => $this->loadRelations($statement_of_account),
        ]);
    }

    public function withdrawalsShow(StatementOfAccount $statement_of_account)
    {
        $this->ensureWithdrawal($statement_of_account);

        return $this->show($statement_of_account);
    }

    public function update(Request $request, StatementOfAccount $statement_of_account)
    {
        $this->normalizeDepositDirection($request, $statement_of_account);
        $validated = $request->validate($this->validationRules($request));
        $validated = $this->resolveInvestmentId($validated, $statement_of_account);
        $this->validateAttachments($validated, $statement_of_account);

        // Keep existing attachments unless new ones are uploaded
        if ($request->hasFile('attachments')) {
            $transactionType = $validated['transaction_type'] ?? $statement_of_account->transaction_type;
            if (! in_array($transactionType, [StatementOfAccount::TYPE_WITHDRAWAL, StatementOfAccount::TYPE_DEPOSIT], true)) {
                return response()->json(['message' => 'Attachments are only allowed for withdrawal or addition transactions.'], 422);
            }

            $existingPaths = $statement_of_account->attachment_paths ?? [];
            if (! is_array($existingPaths)) {
                $existingPaths = [];
            }

            foreach ($request->file('attachments') as $file) {
                $existingPaths[] = $file->storeAs('statement_of_accounts', $this->buildStoredAttachmentName($file), 'local');
            }

            $validated['attachment_paths'] = $existingPaths;
        }

        $statement_of_account->update($validated);

        return response()->json([
            'data' => $this->loadRelations($statement_of_account),
        ]);
    }

    public function withdrawalsUpdate(Request $request, StatementOfAccount $statement_of_account)
    {
        $this->ensureWithdrawal($statement_of_account);
        $request->merge([
            'transaction_type' => StatementOfAccount::TYPE_WITHDRAWAL,
        ]);

        return $this->update($request, $statement_of_account);
    }

    public function attachment(StatementOfAccount $statement_of_account)
    {
        return response()->json(['message' => 'Use the attachments index endpoint with an attachment index.'], 400);
    }

    public function downloadAttachment(StatementOfAccount $statement_of_account, $index)
    {
        $paths = $this->normalizeAttachmentPaths($statement_of_account->attachment_paths ?? []);
        if (! is_array($paths) || ! isset($paths[(int) $index])) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        $path = $paths[(int) $index];
        if (! Storage::disk('local')->exists($path)) {
            return response()->json(['message' => 'Attachment not found.'], 404);
        }

        return Storage::disk('local')->download($path);
    }

    public function destroy(StatementOfAccount $statement_of_account)
    {
        if (! in_array($statement_of_account->transaction_type, [StatementOfAccount::TYPE_WITHDRAWAL, StatementOfAccount::TYPE_DEPOSIT], true)) {
            // Dividend statements are generated records and stay read-only.
            return response()->json([
                'message' => 'Dividend statements are read-only and cannot be deleted directly.',
            ], 403);
        }

        $this->deleteStatementWithAttachments($statement_of_account);

        return response()->json([
            'message' => 'Statement entry deleted successfully.',
        ]);
    }

    public function withdrawalsDestroy(StatementOfAccount $statement_of_account)
    {
        $this->ensureWithdrawal($statement_of_account);

        $this->deleteStatementWithAttachments($statement_of_account);

        return response()->json([
            'message' => 'Withdrawal deleted successfully.',
        ]);
    }

    protected function validationRules(Request $request): array
    {
        $required = $request->isMethod('patch') ? 'sometimes' : 'required';

        $rules = [
            'company_id' => [$required, 'integer', Rule::exists('companies', 'id')],
            'investment_id' => ['nullable', 'integer', Rule::exists('investments', 'id')],
            'investor_id' => [$required, 'integer', Rule::exists('investors', 'id')],
            'transaction_type' => [$required, 'string', Rule::in(StatementOfAccount::TRANSACTION_TYPES)],
            'entry_direction' => ['nullable', 'string', Rule::in(StatementOfAccount::ENTRY_DIRECTIONS)],
            'amount' => [$required, 'numeric', 'min:0.01'],
            'status' => [$required, 'string', Rule::in(StatementOfAccount::STATUSES)],
            'transaction_date' => [$required, 'date'],
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'transfer_reference' => 'nullable|string',
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:20480'],
            'attachment_paths' => ['nullable', 'array'],
            'attachment_paths.*' => ['string'],
        ];

        return $rules;
    }

    protected function normalizeDepositDirection(Request $request, ?StatementOfAccount $existing = null): void
    {
        $transactionType = $request->input('transaction_type', $existing?->transaction_type);
        $entryDirection = $request->input('entry_direction');

        if ($transactionType === StatementOfAccount::TYPE_DEPOSIT && empty($entryDirection)) {
            // Addition/Deposit entries should increase partner balance by default.
            $request->merge([
                'entry_direction' => StatementOfAccount::DIRECTION_CREDIT,
            ]);
        }
    }

    public function validateAttachments(array $validated, ?StatementOfAccount $existing = null): void
    {
        $transactionType = $validated['transaction_type'] ?? $existing?->transaction_type;
        if (
            request()->hasFile('attachments')
            && ! in_array($transactionType, [StatementOfAccount::TYPE_WITHDRAWAL, StatementOfAccount::TYPE_DEPOSIT], true)
        ) {
            throw new \Illuminate\Validation\ValidationException(
                \Illuminate\Validation\Validator::make([], []),
                abort(422, 'Attachments are only allowed for withdrawal or addition transactions.')
            );
        }
    }

    protected function loadRelations(StatementOfAccount $statement): StatementOfAccount
    {
        return $statement->load(['company', 'investment.investor', 'investor']);
    }

    protected function ensureWithdrawal(StatementOfAccount $statement): void
    {
        if ($statement->transaction_type !== StatementOfAccount::TYPE_WITHDRAWAL) {
            abort(404, 'Withdrawal transaction not found.');
        }
    }

    /**
     * Resolve investment automatically from company + investor when not provided.
     */
    protected function resolveInvestmentId(array $validated, ?StatementOfAccount $existing = null): array
    {
        if (! empty($validated['investment_id'])) {
            return $validated;
        }

        $companyId = $validated['company_id'] ?? $existing?->company_id;
        $investorId = $validated['investor_id'] ?? $existing?->investor_id;

        if (! $companyId || ! $investorId) {
            return $validated;
        }

        $investment = Investment::where('company_id', $companyId)
            ->where('investor_id', $investorId)
            ->where('status', Investment::STATUS_ACTIVE)
            ->latest('created_at')
            ->first();

        if (! $investment) {
            abort(422, 'No active investment found for the selected company and partner.');
        }

        $validated['investment_id'] = $investment->id;

        return $validated;
    }

    protected function buildStoredAttachmentName(UploadedFile $file): string
    {
        $originalName = str_replace(['\\', '/'], '_', $file->getClientOriginalName());
        $prefix = now()->format('YmdHis') . '_' . Str::random(10);

        return $prefix . '__' . $originalName;
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

    protected function deleteStatementWithAttachments(StatementOfAccount $statement): void
    {
        $paths = $this->normalizeAttachmentPaths($statement->attachment_paths ?? []);

        foreach ($paths as $path) {
            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }

        $statement->delete();
    }

    protected function shouldDistributeDividend(Request $request, array $validated): bool
    {
        if (($validated['transaction_type'] ?? null) !== StatementOfAccount::TYPE_DIVIDEND) {
            return false;
        }

        if (! $request->has('distribute_to_all_partners')) {
            return true;
        }

        return filter_var($request->input('distribute_to_all_partners'), FILTER_VALIDATE_BOOLEAN);
    }

    protected function createCompanyWideDividendStatements(array $payload): array
    {
        $investments = \App\Models\Investment::where('company_id', $payload['company_id'])
            ->where('status', Investment::STATUS_ACTIVE)
            ->get();

        $totalInvested = $investments->sum(fn ($row) => (float) $row->amount);

        if ($totalInvested <= 0 || $investments->isEmpty()) {
            abort(422, 'No active investments found for company or total invested is zero.');
        }

        $created = [];
        $remaining = round((float) $payload['amount'], 2);
        $count = $investments->count();

        foreach ($investments->values() as $index => $investment) {
            if ($index === $count - 1) {
                // Last row receives remainder to avoid rounding drift.
                $share = round($remaining, 2);
            } else {
                $share = round(((float) $investment->amount / $totalInvested) * (float) $payload['amount'], 2);
                $remaining = round($remaining - $share, 2);
            }

            $record = StatementOfAccount::create([
                'company_id' => (int) $payload['company_id'],
                'investment_id' => $investment->id,
                'investor_id' => $investment->investor_id,
                'transaction_type' => StatementOfAccount::TYPE_DIVIDEND,
                'entry_direction' => null,
                'amount' => $share,
                'status' => $payload['status'] ?? StatementOfAccount::STATUS_PENDING,
                'transaction_date' => $payload['transaction_date'],
                'description' => $payload['description'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ]);

            $created[] = $this->loadRelations($record);
        }

        return $created;
    }
}
