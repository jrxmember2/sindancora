# Cronograma consolidado

> Entregue em 09/06/2026 como D13 da nova onda operacional.

## Objetivo

O cronograma consolidado oferece uma agenda operacional unica para o sindico acompanhar prazos,
eventos e vencimentos dos modulos ja existentes, sem duplicar dados.

## Acesso

- Rota: `/cronograma`
- Nome da rota: `schedule.index`
- Permissao: `schedule:read`
- Modulo de plano: `schedule`
- Planos habilitados por seed: Profissional, Business e Enterprise.

O controller tambem respeita as permissoes e modulos de cada fonte. Exemplo: um usuario com
`schedule:read`, mas sem `expenses:read` ou sem modulo `financial`, nao ve contas a pagar nem
cobrancas no cronograma.

## Fontes consolidadas

| Fonte | Origem | Regra de data |
| --- | --- | --- |
| Reservas | `reservations` | `date` + `start_time`, somente `pending` e `approved` |
| Assembleias | `assemblies` | `scheduled_at`, quando preenchido |
| Manutencoes | `maintenance_plans` | `next_due_date`, somente planos ativos |
| Obras/Reformas | `works` | `start_date`, `expected_end_date` e `completed_at` |
| Ferias | `employee_vacation_periods` | `deadline_date`, somente `pending` e `scheduled` |
| Ocorrencias | `occurrences` | `due_at`, somente ocorrencias nao encerradas |
| Contas a pagar | `expenses` | `due_date`, somente abertas |
| Cobrancas | `charges` | `due_date`, somente abertas |

## Escopo por condominio

O cronograma usa a mesma regra do assistente de IA para condominios acessiveis:

- superadmin e usuarios com papel tenant-wide veem todos os condominios ativos do tenant;
- usuarios com papel escopado por condominio veem apenas os condominios associados em `user_roles.condominium_id`.

O filtro de condominio ignora IDs fora do escopo do usuario.

## Frontend

Tela: `resources/js/Pages/Schedule/Index.tsx`

Recursos:

- grade mensal;
- agenda lateral do mes;
- filtros por condominio e fonte;
- resumo de total, hoje, proximos 7 dias e atrasados;
- eventos clicaveis para a origem quando existe rota de detalhe/edicao;
- legenda por fonte com contagem.

## Arquivos principais

- `app/Http/Controllers/Panel/ScheduleController.php`
- `resources/js/Pages/Schedule/Index.tsx`
- `resources/js/Layouts/AppLayout.tsx`
- `routes/web.php`
- `database/seeders/PermissionSeeder.php`
- `database/seeders/RoleSeeder.php`
- `database/seeders/PlanSeeder.php`
- `app/Http/Middleware/CheckPermission.php`
- `app/Http/Controllers/Admin/PlanController.php`

## Pos-deploy

Nao ha migration para esta entrega. Como houve alteracao de permissoes e modulos de plano, rodar:

```bash
php artisan db:seed --force
php artisan optimize:clear
```
