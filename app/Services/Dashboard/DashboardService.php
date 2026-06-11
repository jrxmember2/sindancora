<?php

namespace App\Services\Dashboard;

use App\Dashboard\Concerns\ScopesDashboard;
use App\Dashboard\Contracts\WidgetDataResolver;
use App\Dashboard\DashboardContext;
use App\Dashboard\WidgetDefinition;
use App\Dashboard\WidgetRegistry;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDashboardPreference;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Orquestra o dashboard modular: resolve filtros e escopo, monta o contexto,
 * seleciona os widgets visíveis (permissão + módulo) e entrega os dados.
 *
 * Widgets `lazy` não são resolvidos no carregamento inicial — o frontend busca o
 * payload via endpoint JSON (resolveWidget), com cache curto para os mais pesados.
 */
class DashboardService
{
    use ScopesDashboard;

    /** Presets de período disponíveis no filtro global. */
    private const PERIODS = [
        'month' => 'Mês atual',
        '30d' => 'Últimos 30 dias',
        '90d' => 'Últimos 90 dias',
        '12m' => 'Últimos 12 meses',
        'year' => 'Ano atual',
    ];

    public function __construct(private readonly WidgetRegistry $registry) {}

    /**
     * Payload completo da página do dashboard.
     *
     * @return array<string, mixed>
     */
    public function buildPage(Request $request): array
    {
        $user = $request->user();
        $tenant = $this->currentTenant();
        $context = $this->buildContext($request, $user, $tenant);

        $visible = $this->registry->visibleFor($user, $tenant);
        $preferences = $this->loadPreferences($user, $tenant);

        $meta = [];
        $data = [];

        foreach ($visible as $definition) {
            $meta[] = $definition->toMeta();

            if (! $definition->lazy) {
                $data[$definition->key] = $this->safeResolve($definition, $context);
            }
        }

        $meta = $this->applyOrder($meta, $preferences['order'] ?? []);

        return [
            'meta' => array_values($meta),
            'data' => $data,
            'filters' => $this->filterOptions($context, $visible->values()->all()),
            'activeFilters' => [
                'period' => $request->query('period', 'month'),
                'condominium' => $context->selectedCondominiumId,
                'status' => $context->status,
            ],
            'preferences' => $preferences,
            'header' => $this->header($context),
        ];
    }

    /**
     * Resolve um único widget (lazy ou refresh). Lança 403 se o usuário não pode vê-lo.
     *
     * @return array<string, mixed>
     */
    public function resolveWidget(Request $request, string $key): array
    {
        $user = $request->user();
        $tenant = $this->currentTenant();

        abort_unless($this->registry->userCanSee($key, $user, $tenant), 403);

        $definition = $this->registry->find($key);
        $context = $this->buildContext($request, $user, $tenant);

        if ($definition->lazy) {
            return Cache::remember(
                $context->cacheKey($key),
                120,
                fn () => $this->safeResolve($definition, $context),
            );
        }

        return $this->safeResolve($definition, $context);
    }

    /**
     * Salva preferências do usuário (widgets ocultos e ordem).
     */
    public function savePreferences(Request $request): void
    {
        $user = $request->user();
        $tenant = $this->currentTenant();

        $validated = $request->validate([
            'hidden_widgets' => ['nullable', 'array'],
            'hidden_widgets.*' => ['string'],
            'widget_order' => ['nullable', 'array'],
            'widget_order.*' => ['string'],
        ]);

        UserDashboardPreference::updateOrCreate(
            ['tenant_id' => $tenant?->id, 'user_id' => $user->id],
            [
                'hidden_widgets' => array_values($validated['hidden_widgets'] ?? []),
                'widget_order' => array_values($validated['widget_order'] ?? []),
            ],
        );
    }

