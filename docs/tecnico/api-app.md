# API do App Móvel (área do tenant)

> Etapa 0 da iniciativa do app Android nativo do síndico (12/06/2026).
> Plano completo: app Kotlin/Compose em repositório próprio (`sindancora-android`),
> consumindo apenas esta API. App do morador é fase futura.

## Visão geral

- **Auth**: token Bearer **Sanctum por usuário** (`POST /api/v1/auth/login`). Não confundir com as
  API keys de integração (`sk_...`, tabela `api_keys`) — o app NÃO usa API keys.
- **Tenant por host**: o app chama `https://<dominio-do-tenant>/api/v1/...`; o `ResolveTenant`
  resolve o tenant e o login escopa o usuário pelo host.
- **Gating**: as rotas usam o MESMO middleware `permission:` do painel (`CheckPermission`), que
  valida permissão do usuário **e** módulo do plano (402 `MODULE_NOT_AVAILABLE` em JSON).
- **Regra de negócio**: os controllers em `app/Http/Controllers/Api/V1/App/` reusam os services
  do painel (`OccurrenceService`, `ReservationService`, `AnnouncementService`, `GatehouseService`,
  `DashboardService`, `ScheduleEventBuilder`) — nunca duplicam regra.
- **Envelope**: `{success, data, meta?}`; erros `{success:false, error:{code, message}}`.
- **OpenAPI**: atributos `#[OA\...]` em todos os endpoints; `php artisan l5-swagger:generate`.

## Descoberta de instância

`GET /api/v1/instance-info` (público) — o app valida o endereço digitado pelo usuário:

```json
{ "success": true, "data": {
  "system": "SindÂncora", "version": "1.0.0", "api_version": 1, "min_app_version": "1.0.0",
  "tenant": { "brand_name": "...", "primary_color": "#1e40af", "logo_url": "...", "status": "active" }
}}
```

Constantes de versão em `Api/V1/InstanceController` (`API_VERSION`, `MIN_APP_VERSION`).

## Autenticação e sessão

| Endpoint | Descrição |
| --- | --- |
| `POST /v1/auth/login` | Devolve `user` (com `permissions`, `can_access_panel`), `tenant` (com `status`, `plan.modules`), `access_token`, `expires_at`. |
| `POST /v1/auth/refresh` | Rotação: revoga o token usado e emite novo (`access_token`, `expires_at`). |
| `GET /v1/session` | Reapresenta usuário + tenant + permissões + módulos (revalidação ao abrir o app). |
| `POST /v1/auth/forgot-password` / `reset-password` | Já existiam. |

**Expiração**: `config/sanctum.php` → `expiration` = 7 dias (env `SANCTUM_TOKEN_EXPIRATION`, em
minutos). Afeta apenas personal access tokens (não a sessão web do painel nem API keys).

## Status do tenant (bloqueio)

`ResolveTenant` agora responde **JSON estruturado** para `api/*`:

| Situação | HTTP | `error.code` |
| --- | --- | --- |
| Domínio sem tenant | 404 | `TENANT_NOT_FOUND` |
| Tenant suspenso | 402 | `TENANT_SUSPENDED` → app mostra "Assinatura em atraso" e bloqueia |
| Tenant inativo | 503 | `TENANT_INACTIVE` |

Estados de carência (`TENANT_GRACE_*`, banner sem bloquear) serão adicionados quando a régua de
cobrança existir no backend. O app já deve tratar códigos desconhecidos como banner informativo.

## Dispositivos (push — consumido na Etapa 3)

- Tabela `user_devices` (migration `2026_07_02_000001`): tenant_id, user_id, `fcm_token` único,
  platform, app_version, device_name, last_seen_at. Model `UserDevice`.
- `POST /v1/devices` (registra/reaponta token) e `DELETE /v1/devices` (logout/troca de instância).

## Endpoints do app (`/api/v1/app/*`)

Todos sob `auth:sanctum` + `throttle:120,1`. Permissões idênticas às rotas web correspondentes.

| Grupo | Endpoints | Permissão |
| --- | --- | --- |
| Dashboard | `GET app/dashboard`, `GET app/dashboard/widgets/{key}` | por widget (WidgetRegistry) |
| Cronograma | `GET app/schedule?month=YYYY-MM&condominium_id&source` | `schedule:read` (+ por fonte) |
| Comunicados | `GET app/announcements`, `GET .../{id}` | `announcements:read` |
| | `POST app/announcements` (action: draft\|publish, `attachments[]`) | `announcements:create` |
| | `POST app/announcements/{id}/publish` | `announcements:publish` |
| Ocorrências | `GET app/occurrences`, `GET .../{id}` | `occurrences:read` |
| | `POST app/occurrences` (`attachments[]` p/ fotos) | `occurrences:create` |
| | `POST .../{id}/status` (closed exige `occurrences:close`), `/assign`, `/comments` | `occurrences:update` |
| Reservas | `GET app/reservations`, `GET .../{id}` | `reservations:read` |
| | `POST .../{id}/approve` / `/reject` / `/cancel` | `reservations:approve/reject/cancel` |
| Financeiro | `GET app/charges` (+ KPIs open/overdue/received_month), `GET .../{id}` | `charges:read` |
| | `GET app/expenses` (status=open\|paid..., from/to) | `expenses:read` |
| Portaria | `GET app/gatehouse/visits` (presentes + log) | `gatehouse:read` |
| | `POST app/gatehouse/validate-token`, `/check-in`, `/visits/{id}/check-out` | `gatehouse:manage` |
| Encomendas | `GET app/parcels` | `gatehouse:read` |

Listagens são paginadas (`per_page` máx. 50) com `meta` e, quando útil, `options` (statuses,
categorias, condomínios) para montar filtros no app sem chamadas extras.

## Refatoração: ScheduleEventBuilder

A montagem do cronograma consolidado saiu de `Panel\ScheduleController` para
`app/Services/ScheduleEventBuilder.php` (payload idêntico), compartilhada entre painel e app.
O controller do painel virou um delegador fino.

## Arquivos da Etapa 0

- `config/sanctum.php` (novo, expiration 7d)
- `app/Http/Controllers/Api/V1/AuthController.php` (login enriquecido, refresh, session)
- `app/Http/Controllers/Api/V1/InstanceController.php`
- `app/Http/Middleware/ResolveTenant.php` (deny JSON p/ API)
- `database/migrations/2026_07_02_000001_create_user_devices_table.php` + `app/Models/UserDevice.php`
- `app/Http/Controllers/Api/V1/App/{AppController,DashboardController,AnnouncementController,OccurrenceController,ReservationController,ScheduleController,FinancialController,GatehouseController,DeviceController}.php`
- `app/Services/ScheduleEventBuilder.php` + `app/Http/Controllers/Panel/ScheduleController.php` (delegador)
- `routes/api.php`

## Validações / deploy

- `php -l` em todos; `php artisan route:list --path=api/v1` (54 rotas); `l5-swagger:generate` OK.
- **Deploy**: `php artisan migrate --force` (cria `user_devices`) + `php artisan optimize:clear`.
  Sem seed novo. A expiração de 7 dias passa a valer para tokens Sanctum novos e antigos.

## Próximas etapas

1. **Etapa 1** — fundação do app Android (`C:\Users\JUNIOR\sindancora-android`).
2. **Etapa 2** — telas dos módulos (2a ocorrências/comunicados → 2d portaria).
3. **Etapa 3** — push FCM (canal Laravel + `NotificationPreferenceRegistry` + `user_devices`).
4. **Etapa 4** — qualidade/release (testes, assinatura, Play Store).
