# 07 — Andamento atual / handoff

> Atualizado em 09/06/2026.
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

- Em 09/06/2026, o usuario testou e finalizou o ajuste de `Admin > IA`: o campo `Modelo` fica em
  dropdown e troca a lista conforme o provedor selecionado (OpenAI, Gemini ou Claude/Anthropic).

- O usuário testou em produção/Easypanel a entrega de `Contas a pagar` + correção de troca de plano e informou que estava tudo certo.
- A correção de plano deixou a mudança de plano refletir acessos e módulos:
  - troca/suspensão/ativação de tenant limpa cache de domínio;
  - `CheckPermission` valida também módulo do plano;
  - portal, portaria, API keys e menus respeitam módulos ativos.

## Última entrega implementada

### D13. Cronograma consolidado

Implementado em 09/06/2026. Doc tecnica: `docs/tecnico/cronograma-consolidado.md`.

O que foi entregue:

- Novo modulo `schedule` habilitado nos planos Profissional, Business e Enterprise.
- Nova rota `/cronograma` (`schedule.index`) protegida por `schedule:read`.
- Menu lateral "Cronograma" visivel apenas quando permissao e modulo do plano permitem.
- Grade mensal com agenda lateral do mes.
- Filtros por condominio e fonte.
- Resumo de eventos totais, eventos de hoje, proximos 7 dias e atrasados.
- Fontes consolidadas:
  - reservas pendentes/aprovadas;
  - assembleias agendadas;
  - proximas manutencoes preventivas;
  - inicio, prazo e conclusao de obras/reformas;
  - prazos de SLA de ocorrencias abertas;
  - contas a pagar abertas;
  - cobrancas abertas.
- O cronograma respeita permissao/modulo de cada fonte. Ex.: sem `financial`, nao mostra
  `expenses` nem `charges`.
- O escopo por condominio segue a mesma regra do assistente IA: usuarios tenant-wide veem todos
  os condominios ativos; usuarios escopados por `user_roles.condominium_id` veem apenas seu escopo.

Arquivos-chave:

- `app/Http/Controllers/Panel/ScheduleController.php`
- `resources/js/Pages/Schedule/Index.tsx`
- `resources/js/Layouts/AppLayout.tsx`
- `routes/web.php`
- `database/seeders/PermissionSeeder.php`
- `database/seeders/RoleSeeder.php`
- `database/seeders/PlanSeeder.php`
- `app/Http/Middleware/CheckPermission.php`
- `app/Http/Controllers/Admin/PlanController.php`

Validacoes feitas:

- `php -l` nos PHP alterados/criados.
- `php artisan route:list --name=schedule --except-vendor` passou.
- `npm run build` passou.
- `git diff --check` passou, apenas com avisos CRLF do Windows.

### C12. Obras/Reformas

Implementado em 09/06/2026. Doc tecnica: `docs/tecnico/obras.md`.

O que foi entregue:

- Novo modulo `works` habilitado nos planos Profissional, Business e Enterprise.
- Novas tabelas `works` e `work_updates`.
- `expenses.work_id` vincula contas a pagar a uma obra/reforma.
- Cadastro, listagem, edicao e detalhe de obras por condominio.
- Campos de tipo, status, prioridade, cronograma, progresso, fornecedor, responsavel, orcamento
  previsto e custo final.
- Anexos por `StorageObject` com `entity_type = work`.
- Linha do tempo de andamentos, com opcao de atualizar status/progresso.
- Criacao de conta a pagar a partir da obra, respeitando `expenses:create` e modulo `financial`.
- Aprovacao de proposta em Orcamentos pode gerar obra/reforma automaticamente.
- Se a aprovacao de orcamento gerar obra e conta no mesmo ato, a conta fica vinculada por
  `quotation_proposal_id` e `work_id`.
- Contas a pagar mostram origem em obra na listagem e na edicao.

Arquivos-chave:

- `database/migrations/2026_06_26_000001_create_works_tables.php`
- `app/Models/Work.php`
- `app/Models/WorkUpdate.php`
- `app/Http/Controllers/Panel/WorkController.php`
- `app/Http/Controllers/Panel/QuotationController.php`
- `app/Http/Controllers/Panel/ExpenseController.php`
- `app/Http/Controllers/AttachmentController.php`
- `resources/js/Pages/Works/`
- `resources/js/Pages/Quotations/Show.tsx`
- `resources/js/Pages/Expenses/Index.tsx`
- `resources/js/Pages/Expenses/Edit.tsx`
- `routes/web.php`

