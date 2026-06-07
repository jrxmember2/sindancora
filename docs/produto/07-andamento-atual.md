# 07 — Andamento atual / handoff

> Atualizado em 07/06/2026.
> Objetivo: dar contexto rápido para Codex/Claude ou outro agente continuar o Sindâncora sem
> redescobrir o estado do projeto.

## Regra do jogo

- Não copiar código, marca, textos, identidade visual, banco, assets ou telas idênticas de concorrentes.
- `docs/mapeamento-concorrente/` e `C:\Users\JUNIOR\sindigestcopy` são referência funcional/mercado.
- Manter SaaS multitenant: todo dado operacional precisa ter `tenant_id` direto ou isolamento indireto claro.
- Respeitar planos/módulos em backend e frontend. Menu escondido não basta; rota/serviço também precisa bloquear.
- Sempre atualizar documentação técnica quando entregar recurso relevante.
- Projeto roda em produção via Easypanel/Docker.

## Estado confirmado

- O usuário testou em produção/Easypanel a entrega de `Contas a pagar` + correção de troca de plano e informou que estava tudo certo.
- A correção de plano deixou a mudança de plano refletir acessos e módulos:
  - troca/suspensão/ativação de tenant limpa cache de domínio;
  - `CheckPermission` valida também módulo do plano;
  - portal, portaria, API keys e menus respeitam módulos ativos.

## Última entrega implementada

### Correções de plano + identidade do tenant/condomínio

Implementado após C7.

O que foi entregue:

- `PlanLimitService` passou a calcular uso real para recursos permanentes (`condominiums`, `units`,
  `users`, `residents`, `storage_mb`) e sincronizar `tenant_usage_counters`.
- Downgrade de plano agora bloqueia criação acima do limite mesmo que o contador antigo esteja
  defasado. Exemplo: Enterprise -> Starter com 1 condomínio já cadastrado impede cadastrar o 2º.
- `Admin\TenantController::changePlan()` sincroniza contadores permanentes após troca de plano.
- Erros web de limite de plano/storage voltam com flash de erro em vez de página técnica.
- Cadastro/edição de condomínio aceita logo (`StorageObject`, `entity_type = condominium_logo`) e a
  listagem mostra thumbnail.
- API de condomínios expõe `logo_url`.
- Nova tela **Configurações -> Dados do tenant** (`settings.tenant.*`) para PF/PJ, razão social/nome,
  nome fantasia, CPF/CNPJ, telefone, e-mail, endereço, cor principal e logo.
- Logo do tenant usa `StorageObject` (`entity_type = tenant_logo`) e fica referenciada em
  `tenants.settings.brand.logo_storage_object_id`.
- `Tenant::getReportProfile()` centraliza os dados para relatórios; o PDF financeiro já usa esse
  perfil no cabeçalho e deixa Sindâncora apenas como assinatura de sistema.
- Doc técnica: `docs/tecnico/identidade-tenant-e-limites.md`.

Arquivos-chave:

- `app/Services/PlanLimitService.php`
- `app/Http/Controllers/Panel/CondominiumController.php`
- `app/Http/Controllers/Panel/TenantProfileController.php`
- `app/Models/Condominium.php`
- `app/Models/Tenant.php`
- `resources/js/Pages/Condominiums/Index.tsx`
- `resources/js/Pages/Condominiums/Create.tsx`
- `resources/js/Pages/Condominiums/Edit.tsx`
- `resources/js/Pages/Settings/TenantProfile.tsx`
- `resources/views/reports/financial.blade.php`

## Entregas anteriores recentes

### C7. Orçamentos/Cotações

Implementado nesta rodada, ainda exigindo deploy/migration no Easypanel depois do commit/push.

O que foi entregue:

