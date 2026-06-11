<?php

namespace App\Dashboard;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Registro central de widgets do dashboard modular.
 *
 * Registrado como singleton no DashboardServiceProvider, onde cada módulo cadastra
 * suas WidgetDefinition. A visibilidade efetiva (visibleFor) replica o gating do
 * menu lateral (AppLayout.tsx): permissão do usuário + módulo habilitado no plano.
 * Super admin enxerga tudo.
 */
class WidgetRegistry
{
    /** @var array<string, WidgetDefinition> */
    private array $widgets = [];

    public function register(WidgetDefinition $definition): self
    {
        $this->widgets[$definition->key] = $definition;

        return $this;
    }

    /**
     * @return Collection<string, WidgetDefinition>
     */
    public function all(): Collection
    {
        return collect($this->widgets)
            ->filter(fn (WidgetDefinition $w) => $w->active)
            ->sortBy(fn (WidgetDefinition $w) => $w->order);
    }

    public function find(string $key): ?WidgetDefinition
    {
        $widget = $this->widgets[$key] ?? null;

        return ($widget && $widget->active) ? $widget : null;
    }

    /**
     * Widgets visíveis para o usuário/tenant, já ordenados.
     *
     * @return Collection<string, WidgetDefinition>
     */
    public function visibleFor(User $user, ?Tenant $tenant): Collection
    {
        return $this->all()->filter(
            fn (WidgetDefinition $w) => $this->canSee($w, $user, $tenant)
        )->values()->keyBy('key');
    }

    /** Um usuário pode ver um widget específico? (usado para validar o endpoint lazy). */
    public function userCanSee(string $key, User $user, ?Tenant $tenant): bool
    {
        $widget = $this->find($key);

        return $widget !== null && $this->canSee($widget, $user, $tenant);
    }

    private function canSee(WidgetDefinition $widget, User $user, ?Tenant $tenant): bool
    {
        return $this->hasPermission($widget->permission, $user)
            && $this->hasModule($widget->module, $user, $tenant);
    }

    private function hasPermission(?string $permission, User $user): bool
    {
        if ($permission === null) {
            return true;
        }

        $permissions = $user->permissionNames();

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    private function hasModule(?string $module, User $user, ?Tenant $tenant): bool
    {
        if ($module === null) {
            return true;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $plan = $tenant?->activePlan();
        if (! $plan) {
            return false;
        }

        return in_array(
            $module,
            $plan->modules()->where('enabled', true)->pluck('module')->all(),
            true,
        );
    }
}