Validacoes feitas:

- `php -l` nos PHP alterados/criados.
- `php artisan route:list --name=works --except-vendor` passou.
- `php artisan route:list --name=quotations --except-vendor` passou.
- `php artisan route:list --name=expenses --except-vendor` passou.
- `npm run build` passou.
- `git diff --check` passou, apenas com avisos CRLF do Windows.

### Assistente IA condominial - blocos 1 a 6

Implementado em 08/06/2026. O roadmap detalhado esta em
`docs/produto/08-roadmap-assistente-ia.md`.

O que foi entregue:

- **Bloco 1 - Admin > IA**
  - Nova configuracao global em `Admin > IA` (`/admin/ia`).
  - Nova tabela/model `ai_settings`, com chave criptografada.
  - Superadmin define provedor, modelo, URL base, chave, status ativo e testa conexao.
  - Campo `Modelo` e um dropdown dependente do `Provedor`, com modelos pre-configurados por OpenAI,
    Gemini e Claude/Anthropic.
  - O Assistente deixou de orientar uso direto de `ANTHROPIC_API_KEY` na tela do tenant.

- **Bloco 2 - Provedores**
  - Nova interface `AiProviderClient`.
  - Novo `AiProviderManager`, que escolhe o cliente conforme `ai_settings`.
  - Clientes HTTP para Claude/Anthropic, OpenAI Responses API e Gemini `generateContent`.
  - `AssistantService` e `AssemblyService` passaram a usar o manager, nao `ClaudeClient` diretamente.

- **Bloco 3 - Limites mensais por tenant**
  - Novo recurso de plano `ai_interactions_monthly`.
  - Nova migration `2026_06_24_000002_add_ai_interactions_monthly_limits.php`.
  - `PlanLimitService` passou a resetar counters mensais pelo ciclo da assinatura
    (`tenant_plan_subscriptions.starts_at`).
  - Perfil do tenant em `Admin > Tenants > detalhe` mostra uso de IA, limite, saldo, data de renovacao,
    origem do limite e permite override ou voltar ao plano.
  - Assistente mostra saldo mensal, bloqueia quando a cota acaba e incrementa consumo apos resposta
    bem-sucedida.

- **Bloco 4 - Documentos atuais do condominio**
  - Categorias padrao de documentos foram expandidas para convencao, regimento interno, ata,
    contrato, circular, comprovante e outros.
  - Nova migration `2026_06_25_000001_add_ai_controls_to_documents_table.php`.
  - Documentos ganharam os controles `Atual` e `Consultar pela IA`.
  - Listagem de documentos ganhou filtros por atualidade e consulta pela IA.
  - `DocumentIndexer`, `DocumentSearch` e `documents:index` passaram a considerar por padrao apenas
    documentos atuais e liberados para IA.
  - Ao editar atualidade, liberacao para IA ou condominio, o controller reindexa ou remove chunks.

- **Bloco 5 - Base legal global**
  - Nova base global em `Admin > IA` para documentos legais da plataforma.
  - Novas tabelas `ai_legal_documents` e `ai_legal_document_chunks`.
  - Upload de Codigo Civil, Codigo Penal, leis condominiais, jurisprudencia, orientacoes da plataforma,
    materiais de referencia e outros.
  - Base legal usa storage global em `global/ai/legal/...`, sem cota de tenant.
  - `LegalDocumentIndexer`, `LegalDocumentSearch`, `IndexAiLegalDocument` e
    `ai-legal-documents:index` foram adicionados.
  - `AssistantService` combina documentos atuais/liberados do tenant com a base legal global ativa.

