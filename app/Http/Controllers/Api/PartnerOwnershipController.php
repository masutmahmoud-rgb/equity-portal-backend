<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investor;
use App\Models\OwnershipRegister;
use App\Models\OwnershipRegisterItem;
use Illuminate\Http\Request;

class PartnerOwnershipController extends Controller
{
    /**
     * Current ownership snapshot for every company where the partner has a stake.
     */
    public function current(Request $request, $investor_id)
    {
        $investor = $this->resolvePartner($request, (int) $investor_id);

        if ($investor instanceof \Illuminate\Http\JsonResponse) {
            return $investor;
        }

        return response()->json([
            'data' => [
                'partner' => [
                    'id' => $investor->id,
                    'name' => $investor->name,
                ],
                'ownerships' => $this->currentOwnershipRows((int) $investor->id),
            ],
        ]);
    }

    public function currentAuthenticated(Request $request)
    {
        $investor = $this->resolvePartnerFromAuthenticatedUser($request);

        if ($investor instanceof \Illuminate\Http\JsonResponse) {
            return $investor;
        }

        return response()->json([
            'data' => [
                'partner' => [
                    'id' => $investor->id,
                    'name' => $investor->name,
                ],
                'ownerships' => $this->currentOwnershipRows((int) $investor->id),
            ],
        ]);
    }

    /**
     * Ownership snapshot history for a partner.
     */
    public function history(Request $request, $investor_id)
    {
        $investor = $this->resolvePartner($request, (int) $investor_id);

        if ($investor instanceof \Illuminate\Http\JsonResponse) {
            return $investor;
        }

        return response()->json([
            'data' => [
                'partner' => [
                    'id' => $investor->id,
                    'name' => $investor->name,
                ],
                'history' => $this->historyRows($request, (int) $investor->id),
            ],
        ]);
    }

    public function historyAuthenticated(Request $request)
    {
        $investor = $this->resolvePartnerFromAuthenticatedUser($request);

        if ($investor instanceof \Illuminate\Http\JsonResponse) {
            return $investor;
        }

        return response()->json([
            'data' => [
                'partner' => [
                    'id' => $investor->id,
                    'name' => $investor->name,
                ],
                'history' => $this->historyRows($request, (int) $investor->id),
            ],
        ]);
    }

    protected function currentOwnershipRows(int $investorId)
    {
        $items = OwnershipRegisterItem::query()
            ->with(['register.company', 'register.valuation'])
            ->where('investor_id', $investorId)
            ->whereHas('register', function ($query) {
                $query->where('status', OwnershipRegister::STATUS_PUBLISHED)
                    ->where('is_current', true);
            })
            ->get();

        return $items->map(function (OwnershipRegisterItem $item) {
            return [
                'company' => $item->register?->company ? [
                    'id' => $item->register->company->id,
                    'name' => $item->register->company->name,
                ] : null,
                'ownership_percentage' => (float) $item->ownership_percentage,
                'effective_date' => optional($item->register?->effective_date)->toDateString(),
                'valuation_period' => $item->register?->valuation?->valuation_period,
                'version' => $item->register?->version,
            ];
        })->values();
    }

    protected function historyRows(Request $request, int $investorId)
    {
        $itemsQuery = OwnershipRegisterItem::query()
            ->with(['register.company', 'register.valuation'])
            ->where('investor_id', $investorId)
            ->whereHas('register', function ($query) {
                $query->where('status', OwnershipRegister::STATUS_PUBLISHED);
            });

        if ($request->filled('company_id')) {
            $companyId = (int) $request->query('company_id');
            $itemsQuery->whereHas('register', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            });
        }

        $items = $itemsQuery
            ->get()
            ->sortByDesc(function (OwnershipRegisterItem $item) {
                return [
                    optional($item->register?->effective_date)->timestamp ?? 0,
                    optional($item->register?->published_at)->timestamp ?? 0,
                    (int) ($item->register?->version ?? 0),
                ];
            })
            ->values();

        return $items->map(function (OwnershipRegisterItem $item) {
            return [
                'company' => $item->register?->company ? [
                    'id' => $item->register->company->id,
                    'name' => $item->register->company->name,
                ] : null,
                'ownership_percentage' => (float) $item->ownership_percentage,
                'effective_date' => optional($item->register?->effective_date)->toDateString(),
                'valuation_period' => $item->register?->valuation?->valuation_period,
                'version' => $item->register?->version,
                'published_at' => optional($item->register?->published_at)->toIso8601String(),
            ];
        })->values();
    }

    protected function resolvePartner(Request $request, int $investorId)
    {
        $investor = Investor::find($investorId);

        if (! $investor) {
            return response()->json([
                'message' => 'Partner not found',
            ], 404);
        }

        $authInvestor = $this->resolvePartnerFromAuthenticatedUser($request);
        if ($authInvestor instanceof \Illuminate\Http\JsonResponse) {
            return $authInvestor;
        }

        if ((int) $authInvestor->id !== (int) $investor->id) {
            return response()->json([
                'message' => 'Forbidden. You can only access your own ownership records.',
            ], 403);
        }

        return $investor;
    }

    protected function resolvePartnerFromAuthenticatedUser(Request $request)
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

        return $investor;
    }
}
