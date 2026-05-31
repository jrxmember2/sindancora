<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\PlanLimitService;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly PlanLimitService $planLimitService,
        private readonly StorageService $storageService,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        $stats = [];
        $storageStats = [];

        if ($tenant) {
            $stats = $this->planLimitService->getUsageSummary($tenant);
            $storageStats = $this->storageService->getUsageStats($tenant);
        }

        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'storage' => $storageStats,
        ]);
    }
}
