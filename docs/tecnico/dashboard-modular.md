# Dashboard Modular

> Implementado em 11/06/2026. Transforma a home do painel (`/dashboard`) numa central
> modular onde cada módulo registra seus próprios widgets (KPIs, gráficos, rankings,
> timelines, alertas, atalhos), com gating por permissão + módulo do plano + escopo de
> condomínio, filtros globais e personalização persistida por usuário.

## Visão geral

```
Requisição → DashboardController → DashboardService
                                      │
                                      ├─ WidgetRegistry.visibleFor(user, tenant)   (permissão + módulo)
                                      ├─ DashboardContext (tenant, user, escopo, filtros)
                                      └─ Resolvers (1 por widget) → payload por tipo
Frontend (Inertia/React) → DashboardGrid → WidgetCard → WidgetRenderer → componente do tipo
```

- Widgets **não-lazy** são resolvidos no carregamento da página (paint rápido).
- Widgets **lazy** (séries de 12 meses, rankings) são buscados sob demanda via
  `GET /dashboard/widgets/{key}` (JSON), com `Cache::remember` de 120s.
- Sem tabela de catálogo: os widgets são definidos em código (registry). A única tabela
  é `user_dashboard_preferences` (ocultos + ordem por usuário).

## Backend

### Núcleo (`app/Dashboard/`)

| Arquivo | Papel |
| --- | --- |
| `WidgetType.php` | Constantes dos tipos de widget (kpi, line, donut, gauge, ...). |
| `WidgetSize.php` | Tokens de tamanho (`small|medium|large|wide|full`). |
| `WidgetDefinition.php` | VO imutável de um widget (key, módulo, tipo, tamanho, resolver, permissão, ordem, lazy). |
| `DashboardContext.php` | Tenant, usuário, IDs de condomínio do escopo, filtros (período/condomínio/status). |
| `Contracts/WidgetDataResolver.php` | Interface `resolve(DashboardContext): array`. |
| `WidgetRegistry.php` | Registro central; `visibleFor()` aplica permissão + módulo (igual ao menu lateral). |
| `Concerns/ScopesDashboard.php` | Reusa `ScopesCondominiumsByRole` para o escopo por papel. |
| `Resolvers/BaseResolver.php` | Helpers de consulta com escopo (`chargeBase`, `occurrenceBase`, ...). |

### Registro (`app/Providers/DashboardServiceProvider.php`)

Registra `WidgetRegistry` como singleton e cadastra todas as `WidgetDefinition`. É o
**único lugar** onde os widgets são declarados. Registrado em `bootstrap/providers.php`.

### Serviço (`app/Services/Dashboard/DashboardService.php`)

- `buildPage(Request)`: resolve filtros/escopo, monta `meta[]`, resolve os não-lazy,
  carrega preferências, devolve opções de filtro e cabeçalho.
- `resolveWidget(Request, key)`: valida visibilidade (403 se não pode ver), resolve um
  widget (com cache nos lazy).
- `savePreferences(Request)`: grava `hidden_widgets` e `widget_order`.

### Persistência

- Migration `2026_07_01_000001_create_user_dashboard_preferences_table.php`
  (`tenant_id`, `user_id`, `hidden_widgets`, `widget_order`, `filters`, único por
  tenant+usuário).
- Model `App\Models\UserDashboardPreference` (`BelongsToTenant`, casts JSON).

### Rotas (`routes/web.php`, grupo `panel`)

| Método | URI | Nome |
| --- | --- | --- |
| GET | `/dashboard` | `dashboard` |
| GET | `/dashboard/widgets/{key}` | `dashboard.widget` |
| PUT | `/dashboard/preferences` | `dashboard.preferences` |

Não há permissão nova: a rota do dashboard é aberta a todo usuário do painel e o gating
acontece **por widget** (permissão + módulo).

## Frontend (`resources/js/`)

| Arquivo | Papel |
| --- | --- |
| `lib/format.ts` | Formatação pt-BR (`brl`, `num`, `pct`, `compact`, `dateBr`). |
| `lib/dashboardTheme.ts` | Paleta de cores por token/módulo + fábrica de opções do ApexCharts. |
| `Components/Dashboard/types.ts` | Tipos espelhando o payload do backend. |
| `Components/Dashboard/DashboardHeader.tsx` | Saudação, resumo, última atualização, botões. |
| `Components/Dashboard/DashboardFilters.tsx` | Período, condomínio, status. |
| `Components/Dashboard/WidgetCard.tsx` | Shell do card + lazy-fetch + refresh + ocultar. |
| `Components/Dashboard/WidgetRenderer.tsx` | Switch tipo → componente; trata empty/error. |
| `Components/Dashboard/WidgetRenderers.tsx` | Componentes visuais de cada tipo. |
| `Components/Dashboard/WidgetStates.tsx` | Skeleton, empty e error states. |
| `Components/Dashboard/CustomizePanel.tsx` | Drawer ocultar/mostrar + reordenar (persiste). |
| `Pages/Dashboard/Index.tsx` | Página: header + filtros + grid + customize. |

