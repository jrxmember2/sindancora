<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        \App\Models\Tenant::class => TenantPolicy::class,
        \App\Models\User::class => UserPolicy::class,
    ];

    public function register(): void {}

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::define('viewHorizon', fn (User $user) => $user->is_super_admin);

        $this->registerPolicies();
    }

    protected function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
