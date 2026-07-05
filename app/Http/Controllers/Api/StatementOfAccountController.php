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

        $investments = \App\Models\Investment::where('company_id', $data['company_id'])->get();
        $totalInvested = $investments->sum(function ($i) { return (float) $i->amount; });

        if ($totalInvested <= 0) {
            return response()->json(['message' => 'No investments found for company or total invested is zero.'], 422);
        }

        $created = [];
        $remaining = (float) $data['total_amount'];
        $count = $investments->count();
        $i = 0;

        foreach ($investments as $investment) {
            $i++;
            if ($i === $count) {
                // last one: assign remaining to avoid rounding loss
                $share = round($remaining, 2);
            } else {
                $share = round(((float) $investment->amount / $totalInvested) * (float) $data['total_amount'], 2);
                $remaining -= $share;
            }

            $record = StatementOfAccount::create([
                'company_id' => $data['company_id'],
                'investment_id' => $investment->id,
                'investor_id' => $investment->investor_id,
                'transaction_type' => StatementOfAccount::TYPE_DIVIDEND,
                'amount' => $share,
                'status' => $data['status'] ?? StatementOfAccount::STATUS_PENDING,
                'transaction_date' => $data['transaction_date'],
                'notes' => $data['notes'] ?? null,
            ]);

            $created[] = $this->loadRelations($record);
        }

        return response()->json(['data' => $created], 201);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->validationRules($request));
        $validated = $this->resolveInvestmentId($validated);
        $this->validateAttachments($validated);

        // attachments only allowed for withdrawals
        if ($request->hasFile('attachments')) {
            if (($validated['transaction_type'] ?? null) !== StatementOfAccount::TYPE_WITHDRAWAL) {
                return response()->json(['message' => 'Attachments are only allowed for withdrawal transactions.'], 422);
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
        $validated = $request->validate($this->validationRules($request));
        $validated = $this->resolveInvestmentId($validated, $statement_of_account);
        $this->validateAttachments($validated, $statement_of_account);

        // Keep existing attachments unless new ones are uploaded
        if ($request->hasFile('attachments')) {
            if (($validated['transaction_type'] ?? null) !== StatementOfAccount::TYPE_WITHDRAWAL) {
                return response()->json(['message' => 'Attachments are only allowed for withdrawal transactions.'], 422);
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
        // Statements are read-only and cannot be deleted directly
        // Delete the underlying transaction (Dividend/Withdrawal) instead
        return response()->json([
            'message' => 'Statements are read-only. Delete the underlying transaction instead.',
        ], 403);
    }

    public function withdrawalsDestroy(StatementOfAccount $statement_of_account)
    {
        $this->ensureWithdrawal($statement_of_account);

        return $this->destroy($statement_of_account);
    }

    protected function validationRules(Request $request): array
    {
        $required = $request->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'company_id' => [$required, 'integer', Rule::exists('companies', 'id')],
            'investment_id' => ['nullable', 'integer', Rule::exists('investments', 'id')],
            'investor_id' => [$required, 'integer', Rule::exists('investors', 'id')],
            'transaction_type' => [$required, 'string', Rule::in(StatementOfAccount::TRANSACTION_TYPES)],
            'amount' => [$required, 'numeric', 'min:0.01'],
            'status' => [$required, 'string', Rule::in(StatementOfAccount::STATUSES)],
            'transaction_date' => [$required, 'date'],
            'notes' => 'nullable|string',
            'bank_name' => 'nullable|string',
            'transfer_reference' => 'nullable|string',
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['nullable', 'file', 'max:20480'],
            'attachment_paths' => ['nullable', 'array'],
            'attachment_paths.*' => ['string'],
        ];
    }

    public function validateAttachments(array $validated, ?StatementOfAccount $existing = null): void
    {
        $transactionType = $validated['transaction_type'] ?? $existing?->transaction_type;
        if (request()->hasFile('attachments') && $transactionType !== StatementOfAccount::TYPE_WITHDRAWAL) {
            throw new \Illuminate\Validation\ValidationException(
                \Illuminate\Validation\Validator::make([], []),
                abort(422, 'Attachments are only allowed for withdrawal transactions.')
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
}
