<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PlanLimitService;
use App\Services\StorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(
        private readonly PlanLimitService $planLimitService,
        private readonly StorageService $storageService,
    ) {}

    public function current(Request $request): JsonResponse
    {
        $tenant = app('tenant');
        $plan = $tenant->activePlan();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'settings' => $tenant->settings,
                'plan' => $plan ? [
                    'name' => $plan->name,
                    'display_name' => $plan->display_name,
                    'price_monthly' => $plan->price_monthly,
                ] : null,
            ],
        ]);
    }

    public function usage(Request $request): JsonResponse
    {
        $tenant = app('tenant');

        return response()->json([
            'success' => true,
            'data' => $this->planLimitService->getUsageSummary($tenant),
        ]);
    }

    public function storageStats(Request $request): JsonResponse
    {
        $tenant = app('tenant');

        return response()->json([
            'success' => true,
            'data' => $this->storageService->getUsageStats($tenant),
        ]);
    }
}
