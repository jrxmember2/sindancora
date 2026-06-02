# Módulo de Áreas Comuns e Reservas (Fase 3.3)

Gestão de áreas comuns do condomínio e reservas com fluxo de aprovação, prevenção de conflito de
horário, calendário mensal e notificações in-app.

## Modelo de dados

**`common_areas`** (migration `2026_06_01_000006`): name, description, capacity, `requires_approval`,
`min_advance_days` (antecedência mínima), `opening_time`/`closing_time`, `fee` (taxa), `deposit` (caução),
`rules`, `active`, + tenant_id/condominium_id. SoftDeletes.

**`reservations`** (migration `2026_06_01_000007`): common_area_id, condominium_id, `requested_by`,
`date`, `start_time`, `end_time`, `status` (`pending|approved|rejected|cancelled`), `notes`,
`decision_reason`, `decided_by`, `decided_at`. SoftDeletes. Índices em (tenant, área, data) e (tenant, status).

`Reservation::STATUSES` define os rótulos.

## Regras de negócio (App\Services\ReservationService)

- `request(area, data, requesterId)` — cria a reserva dentro de uma **transação**. Se a área **não**
  exige aprovação, já aprova (após checar conflito); senão fica `pending`. Notifica conforme o caso.
- `approve(reservation)` — em transação, **revalida conflito** e aprova.
- `reject` / `cancel` — definem status + `decision_reason` + `decided_by/at` e notificam o solicitante.

**Prevenção de conflito** (`assertNoConflict`): dentro da transação, busca reservas `approved` da mesma
área/data que se sobrepõem ao intervalo (`start_time < fim` E `end_time > início`) com `lockForUpdate()`,
e lança `App\Exceptions\ReservationConflictException` se houver. Isso evita corrida entre duas aprovações
simultâneas. O controller captura a exceção e devolve erro amigável (não é um handler global).

**Validações de janela** (no controller, `assertWithinAreaRules`): antecedência mínima da área e
horário dentro de abertura/fechamento — viram `ValidationException` nos campos correspondentes.

**Notificações** (`ReservationUpdated`, canal database — ver `notificacoes.md`): nova solicitação
pendente avisa os demais usuários ativos do tenant (aprovadores); aprovação/recusa/cancelamento avisam
o solicitante (exceto se for o próprio ator).

## RBAC

Prefixo `/reservas`. Reservas: `reservations:read` (index/show), `:create` (create/store),
`:approve`, `:reject`, `:cancel`. **Gestão de áreas comuns** (`/reservas/areas`, CRUD) fica sob
`reservations:approve` (nível administrativo) — não há permissão própria de "áreas" no seed.
Rotas estáticas (`areas`, `criar`) registradas antes da dinâmica `{reservation}`. Sem re-seed.

## Frontend

- `Pages/CommonAreas/{Index,Create,Edit}.tsx` + `CommonAreaForm.tsx`.
- `Pages/Reservations/{Index,Create,Show}.tsx`. O **Index** traz um **calendário mensal** (grade
  própria, sem dependência externa) com navegação de mês e filtro por área, mostrando reservas
  pendentes/aprovadas, além da lista filtrável por status. O **Show** tem os botões de aprovar/recusar/
  cancelar (motivo via prompt). Horários `time` são exibidos com `slice(0,5)`.

## Deploy

`php artisan migrate --force && php artisan optimize:clear`. Sem `db:seed`.

## Pendências

Fotos das áreas (reusar `StorageService`), visão semanal do calendário, bloqueio de inadimplente
(depende do módulo financeiro) e a solicitação direta pelo morador (Portal — Fase 4).
Ver `docs/produto/02-roadmap-mvp.md` §3.3.
