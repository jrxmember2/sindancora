# Manutenção preventiva recorrente (Fase B — B4)

Planos de manutenção que se repetem (elevador, bombas, gerador, caixa d'água, dedetização,
AVCB…), com fornecedor, recorrência, próxima data prevista, **histórico de execuções** e
**alerta automático** de proximidade/atraso aos gestores. Reusa a entidade `Supplier` (B6).

## Modelo de dados

| Tabela | Descrição |
| --- | --- |
| `maintenance_plans` | Plano recorrente de um condomínio. `condominium_id` (obrigatório), `supplier_id` (padrão, opcional), `category` (slug tipo `maintenance`), `title`, `description`, `frequency` (`once\|monthly\|quarterly\|semiannual\|annual\|biennial`), `next_due_date`, `alert_days` (padrão 15), `last_done_date`, `last_notified_at`, `is_active`. Soft delete. |
| `maintenance_records` | Execuções (histórico): `done_date`, `supplier_id` (quem executou), `user_id` (quem registrou), `cost` (decimal), `notes`. |

Migrations: `2026_06_19_000001..2`. `tenant_id` direto em ambas; FKs `cascadeOnDelete` (e `nullOnDelete` para `supplier_id`/`user_id`).

## Regras

- **Recorrência automática**: ao registrar uma execução (`MaintenanceService::registerExecution`),
  o sistema grava o `MaintenanceRecord`, atualiza `last_done_date` e **recalcula `next_due_date`**
  (`MaintenancePlan::nextDateFrom`) conforme a `frequency`. Recorrência `once` não recalcula.
  O ciclo de alerta é reaberto (`last_notified_at = null`).
- **Situação** (accessor `status`, em `$appends`): `overdue` (vencida), `due_soon` (dentro de
  `alert_days`), `ok`, ou `null` (sem data) — mesma lógica do `Document` da Fase A.
- **Categoria** reusa as **Categorias customizáveis**: tipo `maintenance` em `Category::TYPES`,
  com lista-base `MaintenancePlan::CATEGORIES` (Elevador, Bombas, Gerador, Caixa d'água,
  Dedetização, AVCB/Incêndio, Ar-condicionado, Portões, Jardinagem, Outros).
- **Custo** fica registrado na execução (histórico) e, opcionalmente, gera uma conta a pagar vinculada
  em `expenses` pela integração B4+B6+C8.

## Alerta agendado

- Comando `maintenance:notify-due` (`NotifyDueMaintenance`) — varre `MaintenancePlan::dueForAlert()`
  (ativas, dentro da janela `alert_days`, ainda não notificadas no ciclo), notifica os
  `User::PANEL_ROLES` ativos por tenant e marca `last_notified_at`.
- Notificação `MaintenanceDue` (`database` + `mail` + `broadcast`, enfileirada), ícone `wrench`.
- Agendado em `routes/console.php`: `dailyAt('07:30')->withoutOverlapping()`.
- Espelha o padrão de `documents:notify-expiring`/`DocumentExpiring` (Fase A).

## Integração com contas a pagar

- A execução de manutenção (`maintenance_records`) pode gerar uma conta em `expenses` quando o
  usuário marca **Gerar conta a pagar** e informa custo. O vínculo fica em
  `expenses.maintenance_record_id`.
- A criação da conta ocorre na mesma transação de `MaintenanceService::registerExecution`: se a
  conta não for criada, a execução também não fica salva pela metade.
- A conta nasce como `pending`, categoria `maintenance`, fornecedor da execução/plano e
  vencimento/documento/lembrete informados no registro da execução.
- Regra de acesso: além de `maintenance:update`, gerar a conta exige `expenses:create` e plano com
  módulo `financial` ativo (super admin continua com bypass global).
- A tela da manutenção mostra a conta vinculada em cada execução; a tela de contas mostra a origem
  em manutenção.

## Permissões

Módulo `maintenance` com `read` / `create` / `update` / `delete`.
- `admin` e `sindico`: todas. `subsindico` e `conselheiro`: apenas `read`.
- Registrar execução exige `maintenance:update`; remover execução exige `maintenance:delete`.

## Rotas (painel) — prefixo `/manutencoes`

`maintenance.index` (read), `maintenance.show` (read), `maintenance.create`/`store` (create),
`maintenance.edit`/`update` (update), `maintenance.executions.store` (update),
`maintenance.executions.destroy` (delete), `maintenance.destroy` (delete). Estáticas antes da
dinâmica `{maintenance}`.

## Front

Páginas em `resources/js/Pages/Maintenance/`: `Index` (badge Em dia/Vence em N/Atrasada + KPI de
atrasadas + filtros situação/categoria/condomínio), `Create`, `Edit`, `Show` (dados + histórico de
execuções + form "Registrar execução"), `MaintenanceForm`. Menu **"Manutenção"** (ícone `Wrench`)
gated por `maintenance:read`.

## Deploy

`migrate --force` (2 tabelas) + `db:seed --force` (permissões `maintenance:*`) + `optimize:clear`
+ rebuild do front. O scheduler precisa rodar `maintenance:notify-due` (07:30). Sem env nova.