Gráficos usam **ApexCharts** (`react-apexcharts`). Como o Inertia faz code-split por
página, o bundle do ApexCharts só carrega ao abrir `/dashboard`.

Grade responsiva (xl = 4 colunas): `small`=1, `medium`/`large`=2, `wide`=3, `full`=4;
colapsa para 1–2 colunas em telas menores.

## Como registrar um widget novo

1. Crie um resolver em `app/Dashboard/Resolvers/<Modulo>/MeuResolver.php`:

```php
namespace App\Dashboard\Resolvers\Financial;

use App\Dashboard\DashboardContext;
use App\Dashboard\Resolvers\BaseResolver;

class MeuResolver extends BaseResolver
{
    public function resolve(DashboardContext $ctx): array
    {
        if ($ctx->hasNoScope()) {
            return $this->empty(['label' => 'Meu indicador']);
        }

        $valor = $this->chargeBase($ctx)->open()->count();

        return [
            'label' => 'Meu indicador',
            'value' => $valor,
            'formatted' => number_format($valor, 0, ',', '.'),
            'color' => 'blue',
            'icon' => 'Wallet',
        ];
    }
}
```

2. Registre a `WidgetDefinition` em `DashboardServiceProvider::definitions()`:

```php
new WidgetDefinition(
    key: 'financial.meu_indicador',
    module: 'financial',          // null = geral, sempre visível
    name: 'Meu indicador',
    description: 'O que ele mostra.',
    type: Type::KPI,
    size: Size::SMALL,
    resolver: MeuResolver::class,
    permission: 'charges:read',   // null = sem restrição
    order: 69,
    lazy: false,                  // true para consultas pesadas
),
```

Pronto — o widget aparece para quem tem a permissão e o módulo do plano.

## Como criar um novo tipo de gráfico

1. Adicione a constante em `app/Dashboard/WidgetType.php`.
2. Defina o shape do payload no resolver (categorias/series/colors...).
3. Crie o componente renderer em `resources/js/Components/Dashboard/WidgetRenderers.tsx`
   (reuse `baseChartOptions(colors, format)` do `dashboardTheme.ts`).
4. Adicione o `case` no `WidgetRenderer.tsx` mapeando o tipo ao componente.
5. Atualize o union `WidgetType` em `Components/Dashboard/types.ts`.

## Como conectar um widget a dados reais

Os resolvers já recebem o `DashboardContext` com o escopo resolvido. Use os helpers do
`BaseResolver` (`chargeBase`, `expenseBase`, `occurrenceBase`, `reservationBase`,
`maintenanceBase`, `workBase`, `documentBase`, `unitBase`, `residentLinkBase`) — todos já
filtram por tenant e pelos condomínios acessíveis (`$ctx->scopeIds()`). Para evitar N+1,
prefira agregações (`selectRaw('... count/sum ...')->groupBy(...)`), como em
`ConsolidatedReportBuilder`.

## Como controlar permissões dos widgets

- `permission`: string única (ex.: `charges:read`). `null` = sem restrição.
- `module`: módulo do plano (ex.: `financial`). `null` = sempre habilitado.
- Super admin enxerga tudo. O `WidgetRegistry::visibleFor()` aplica o mesmo critério do
  menu lateral. O endpoint `dashboard.widget` revalida a visibilidade (403 caso contrário),
  então não basta esconder no front.

## Como evoluir para drag-and-drop (fase 2)

A persistência já existe: `widget_order` (array de keys) e `hidden_widgets`. O
`CustomizePanel` hoje reordena com setas. Para drag-and-drop:

1. Adicione uma lib (ex.: `@dnd-kit/core`) no `DashboardGrid`/`CustomizePanel`.
2. No fim do arraste, monte o novo array de keys e chame a mesma rota
   `PUT /dashboard/preferences` com `widget_order`.
3. O grid já respeita `preferences.order` (ver `Pages/Dashboard/Index.tsx` → `visible`).
   Redimensionamento por usuário exigiria persistir um override de `size` por key.

## Comandos

```bash
# Backend
php artisan migrate            # cria user_dashboard_preferences
php artisan route:list --name=dashboard --except-vendor

# Frontend
npm install                    # apexcharts + react-apexcharts já no package.json
npm run build                  # tsc && vite build
```

## Limitações conhecidas / fase 2

- Drag-and-drop visual e redimensionamento por usuário.
- Múltiplos dashboards por perfil; export PDF; compartilhar.
- Sensores/IoT: não há módulo no projeto — o tipo `gauge` está pronto e documentado como
  template; basta criar um resolver quando a fonte existir.
- Dark mode: o projeto ainda não tem suporte global; não foi adicionado aqui.