- **Bloco 6 - Fluxo final do assistente para o sindico**
  - Conversas de IA ganharam `condominium_id` e mensagens ganharam `sources` JSON.
  - Tela `/assistente` seleciona automaticamente quando ha um unico condominio acessivel e exige
    dropdown quando ha mais de um.
  - Usuarios com papeis escopados por condominio so listam/abrem conversas do seu escopo.
  - Busca documental do RAG passou a filtrar documentos atuais/liberados pelo condominio selecionado.
  - O contexto estruturado tambem filtra inadimplencia, ocorrencias, reservas e comunicados pelo
    condominio selecionado.
  - Respostas do chat salvam e exibem fontes consultadas com marcadores `[D#]` e `[L#]`.
  - Guardrails foram reforcados para nao inventar informacao, citar fontes e tratar base legal como
    apoio informativo, nao parecer juridico definitivo.

Arquivos-chave:

- `database/migrations/2026_06_24_000001_create_ai_settings_table.php`
- `database/migrations/2026_06_24_000002_add_ai_interactions_monthly_limits.php`
- `database/migrations/2026_06_25_000001_add_ai_controls_to_documents_table.php`
- `database/migrations/2026_06_25_000002_create_ai_legal_documents_tables.php`
- `database/migrations/2026_06_25_000003_scope_ai_conversations_by_condominium.php`
- `app/Models/AiConversation.php`
- `app/Models/AiMessage.php`
- `app/Models/AiSetting.php`
- `app/Models/AiLegalDocument.php`
- `app/Models/AiLegalDocumentChunk.php`
- `app/Models/Document.php`
- `app/Jobs/IndexAiLegalDocument.php`
- `app/Services/AI/AiProviderClient.php`
- `app/Services/AI/AiProviderManager.php`
- `app/Services/AI/AiSettingsManager.php`
- `app/Services/AI/ClaudeClient.php`
- `app/Services/AI/OpenAiClient.php`
- `app/Services/AI/GeminiClient.php`
- `app/Services/AI/DocumentTextExtractor.php`
- `app/Services/AI/DocumentIndexer.php`
- `app/Services/AI/DocumentSearch.php`
- `app/Services/AI/LegalDocumentIndexer.php`
- `app/Services/AI/LegalDocumentSearch.php`
- `app/Services/AI/AssistantService.php`
- `app/Console/Commands/IndexAiLegalDocuments.php`
- `app/Services/PlanLimitService.php`
- `app/Http/Controllers/Admin/AiSettingController.php`
- `app/Http/Controllers/Panel/DocumentController.php`
- `app/Http/Controllers/Admin/TenantController.php`
- `app/Http/Controllers/Panel/AssistantController.php`
- `resources/js/Pages/Documents/`
- `resources/js/Pages/Admin/AI/Settings.tsx`
- `resources/js/Pages/Admin/Tenants/Show.tsx`
- `resources/js/Pages/IA/Assistant.tsx`
- `routes/admin.php`
- `docs/tecnico/ia-assistente.md`

Validacoes feitas:

- Em 09/06/2026, validacao funcional manual do usuario no painel `Admin > IA` concluida.
- `php -l` nos PHP alterados/criados.
- `php artisan route:list --name=assistant --except-vendor` passou.
- `npm run build` passou.
- `git diff --check` passou.
- `php artisan test` nao roda porque `phpunit.xml.dist` nao existe no repo.

Observacoes para retomada:

- A tela `Admin > IA` foi validada pelo usuario em 09/06/2026. Testes contra provedores externos
  continuam dependendo de chaves validas, permissao ao modelo e billing/projeto corretos no provedor.
- Rodar `php artisan migrate --force` no ambiente antes de testar.
- Se seeders forem usados para atualizar planos existentes, rodar tambem `php artisan db:seed --force`.
- `public/build` pode mudar quando `npm run build` for executado; isso e esperado porque assets buildados
  estao versionados neste repo.

Proximo bloco recomendado:

- **D10. Relatorios consolidados multi-condominio**:
  - consolidar indicadores operacionais e financeiros por condominio;
  - permitir periodo, modulos e exportacao;
  - aproveitar o cronograma consolidado como uma das bases de prazos/eventos.

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
- `php artisan route:list --name=works --except-vendor`
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

O próximo item natural do roadmap é **D10. Relatórios consolidados multi-condomínio**.

Motivo: o sistema agora tem dados operacionais ricos em fornecedores, manutenção, orçamentos,
obras, contas a pagar, ocorrências, reservas e cronograma. A próxima evolução é transformar isso
em relatórios executivos comparáveis por período e condomínio.

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
