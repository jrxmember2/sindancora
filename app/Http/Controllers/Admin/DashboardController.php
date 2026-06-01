<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_tenants' => Tenant::count(),
                'active_tenants' => Tenant::where('status', 'active')->count(),
                'suspended_tenants' => Tenant::where('status', 'suspended')->count(),
                'total_users' => User::whereNotNull('tenant_id')->count(),
                'total_plans' => Plan::active()->count(),
            ],
        ]);
    }
}