    private function buildContext(Request $request, User $user, ?Tenant $tenant): DashboardContext
    {
        $condominiumIds = [];
        if ($tenant) {
            $condominiumIds = $this->accessibleCondominiums($tenant->id, $user)
                ->pluck('id')->all();
        }

        $selected = $request->query('condominium');
        if ($selected && ! in_array($selected, $condominiumIds, true)) {
            $selected = null;
        }

        [$from, $to] = $this->periodRange((string) $request->query('period', 'month'));

        return new DashboardContext(
            tenant: $tenant ?? new Tenant(),
            user: $user,
            condominiumIds: $condominiumIds,
            selectedCondominiumId: $selected ?: null,
            from: $from,
            to: $to,
            status: $request->query('status') ?: null,
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodRange(string $period): array
    {
        $to = now();

        $from = match ($period) {
            '30d' => now()->subDays(30)->startOfDay(),
            '90d' => now()->subDays(90)->startOfDay(),
            '12m' => now()->subMonthsNoOverflow(12)->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        return [$from, $to];
    }

    /**
     * @param  array<int, WidgetDefinition>  $visible
     * @return array<string, mixed>
     */
    private function filterOptions(DashboardContext $context, array $visible): array
    {
        $condos = [];
        if (! $context->tenant->getKey()) {
            $condos = [];
        } else {
            $condos = $this->accessibleCondominiums($context->tenant->id, $context->user)
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
                ->values()->all();
        }

        $modules = collect($visible)
            ->map(fn (WidgetDefinition $w) => $w->module ?? 'general')
            ->unique()->values()
            ->map(fn (string $m) => ['value' => $m, 'label' => $this->moduleLabel($m)])
            ->all();

        $periods = collect(self::PERIODS)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()->all();

        return [
            'condominiums' => $condos,
            'modules' => $modules,
            'periods' => $periods,
            'statuses' => [
                ['value' => 'open', 'label' => 'Em aberto'],
                ['value' => 'overdue', 'label' => 'Vencido'],
                ['value' => 'paid', 'label' => 'Pago'],
            ],
        ];
    }

    private function moduleLabel(string $module): string
    {
        return [
            'general' => 'Geral',
            'financial' => 'Financeiro',
            'occurrences' => 'Ocorrências',
            'reservations' => 'Reservas',
            'documents' => 'Documentos',
            'maintenance' => 'Manutenção',
            'works' => 'Obras',
        ][$module] ?? ucfirst($module);
    }

    /**
     * @return array<string, mixed>
     */
    private function header(DashboardContext $context): array
    {
        $hour = (int) now()->format('H');
        $greeting = $hour < 12 ? 'Bom dia' : ($hour < 18 ? 'Boa tarde' : 'Boa noite');

        return [
            'greeting' => $greeting,
            'user_name' => $context->user->name,
            'period_label' => $context->from->translatedFormat('d/m/Y').' — '.$context->to->translatedFormat('d/m/Y'),
            'updated_at' => now()->toIso8601String(),
            'condominium_count' => count($context->scopeIds()),
        ];
    }

    /**
     * @return array{hidden: array<int, string>, order: array<int, string>}
     */
    private function loadPreferences(User $user, ?Tenant $tenant): array
    {
        $pref = UserDashboardPreference::where('user_id', $user->id)
            ->where('tenant_id', $tenant?->id)
            ->first();

        return [
            'hidden' => $pref?->hidden_widgets ?? [],
            'order' => $pref?->widget_order ?? [],
        ];
    }

    /**
     * Reordena os metadados conforme a ordem salva pelo usuário; o que não estiver
     * na lista mantém a ordem padrão do registry, ao final.
     *
     * @param  array<int, array<string, mixed>>  $meta
     * @param  array<int, string>  $order
     * @return array<int, array<string, mixed>>
     */
    private function applyOrder(array $meta, array $order): array
    {
        if ($order === []) {
            return $meta;
        }

        $rank = array_flip($order);
        usort($meta, function ($a, $b) use ($rank) {
            $ra = $rank[$a['key']] ?? PHP_INT_MAX;
            $rb = $rank[$b['key']] ?? PHP_INT_MAX;

            return $ra <=> $rb ?: ($a['order'] <=> $b['order']);
        });

        return $meta;
    }

    private function safeResolve(WidgetDefinition $definition, DashboardContext $context): array
    {
        try {
            /** @var WidgetDataResolver $resolver */
            $resolver = app()->make($definition->resolver);

            return $resolver->resolve($context);
        } catch (\Throwable $e) {
            Log::error('Dashboard widget falhou', [
                'widget' => $definition->key,
                'error' => $e->getMessage(),
            ]);

            return ['error' => true, 'message' => 'Não foi possível carregar este indicador.'];
        }
    }

    private function currentTenant(): ?Tenant
    {
        return app()->bound('tenant') ? app('tenant') : null;
    }
}
