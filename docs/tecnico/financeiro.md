# Financeiro (Fase 5 — núcleo manual)

> Status: implementado o financeiro **manual** (cobranças, despesas, inadimplência, relatórios e
> "minhas cobranças" no portal). A integração com gateway **Asaas (boleto/PIX/webhook)** foi
> entregue na Fase 5.4 — ver `docs/tecnico/financeiro-asaas.md`.

## Modelo de dados

- `charges` (model `App\Models\Charge`): cobrança por unidade. Campos-chave: `condominium_id`,
  `unit_id`, `person_id` (responsável — snapshot na geração), `batch_id` (agrupa geração em lote),
  `type` (`condo_fee/extra/fine/other`), `reference_month` (YYYY-MM), `amount`, `due_date`,
  `fine_rate`/`interest_rate` (%), `status` (`pending/paid/overdue/cancelled`), `paid_at`,
  `paid_amount`, `payment_method`, `receipt_storage_object_id`. Soft delete.
- `expenses` (model `App\Models\Expense`): lançamento de despesa do condomínio (`category`,
  `description`, `amount`, `expense_date`, `supplier`, comprovante). Soft delete.
- Relações: `Unit::charges()`, `Condominium::charges()`/`expenses()`.

### Valor atualizado

`Charge::currentAmount()` calcula (não persiste) principal + multa (`fine_rate`%) + juros
(`interest_rate`% ao mês, pró-rata por dia) quando a cobrança está vencida. Usado no detalhe, no
portal e no relatório de inadimplência.

## Permissões

`charges:create|read|update|delete|mark_paid` e `expenses:create|read|update|delete` (PermissionSeeder).
No RoleSeeder: **admin** tem tudo; **síndico** opera cobranças (create/read/update/mark_paid),
despesas (read/create/update) e relatórios (read/export). `reports:read|export` já existiam.
Gating das rotas é **só por `permission:`** (convenção do `web.php`).

## Cobranças (painel)

`App\Http\Controllers\Panel\ChargeController` + `App\Services\ChargeService`:
- CRUD + `registerPayment` (status `paid`, comprovante via `StorageService` entityType
  `charge_receipt`) + `download` (URL assinada/streaming).
- **Geração em lote** (`generateForm` → `generatePreview` [JSON] → `generateConfirm`): escolhe
  condomínio + valor base; a prévia lista as unidades ativas com o morador principal sugerido e valor
  editável por unidade; confirma criando o lote (mesmo `batch_id`). Espelha o import CSV de Unidades.
- Páginas `resources/js/Pages/Charges/` (`Index` com KPIs, `Create`/`Edit` via `ChargeForm`, `Show`
  com modal de pagamento, `Generate`).

## Despesas (painel)

`App\Http\Controllers\Panel\ExpenseController`: CRUD + comprovante (`expense_receipt`). Páginas
`resources/js/Pages/Expenses/` (`Index`/`Create`/`Edit` + `ExpenseFields`).

## Inadimplência + notificações

- Comando `charges:mark-overdue` (`App\Console\Commands\MarkOverdueCharges`) agendado em
  `routes/console.php` (`dailyAt('06:00')`): roda sem tenant (varre todos), flipa `pending` vencidas
  para `overdue` e notifica o morador (User do morador principal da unidade).
- Notificação `App\Notifications\ChargeOverdue` (canais `database` + `mail`, enfileirada). O sino do
  portal já renderiza pelo padrão title/message/url/icon.
- A inadimplência é **informativa** (sem bloqueio de reservas — adiado).

## Relatórios + exportação

- `App\Http\Controllers\Panel\ReportController`: agrega por período (cobrado, recebido, em aberto,
  vencido, despesas, **saldo** = recebido − despesas), quebra mensal e inadimplência por unidade.
- Exportação: **PDF** via `barryvdh/laravel-dompdf` (view `resources/views/reports/financial.blade.php`,
  prestação de contas) e **XLSX** via `maatwebsite/excel` (`App\Exports\FinancialReportExport`).
- Página `resources/js/Pages/Reports/Financial.tsx` (cards + tabela mensal + inadimplentes + export).

### Dependências e ambiente

`composer require barryvdh/laravel-dompdf maatwebsite/excel`. `maatwebsite/excel` puxa
`phpoffice/phpspreadsheet`, que exige as extensões PHP **gd** e **zip** — **já presentes no Dockerfile
de produção** (linhas de `docker-php-ext-install zip` e `gd`). No XAMPP local essas extensões podem
não estar habilitadas; por isso o `composer require` local usa `--ignore-platform-reqs` (a geração de
XLSX/PDF só é exercida em runtime, onde o container tem as extensões).

## Portal "Minhas cobranças"

`App\Http\Controllers\Portal\ChargeController` (trait `InteractsWithResident`): `index` (cobranças das
unidades do morador, separadas em aberto/pagas), `show`, `download` do comprovante. Item "Cobranças"
no `PortalLayout` e card de total em aberto no `Portal/Dashboard`. Sem boleto/PIX (Asaas adiado).

## Integração Asaas (Fase 5.4)

Entregue em fatia dedicada: config por tenant, boleto/PIX por cobrança, webhook de conciliação e 2ª
via por e-mail. Documentação completa em `docs/tecnico/financeiro-asaas.md`.

## Fora de escopo (adiado)

Bloqueio de inadimplente em reservas; split/repasse por wallet; assinaturas recorrentes nativas do
Asaas; estornos parciais.

## Validação

`php -l` em todos os arquivos; `route:list` (25 rotas de painel financeiro + 3 `portal.charges`);
`artisan list` mostra `charges:mark-overdue`; `npm run build` (tsc + Vite) verde. Migrations **não**
rodadas localmente (banco pode ser prod). Deploy: `migrate --force` + `optimize:clear`; `db:seed
--force` (entrypoint) aplica as permissões `charges:delete`/`expenses:*`; o supervisor já roda o
scheduler.