- Novo módulo `quotations` habilitado nos planos Profissional, Business e Enterprise.
- Cadastro/listagem/edição de orçamentos por condomínio, categoria, prazo e status.
- Cadastro de propostas por fornecedor com valor, validade, prazo de execução, observações e anexos.
- Comparativo de propostas na tela do orçamento, com menor valor em destaque.
- Aprovação transacional de proposta:
  - marca a proposta aprovada;
  - rejeita as demais propostas recebidas;
  - fecha o orçamento como aprovado;
  - guarda aprovador, data e proposta vencedora.
- Aprovação pode gerar manutenção preventiva e/ou conta a pagar, respeitando permissões e módulos do plano.
- Contas a pagar e manutenção passam a mostrar a origem quando vierem de orçamento aprovado.
- Fornecedores passam a mostrar quantidade e histórico recente de propostas em orçamentos.
- Anexos usam `StorageObject` com `entity_type = quotation_proposal`.
- Docs atualizados:
  - `docs/tecnico/orcamentos.md`;
  - `docs/tecnico/fornecedores.md`;
  - `docs/tecnico/financeiro.md`;
  - `docs/tecnico/manutencao-preventiva.md`;
  - `docs/produto/04-planos-limites-e-storage.md`;
  - `docs/produto/06-roadmap-nova-onda.md`.

Arquivos-chave:

- `database/migrations/2026_06_23_000001_create_quotations_tables.php`
- `app/Models/Quotation.php`
- `app/Models/QuotationProposal.php`
- `app/Http/Controllers/Panel/QuotationController.php`
- `app/Http/Controllers/AttachmentController.php`
- `app/Models/Expense.php`
- `app/Models/MaintenancePlan.php`
- `app/Models/Supplier.php`
- `resources/js/Pages/Quotations/`
- `resources/js/Pages/Expenses/Index.tsx`
- `resources/js/Pages/Expenses/Edit.tsx`
- `resources/js/Pages/Maintenance/Show.tsx`
- `resources/js/Pages/Suppliers/Index.tsx`
- `resources/js/Pages/Suppliers/Show.tsx`
- `routes/web.php`

### Cadastro de unidades com PF/PJ para proprietários e inquilinos

Implementado após a entrega de fornecedores/manutenção. Escopo propositalmente limitado: **sem busca
externa em base pública** por CPF/CNPJ neste momento.

O que foi entregue:

- Proprietários e inquilinos no formulário de criar/editar unidade ganharam dropdown **Pessoa física /
  Pessoa jurídica**.
- PF mantém nome + CPF + nascimento; PJ usa razão social + CNPJ e não exibe nascimento.
- Familiares continuam como PF.
- `persons.person_type` foi adicionado com default `individual`.
- O campo legado `persons.cpf` continua guardando os dígitos do CPF ou CNPJ para manter
  compatibilidade com cobranças, portal, integrações e telas antigas.
- O backend aceita `document` e também mantém compatibilidade com payload antigo via `cpf`.
- Doc técnica atualizada em `docs/tecnico/cadastro-unidade.md`.

Arquivos-chave:

- `database/migrations/2026_06_22_000002_add_person_type_to_persons.php`
- `app/Models/Person.php`
- `app/Services/UnitRosterService.php`
- `app/Http/Controllers/Panel/UnitController.php`
- `resources/js/Pages/Units/Form.tsx`
- `resources/js/Pages/Units/Edit.tsx`

### Fornecedores + Manutenção integrada com Contas a pagar

Implementado nesta última rodada, ainda exigindo deploy/migration no Easypanel se não tiver sido feito depois deste handoff.

O que foi entregue:

- Nova migration `database/migrations/2026_06_22_000001_link_maintenance_records_to_expenses.php`.
- `expenses.maintenance_record_id` vincula uma conta a pagar à execução de manutenção que a originou.
- `MaintenanceService::registerExecution()` pode criar a conta a pagar na mesma transação da execução.
- Regra de acesso para gerar conta pela manutenção:
  - exige `maintenance:update`;
  - exige `expenses:create`;
  - exige módulo `financial` ativo no plano;
  - super admin mantém bypass global.
