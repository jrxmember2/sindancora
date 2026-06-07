# Financeiro (Fase 5 — núcleo manual)

> Status: implementado o financeiro **manual** (cobranças, contas a pagar, inadimplência, relatórios e
> "minhas cobranças" no portal). A integração com gateway **Asaas (boleto/PIX/webhook)** foi
> entregue na Fase 5.4 — ver `docs/tecnico/financeiro-asaas.md`.

## Modelo de dados

- `charges` (model `App\Models\Charge`): cobrança por unidade. Campos-chave: `condominium_id`,
  `unit_id`, `person_id` (responsável — snapshot na geração), `batch_id` (agrupa geração em lote),
  `type` (`condo_fee/extra/fine/other`), `reference_month` (YYYY-MM), `amount`, `due_date`,
  `fine_rate`/`interest_rate` (%), `status` (`pending/paid/overdue/cancelled`), `paid_at`,
  `paid_amount`, `payment_method`, `receipt_storage_object_id`. Soft delete.
- `expenses` (model `App\Models\Expense`): contas a pagar/despesas do condomínio. Campos-chave:
  `category`, `description`, `amount`, `status` (`pending/paid/overdue/cancelled`), `due_date`,
  `expense_date` (competência), `paid_at`, `paid_amount`, `payment_method`, `supplier_id` opcional,
  `supplier` livre para legado, `maintenance_record_id` quando veio de manutenção, `document_number`,
  `reminder_days`, `reminder_sent_at` e comprovante/
  nota fiscal (`receipt_storage_object_id`). Soft delete.
- Relações: `Unit::charges()`, `Condominium::charges()`/`expenses()`.

### Valor atualizado

`Charge::currentAmount()` calcula (não persiste) principal + multa (`fine_rate`%) + juros
(`interest_rate`% ao mês, pró-rata por dia) quando a cobrança está vencida. Usado no detalhe, no
portal e no relatório de inadimplência.

## Permissões

`charges:create|read|update|delete|mark_paid` e `expenses:create|read|update|delete` (PermissionSeeder).
No RoleSeeder: **admin** tem tudo; **síndico** opera cobranças (create/read/update/mark_paid),
contas a pagar (read/create/update) e relatórios (read/export). `reports:read|export` já existiam.
Gating das rotas passa por `permission:` e o middleware `CheckPermission` também valida o módulo do
plano (`financial` para cobranças/contas a pagar, `reports` para relatórios). O menu Inertia recebe
`tenant.plan.modules` para esconder itens fora do plano; troca/suspensão/ativação de tenant limpa o
cache `tenant:domain:*`.

## Cobranças (painel)

`App\Http\Controllers\Panel\ChargeController` + `App\Services\ChargeService`:
- CRUD + `registerPayment` (status `paid`, comprovante via `StorageService` entityType
  `charge_receipt`) + `download` (URL assinada/streaming).
- **Geração em lote** (`generateForm` → `generatePreview` [JSON] → `generateConfirm`): escolhe
  condomínio + valor base; a prévia lista as unidades ativas com o morador principal sugerido e valor
  editável por unidade; confirma criando o lote (mesmo `batch_id`). Espelha o import CSV de Unidades.
- Páginas `resources/js/Pages/Charges/` (`Index` com KPIs, `Create`/`Edit` via `ChargeForm`, `Show`
  com modal de pagamento, `Generate`).

## Contas a pagar (painel)

`App\Http\Controllers\Panel\ExpenseController`: CRUD de contas a pagar + comprovante/nota fiscal
(`expense_receipt`) + baixa rápida (`POST /despesas/{expense}/pagar`). Páginas
`resources/js/Pages/Expenses/` (`Index`/`Create`/`Edit` + `ExpenseFields`).

- Lançamentos antigos são migrados como `status=paid`, com `due_date=expense_date`,
  `paid_at=expense_date` e `paid_amount=amount`, preservando os relatórios históricos.
- Listagem mostra KPIs de aberto, vencido, próximos 7 dias e pago no mês; filtros por condomínio,
  status, categoria, fornecedor e vencimento.
- Fornecedor pode ser vinculado a `suppliers` (`supplier_id`) ou informado em texto livre (`supplier`)
  para compatibilidade.
- Contas geradas por execução de manutenção usam `maintenance_record_id`, preservando a trilha:
  manutenção -> execução -> conta a pagar. A criação automática exige `maintenance:update`,
  `expenses:create` e módulo `financial` ativo no plano.
- Contas geradas por proposta aprovada de orçamento usam `quotation_proposal_id`, preservando a
  trilha: orçamento -> proposta aprovada -> conta a pagar. A criação automática exige
  `quotations:approve`, `expenses:create` e módulo `financial` ativo.
- Lembrete: comando `expenses:notify-due` roda diariamente no scheduler e notifica gestores ativos
  quando `days_until_due <= reminder_days`; marca `reminder_sent_at` para não repetir.

## Inadimplência + notificações

- Comando `charges:mark-overdue` (`App\Console\Commands\MarkOverdueCharges`) agendado em
  `routes/console.php` (`dailyAt('06:00')`): roda sem tenant (varre todos), flipa `pending` vencidas
  para `overdue` e notifica o morador (User do morador principal da unidade).
- Notificação `App\Notifications\ChargeOverdue` (canais `database` + `mail`, enfileirada). O sino do
  portal já renderiza pelo padrão title/message/url/icon.
- A inadimplência é **informativa** (sem bloqueio de reservas — adiado).

## Relatórios + exportação

- `App\Http\Controllers\Panel\ReportController`: agrega por período (cobrado, recebido, em aberto,
  vencido, contas pagas, **saldo** = recebido − contas pagas), quebra mensal e inadimplência por
  unidade. Contas pendentes não reduzem o saldo até a baixa (`status=paid`/`paid_at`).
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

`php -l` nos arquivos PHP alterados; `php artisan route:list --name=expenses` mostra 8 rotas de
contas a pagar, incluindo `expenses.pay`; `php artisan list expenses` mostra `expenses:notify-due`;
`npm run build` (`tsc && vite build`) verde. Migrations **não** rodadas localmente (banco pode ser
prod). Deploy: `migrate --force` + `optimize:clear`; `db:seed --force` atualiza matriz de módulos dos
planos (`suppliers`/`maintenance` e desabilitação dos não listados); o supervisor já roda o scheduler.
