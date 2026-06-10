<?php

namespace App\Http\Middleware;

use App\Services\StorageService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        $user = $request->user();
        $plan = $tenant?->activePlan();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'is_super_admin' => $user->is_super_admin,
                    'permissions' => $user->permissionNames(),
                    'sign_messages' => (bool) $user->sign_messages,
                ] : null,
            ],
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'brand_name' => $tenant->getBrandName(),
                'logo_url' => $tenant->getLogoUrl(),
                'primary_color' => $tenant->getPrimaryColor(),
                'storage' => (function () use ($tenant) {
                    $usage = app(StorageService::class)->cachedUsageStats($tenant);

                    return [
                        'percentage_used' => $usage['percentage_used'],
                        'is_near_limit' => $usage['is_near_limit'],
                    ];
                })(),
                'plan' => $plan ? [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'display_name' => $plan->display_name,
                    'modules' => $plan->modules()
                        ->where('enabled', true)
                        ->pluck('module')
                        ->values()
                        ->all(),
                ] : null,
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'notifications' => $user ? [
                'unread_count' => fn () => $user->unreadNotifications()->count(),
                'recent' => fn () => $user->notifications()->latest()->take(8)->get()->map(fn ($n) => [
                    'id' => $n->id,
                    'data' => $n->data,
                    'read_at' => $n->read_at,
                    'created_at' => $n->created_at,
                ]),
            ] : null,
        ];
    }
}