- Tela de manutenção (`resources/js/Pages/Maintenance/Show.tsx`):
  - form de execução ganhou opção **Gerar conta a pagar**;
  - informa vencimento, número do documento e lembrete;
  - histórico mostra a conta gerada e link para edição/listagem quando permitido.
- Contas a pagar:
  - listagem mostra a origem quando a conta veio de manutenção;
  - edição mostra um box com a origem da manutenção.
- Fornecedores:
  - index mostra manutenções ativas e total em aberto;
  - detalhe mostra cards de manutenção/financeiro, manutenções vinculadas, execuções recentes e contas do fornecedor.
- Docs atualizados:
  - `docs/tecnico/manutencao-preventiva.md`;
  - `docs/tecnico/fornecedores.md`;
  - `docs/tecnico/financeiro.md`;
  - `docs/produto/06-roadmap-nova-onda.md`.

Arquivos-chave:

- `app/Services/MaintenanceService.php`
- `app/Http/Controllers/Panel/MaintenanceController.php`
- `app/Http/Controllers/Panel/SupplierController.php`
- `app/Http/Controllers/Panel/ExpenseController.php`
- `app/Models/Expense.php`
- `app/Models/MaintenanceRecord.php`
- `app/Models/Supplier.php`
- `resources/js/Pages/Maintenance/Show.tsx`
- `resources/js/Pages/Suppliers/Index.tsx`
- `resources/js/Pages/Suppliers/Show.tsx`
- `resources/js/Pages/Expenses/Index.tsx`
- `resources/js/Pages/Expenses/Edit.tsx`

## Validações locais já feitas

- `php -l` nos PHP alterados.
- `php artisan route:list --name=settings.tenant --except-vendor`
- `php artisan route:list --name=condominiums --except-vendor`
- `php artisan route:list --name=quotations --except-vendor`
- `php artisan route:list --name=maintenance --except-vendor`
- `php artisan route:list --name=suppliers --except-vendor`
- `php artisan route:list --name=expenses --except-vendor`
- `php artisan route:list --name=condominiums.units --except-vendor`
- `npm run build` passou (`tsc && vite build`).
- `git diff --check` passou.

Observação: `npm run build` regenera muitos arquivos com hash em `public/build`. Isso é esperado neste repositório porque os assets buildados estão versionados.

## Pós-deploy no Easypanel

Depois de commitar/pushar a última entrega, rodar no container da aplicação:

```bash
php artisan migrate --force
php artisan optimize:clear
```

Se houver alteração de permissões/planos em alguma entrega futura, também rodar:

```bash
php artisan db:seed --force
```

O scheduler já é importante para:

- `documents:notify-expiring`;
- `maintenance:notify-due`;
- `expenses:notify-due`;
- rotinas financeiras existentes.

Confirmar no Easypanel se há worker/cron com `php artisan schedule:run` ou `php artisan schedule:work`.

## Próximo passo sugerido

O próximo item natural do roadmap é **C12. Obras**:

- cadastro de obra/reforma por condomínio;
- vínculo opcional com orçamento/proposta aprovada;
- orçamento previsto vs custo final;
- cronograma/status;
- fornecedores envolvidos;
- anexos e histórico;
- vínculo com contas a pagar.

Motivo: fornecedores, manutenção, contas a pagar e orçamentos agora estão integrados. Obras deve usar essa base para fechar o ciclo de contratação, execução e controle financeiro.

## Cuidados para a próxima implementação

- Não criar fluxo financeiro que ignore plano `financial`.
- Não criar telas só no frontend; proteger rotas/serviços no backend.
- Evitar duplicar fornecedor em texto livre quando houver `supplier_id`.
- Em vínculos financeiros, preservar trilha de auditoria: origem -> execução/orçamento/obra -> conta.
- Antes de finalizar qualquer próxima entrega, rodar:

```bash
php -l <arquivos PHP alterados>
php artisan route:list --name=<modulo> --except-vendor
npm run build
git diff --check
```
