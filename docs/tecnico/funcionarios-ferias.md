# Funcionarios + controle de ferias

> Entregue em 09/06/2026 como D9 da nova onda operacional.

## Objetivo

O modulo centraliza o cadastro operacional de funcionarios por condominio e controla os periodos
aquisitivos/concessivos de ferias, com alerta automatico quando o prazo limite se aproxima ou atrasa.

## Acesso

- Rota: `/funcionarios`
- Nome base das rotas: `employees.*`
- Modulo de plano: `employees`
- Planos habilitados por seed/migration: Profissional, Business e Enterprise.

Permissoes:

- `employees:create`
- `employees:read`
- `employees:update`
- `employees:delete`

`admin` e `sindico` recebem todas. `subsindico` e `conselheiro` recebem leitura.

## Modelo de dados

| Tabela | Descricao |
| --- | --- |
| `employees` | Funcionario do tenant por condominio. Guarda nome, documento, contato, cargo, tipo de vinculo, status, admissao, desligamento, CTPS, PIS/PASEP, salario, dias de alerta de ferias e observacoes. |
| `employee_vacation_periods` | Periodos de ferias do funcionario. Guarda inicio/fim aquisitivo, prazo limite, datas de gozo, dias, status, `notified_at` e observacoes. |

Status de funcionario:

- `active`
- `on_vacation`
- `on_leave`
- `terminated`

Status de ferias:

- `pending`
- `scheduled`
- `taken`
- `paid_out`
- `cancelled`

## Regras principais

- Ao cadastrar funcionario, a tela pode criar automaticamente o primeiro periodo aquisitivo:
  admissao ate admissao + 1 ano - 1 dia; prazo limite = fim aquisitivo + 1 ano.
- Cada funcionario pode ter varios periodos de ferias, mantendo historico.
- Periodos `pending` e `scheduled` entram no alerta e no cronograma.
- Periodos `taken`, `paid_out` e `cancelled` saem da logica de atraso.
- Alterar prazo ou status de um periodo pendente/programado limpa `notified_at`, reabrindo o ciclo de alerta.
- O escopo por condominio segue `user_roles.condominium_id`: usuario tenant-wide ve todos os condominios ativos; usuario escopado ve apenas seu condominio.

## Alertas

- Comando: `employees:notify-vacations`
- Agendamento: `routes/console.php`, diariamente as 08:15, com `withoutOverlapping()`.
- Notificacao: `EmployeeVacationDue`, canais `database`, `mail` e `broadcast`.
- Destinatarios: usuarios ativos do tenant com `employees:read`, respeitando escopo por condominio.

## Cronograma

O cronograma consolidado ganhou a fonte `employee_vacations`.

Regra:

- origem: `employee_vacation_periods.deadline_date`;
- somente periodos `pending` e `scheduled`;
- rota do evento aponta para `employees.show`;
- fonte aparece apenas com permissao `employees:read` e modulo `employees`.

## Rotas

| Metodo | URI | Nome | Permissao |
| --- | --- | --- | --- |
| GET | `/funcionarios` | `employees.index` | `employees:read` |
| GET | `/funcionarios/{employee}` | `employees.show` | `employees:read` |
| GET | `/funcionarios/criar` | `employees.create` | `employees:create` |
| POST | `/funcionarios` | `employees.store` | `employees:create` |
| GET | `/funcionarios/{employee}/editar` | `employees.edit` | `employees:update` |
| PUT/PATCH | `/funcionarios/{employee}` | `employees.update` | `employees:update` |
| DELETE | `/funcionarios/{employee}` | `employees.destroy` | `employees:delete` |
| POST | `/funcionarios/{employee}/ferias` | `employees.vacations.store` | `employees:update` |
| PUT/PATCH | `/funcionarios/ferias/{period}` | `employees.vacations.update` | `employees:update` |
| DELETE | `/funcionarios/ferias/{period}` | `employees.vacations.destroy` | `employees:delete` |

## Arquivos principais

- `database/migrations/2026_06_28_000001_create_employees_tables.php`
- `database/migrations/2026_06_28_000002_register_employees_permissions_and_module.php`
- `app/Models/Employee.php`
- `app/Models/EmployeeVacationPeriod.php`
- `app/Http/Controllers/Panel/EmployeeController.php`
- `app/Console/Commands/NotifyDueEmployeeVacations.php`
- `app/Notifications/EmployeeVacationDue.php`
- `resources/js/Pages/Employees/`
- `app/Http/Controllers/Panel/ScheduleController.php`
- `resources/js/Pages/Schedule/Index.tsx`
- `resources/js/Layouts/AppLayout.tsx`

## Deploy

Rodar:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
```

O entrypoint Docker atual ja executa migrate e seed de forma idempotente.
