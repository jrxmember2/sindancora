<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use App\Services\Mail\TenantMailManager;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        \App\Models\Tenant::class => TenantPolicy::class,
        \App\Models\User::class => UserPolicy::class,
    ];

    public function register(): void
    {
        $this->app->singleton(TenantMailManager::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
    URL::forceScheme('https');
}
        Vite::prefetch(concurrency: 3);

        Gate::define('viewHorizon', fn (User $user) => $user->is_super_admin);

        $this->registerPolicies();
        $this->registerTenantMail();
    }

    /**
     * E-mail por tenant: nas filas, aplica o SMTP do tenant do job antes de processar e limpa depois;
     * após cada envio, copia a mensagem na pasta Enviados do tenant (IMAP), se configurado.
     */
    protected function registerTenantMail(): void
    {
        Queue::before(function (JobProcessing $event) {
            app(TenantMailManager::class)->apply($this->resolveJobTenant($event));
        });

        Queue::after(fn () => app(TenantMailManager::class)->reset());

        Event::listen(MessageSent::class, function (MessageSent $event) {
            app(TenantMailManager::class)->copyToSent($event->sent->getOriginalMessage()->toString());
        });
    }

    /** Tenta descobrir o tenant_id de um job de e-mail/notificação (mailable/notification com tenantId plano). */
    protected function resolveJobTenant(JobProcessing $event): ?string
    {
        $command = $event->job->payload()['data']['command'] ?? null;
        if (! is_string($command)) {
            return null;
        }

        try {
            $obj = unserialize($command);
        } catch (\Throwable) {
            return null;
        }

        $carrier = $obj->mailable ?? $obj->notification ?? null;

        return is_object($carrier) && isset($carrier->tenantId) ? $carrier->tenantId : null;
    }

    protected function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
