<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $query = Announcement::query()->latest('publish_date')->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('audience_type')) {
            $query->where('audience_type', $request->query('audience_type'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge($this->normalizeInputFields($request->all()));
        $validated = $request->validate($this->storeRules());
        $validated = $this->handleAttachmentUpload($request, $validated);

        $announcement = Announcement::create($validated);

        return response()->json([
            'data' => $announcement,
        ], 201);
    }

    public function show(Announcement $announcement)
    {
        return response()->json([
            'data' => $announcement,
        ]);
    }

    public function update(Request $request, Announcement $announcement)
    {
        $request->merge($this->normalizeInputFields($request->all()));
        $validated = $request->validate($this->updateRules());
        $validated = $this->handleAttachmentUpload($request, $validated, $announcement);

        $announcement->update($validated);

        return response()->json([
            'data' => $announcement,
        ]);
    }

    public function destroy(Announcement $announcement)
    {
        if ($announcement->attachment) {
            Storage::disk('public')->delete($announcement->attachment);
        }

        $announcement->delete();

        return response()->json([
            'message' => 'Announcement deleted successfully.',
        ]);
    }

    protected function handleAttachmentUpload(Request $request, array $validated, ?Announcement $announcement = null): array
    {
        if (! $request->hasFile('attachment')) {
            return $validated;
        }

        if ($announcement && $announcement->attachment) {
            Storage::disk('public')->delete($announcement->attachment);
        }

        $validated['attachment'] = $request->file('attachment')->store('announcements', 'public');

        return $validated;
    }

    protected function normalizeInputFields(array $input): array
    {
        if (! array_key_exists('audience_type', $input) && array_key_exists('Audience', $input)) {
            $input['audience_type'] = $input['Audience'];
        }

        if (array_key_exists('audience_type', $input) && is_string($input['audience_type'])) {
            $normalizedAudience = strtolower(trim($input['audience_type']));
            $audienceMap = [
                strtolower(Announcement::AUDIENCE_ALL) => Announcement::AUDIENCE_ALL,
                strtolower(Announcement::AUDIENCE_COMPANY) => Announcement::AUDIENCE_COMPANY,
                strtolower(Announcement::AUDIENCE_PARTNER) => Announcement::AUDIENCE_PARTNER,
            ];

            if (isset($audienceMap[$normalizedAudience])) {
                $input['audience_type'] = $audienceMap[$normalizedAudience];
            }
        }

        if (array_key_exists('status', $input) && is_string($input['status'])) {
            $normalizedStatus = strtolower(trim($input['status']));
            $statusMap = [
                strtolower(Announcement::STATUS_DRAFT) => Announcement::STATUS_DRAFT,
                strtolower(Announcement::STATUS_PUBLISHED) => Announcement::STATUS_PUBLISHED,
            ];

            if (isset($statusMap[$normalizedStatus])) {
                $input['status'] = $statusMap[$normalizedStatus];
            }
        }

        unset($input['Audience']);

        return $input;
    }

    protected function storeRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'category' => ['required', 'string', 'max:100'],
            'audience_type' => ['required', Rule::in(Announcement::AUDIENCE_TYPES)],
            'company_id' => ['nullable', 'exists:companies,id', 'required_if:audience_type,Company'],
            'investor_id' => ['nullable', 'exists:investors,id', 'required_if:audience_type,Partner'],
            'attachment' => ['nullable', 'file', 'max:10240'],
            'publish_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:publish_date'],
            'status' => ['required', Rule::in(Announcement::STATUSES)],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'message' => ['sometimes', 'string'],
            'category' => ['sometimes', 'string', 'max:100'],
            'audience_type' => ['sometimes', Rule::in(Announcement::AUDIENCE_TYPES)],
            'company_id' => ['nullable', 'exists:companies,id', 'required_if:audience_type,Company'],
            'investor_id' => ['nullable', 'exists:investors,id', 'required_if:audience_type,Partner'],
            'attachment' => ['nullable', 'file', 'max:10240'],
            'publish_date' => ['sometimes', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:publish_date'],
            'status' => ['sometimes', Rule::in(Announcement::STATUSES)],
        ];
    }
}
