<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Notification::query()->latest('publish_date')->latest('created_at');

        if (request()->filled('notification_type')) {
            $query->where('notification_type', request('notification_type'));
        }

        if (request()->boolean('active_only')) {
            $query->active();
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->storeRules());

        $notification = Notification::create($validated);

        return response()->json([
            'data' => $notification,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Notification $notification)
    {
        return response()->json([
            'data' => $notification,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Notification $notification)
    {
        $validated = $request->validate($this->updateRules());

        $notification->update($validated);

        return response()->json([
            'data' => $notification,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Notification $notification)
    {
        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully.',
        ]);
    }

    protected function storeRules(): array
    {
        return [
            'notification_type' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'important_notes' => ['nullable', 'string'],
            'target_investor_id' => ['nullable', 'exists:investors,id'],
            'valuation_id' => ['nullable', 'exists:portfolio_valuations,id'],
            'publish_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:publish_date'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'notification_type' => ['sometimes', 'string', 'max:100'],
            'title' => ['sometimes', 'string', 'max:255'],
            'message' => ['sometimes', 'string'],
            'important_notes' => ['nullable', 'string'],
            'target_investor_id' => ['nullable', 'exists:investors,id'],
            'valuation_id' => ['nullable', 'exists:portfolio_valuations,id'],
            'publish_date' => ['sometimes', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:publish_date'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
