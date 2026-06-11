# 07 — Andamento atual / handoff

> Atualizado em 11/06/2026.
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

### X3. Links públicos + QR por condomínio

Implementado em 09/06/2026. Doc técnica: `docs/tecnico/links-publicos.md`. **Fecha o roadmap da
Nova Onda** (`06-roadmap-nova-onda.md`).

O que foi entregue:

- Novo módulo `public_links` habilitado em **todos os planos** (inclusive Starter).
- Novas tabelas `condominium_public_links` (token/QR por condomínio) e `public_submissions`
  (fila de moderação).
- Páginas públicas sem login em `/p/{token}`: landing, auto-cadastro de morador e abertura de
  ocorrência. Tenant resolvido pelo domínio; token escopado ao tenant; POSTs com `throttle:10,1`.
- Gestão no painel em `/links-publicos`: gerar/rotacionar token, copiar URL, QR, ativar/desativar e
  habilitar cada ação, com contador de pendências.
- Moderação em `/links-publicos/moderacao`: fila filtrável e detalhe com aprovar/reprovar.
- Aprovar auto-cadastro cria/reaproveita a Pessoa, vincula à unidade (gestor pode ajustar
  unidade/relação) e envia convite ao portal **opcional** (e-mail/WhatsApp).
- Aprovar ocorrência cria a Ocorrência aberta com contato anexado e SLA calculado pela prioridade.
- Escopo por condomínio via `ScopesCondominiumsByRole` (mesma regra de Funcionários/Cronograma).
- Notificação `PublicSubmissionReceived` aos gestores, respeitando preferências granulares (D11).

Arquivos-chave:

- `database/migrations/2026_06_30_000001_create_condominium_public_links_table.php`
- `database/migrations/2026_06_30_000002_create_public_submissions_table.php`
- `database/migrations/2026_06_30_000003_register_public_links_permissions_and_module.php`
- `app/Models/CondominiumPublicLink.php`, `app/Models/PublicSubmission.php`
- `app/Services/PublicSubmissionService.php`
- `app/Http/Controllers/PublicIntakeController.php`
- `app/Http/Controllers/Panel/PublicLinkController.php`
- `app/Http/Controllers/Panel/PublicSubmissionController.php`
- `app/Http/Controllers/Concerns/ScopesCondominiumsByRole.php`
- `app/Notifications/PublicSubmissionReceived.php`
- `resources/js/Pages/Public/`, `resources/js/Pages/PublicLinks/`, `resources/js/Layouts/PublicLayout.tsx`
- `routes/web.php`, seeders (`PermissionSeeder`, `RoleSeeder`, `PlanSeeder`)

Validações feitas:

- `php -l` nos PHP alterados/criados.
- `php artisan route:list --name=public --except-vendor` (13 rotas).
- `npm run build` passou.
- `git diff --check` passou, apenas com avisos CRLF do Windows.

### D11. Perfil de usuario + preferencias de notificacao granulares

Implementado em 09/06/2026. Doc tecnica: `docs/tecnico/perfil-usuario-notificacoes.md`.

O que foi entregue:

- Pagina unica `/perfil` para superadmin, usuarios do painel e moradores do portal.
- Edicao de nome, e-mail e telefone.
- Troca de senha com validacao da senha atual.
- Upload/remocao de foto de usuario, com avatar exibido nos menus dos layouts.
- Nova tabela `user_notification_preferences` com matriz evento x canal por usuario.
- Registry central `NotificationPreferenceRegistry` para eventos/canais configuraveis.
- Trait `RespectsNotificationPreferences` para filtrar os canais retornados por `via()`.
- Notificacoes de comunicados, ocorrencias, SLA, reservas, portaria, financeiro, documentos,
  manutencoes e ferias agora respeitam opt-in/opt-out por usuario.
- `/portal/perfil` foi mantido como redirect para o perfil unificado.

Arquivos-chave:

- `database/migrations/2026_06_29_000001_create_user_notification_preferences.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Models/User.php`
- `app/Models/UserNotificationPreference.php`
- `app/Support/NotificationPreferenceRegistry.php`
- `app/Notifications/Concerns/RespectsNotificationPreferences.php`
- `resources/js/Pages/Profile/Edit.tsx`
- `resources/js/Layouts/AppLayout.tsx`
- `resources/js/Layouts/AdminLayout.tsx`
- `resources/js/Layouts/PortalLayout.tsx`
- `routes/web.php`
- `routes/portal.php`

Validacoes feitas:

- `php -l` nos PHP alterados/criados.
- `php artisan route:list --name=profile --except-vendor` passou.
- `npm run build` passou.
- `git diff --check` passou, apenas com avisos CRLF do Windows quando aplicavel.

### D9. Funcionarios + controle de ferias

Implementado em 09/06/2026. Doc tecnica: `docs/tecnico/funcionarios-ferias.md`.

O que foi entregue:

- Novo modulo `employees` habilitado nos planos Profissional, Business e Enterprise.
- Novas tabelas `employees` e `employee_vacation_periods`.
- CRUD de funcionarios por condominio, com documento, contato, cargo, tipo de vinculo, status,
  admissao, desligamento, CTPS, PIS/PASEP, salario e observacoes.
- Controle de periodos de ferias com periodo aquisitivo, prazo limite, datas de gozo, dias, status
  e observacoes.
- Cadastro pode criar automaticamente o primeiro periodo aquisitivo a partir da admissao.
- Alertas de ferias proximas/atrasadas via `employees:notify-vacations`, agendado diariamente as 08:15.
- Notificacao `EmployeeVacationDue` por banco, e-mail e broadcast.
- Escopo por condominio respeita `user_roles.condominium_id` no CRUD, alertas e cronograma.
- Cronograma consolidado ganhou a fonte `employee_vacations`.
- Menu lateral ganhou "Funcionarios" condicionado a permissao `employees:read` e modulo `employees`.
- Migration idempotente registra permissoes, vincula papeis padrao e habilita modulo nos planos.

Arquivos-chave:

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

Validacoes feitas:

- `php -l` nos PHP alterados/criados.
- `php artisan route:list --name=employees --except-vendor` passou.
- `php artisan route:list --name=schedule --except-vendor` passou.
- `npm run build` passou.
- `git diff --check` passou, apenas com avisos CRLF do Windows.

### D10. Relatorios consolidados multi-condominio

Implementado em 09/06/2026. Doc tecnica: `docs/tecnico/relatorios-consolidados.md`.

O que foi entregue:

- A rota existente `/relatorios` passou a abrir uma visao consolidada multi-condominio.
- O modulo continua protegido por `reports:read` e modulo de plano `reports`.
- Filtros por periodo, multiplos condominios e modulos.
- Escopo por condominio respeita `user_roles.condominium_id`: usuarios tenant-wide veem todos os
  condominios ativos; usuarios escopados veem somente seu escopo.
- Cada bloco interno respeita permissao e modulo da fonte:
  - financeiro depende de `financial`;
  - ocorrencias depende de `occurrences:read` e modulo `occurrences`;
  - reservas depende de `reservations:read` e modulo `reservations`;
  - manutencoes depende de `maintenance:read` e modulo `maintenance`;
  - obras depende de `works:read` e modulo `works`;
  - documentos depende de `documents:read` e modulo `documents`;
  - orcamentos depende de `quotations:read` e modulo `quotations`.
- KPIs gerais de estrutura, financeiro, inadimplencia, operacao e risco.
- Comparativo por condominio com saldo, inadimplencia, ocorrencias, SLA, manutencoes, obras,
  documentos e score de risco.
- Serie mensal com recebido, despesas e movimentos operacionais.
- Rankings de inadimplencia, risco operacional e contas pagas.
- Exports financeiros PDF/XLSX existentes foram preservados.

Arquivos-chave:

- `app/Http/Controllers/Panel/ReportController.php`
- `app/Services/Reports/ConsolidatedReportBuilder.php`
- `resources/js/Pages/Reports/Index.tsx`
- `docs/tecnico/relatorios-consolidados.md`

Validacoes feitas:

- `php -l app/Services/Reports/ConsolidatedReportBuilder.php` passou.
- `php -l app/Http/Controllers/Panel/ReportController.php` passou.
- `php artisan route:list --name=reports --except-vendor` passou.
- `npm run build` passou (`tsc && vite build`).
- `git diff --check` passou, apenas com avisos CRLF do Windows.
- `npm run typecheck` nao existe no `package.json`; o typecheck fica coberto pelo `npm run build`.

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

Proximo bloco recomendado atualizado:

- **X3. Links publicos + QR por condominio** — concluido em 09/06/2026 (auto-cadastro de morador,
  abertura publica de ocorrencia e moderacao/aprovacao antes de criar acesso definitivo). Fecha o
  roadmap da Nova Onda. Ver `docs/tecnico/links-publicos.md`.

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
php artisan db:seed --force
php artisan optimize:clear
```

O scheduler já é importante para:

- `documents:notify-expiring`;
- `maintenance:notify-due`;
- `expenses:notify-due`;
- `occurrences:notify-sla`;
- `employees:notify-vacations`;
- rotinas financeiras existentes.

Confirmar no Easypanel se há worker/cron com `php artisan schedule:run` ou `php artisan schedule:work`.

## Próximo passo sugerido

O roadmap da **Nova Onda** (`06-roadmap-nova-onda.md`) está **concluído**: Fases A, B, C, D e o
booster X3 entregues. Não há mais itens abertos nesse roadmap.

Pendência operacional antes de testar X3 em produção (Easypanel):

```bash
php artisan migrate --force
php artisan db:seed --force   # se quiser refletir public_links em planos/papéis já existentes
php artisan optimize:clear
```

### Ciclo "Módulos operacionais" — E1 Encomendas · E2 Enquetes · E3 Achados & Perdidos (concluído)

Implementado em 11/06/2026. Doc técnica: `docs/tecnico/modulos-operacionais.md`.

- **E1 Encomendas:** montado no domínio gatehouse (porteiro registra em `/portaria/encomendas`, gestor
  acompanha em `/encomendas`, morador em `/portal/encomendas`); notificação `ParcelArrived`. Reusa o
  módulo `gatehouse` (sem módulo novo) porque o porteiro não é papel de painel.
- **E2 Enquetes:** módulo novo `polls` (1 voto por pessoa); `Poll/PollOption/PollVote` + `PollService`;
  painel `/enquetes`, portal `/portal/enquetes`; notificação `PollOpened`.
- **E3 Achados & Perdidos:** módulo novo `lost_found`; `LostFoundItem` (foto); painel
  `/achados-perdidos`, portal `/portal/achados-perdidos` (morador reporta).

Cada módulo novo (polls, lost_found) é registrado por migration (permissões + papéis + habilita em
todos os planos), no padrão do `register_public_links_*`. Validado: `php -l`, `route:list`,
`npm run build` verde. **Deploy:** `migrate --force` + `optimize:clear`.

### Ciclo "Módulos operacionais" — E4 Multas/advertências regimentais · E5 Mural/classificados (concluído)

Implementado em 11/06/2026. Doc técnica: `docs/tecnico/modulos-operacionais.md`.

- **E4 Multas/advertências regimentais:** módulo novo `disciplinary`; painel
  `/multas-advertencias`, portal `/portal/multas-advertencias`; registros por unidade/pessoa, tipos
  `warning|fine`, status `issued|acknowledged|cancelled`, anexos/evidências, ciência do morador e
  notificação `DisciplinaryRecordIssued`. Multa pode gerar cobrança vinculada quando o plano tem
  `financial` e o usuário possui `charges:create`.
- **E5 Mural/classificados:** módulo novo `community_board`; painel `/mural`, portal `/portal/mural`;
  publicações de mural pela gestão, classificados de moradores com moderação, status
  `pending|published|rejected|archived`, anexos/imagens, expiração opcional e notificação
  `CommunityPostApproved` ao autor aprovado.

Cada módulo novo é registrado por migration (permissões + papéis + habilita em todos os planos), no
padrão do `register_public_links_*`. Validado: `php -l`, `route:list`, `npm run build` verde.
**Deploy:** `migrate --force` + `optimize:clear`.

### Auth do webhook da Evolution (concluído)

Implementado em 10/06/2026. Fecha o gap de segurança: `POST /api/webhooks/evolution` era público
(só casava pela `instance`), permitindo injetar mensagens falsas na inbox de um tenant. Agora a rota
é `POST /api/webhooks/evolution/{secret?}` e o `EvolutionWebhookController` confere o segredo
(`hash_equals`) contra `EvolutionSetting::webhookSecret()` (gerado/persistido na 1ª vez). O segredo
vai na URL registrada via `EvolutionManager::registrationWebhookUrl()` (usada no
`WhatsappConnectionController` ao criar/recriar/parear). Super admin em `/admin/whatsapp` vê a URL
protegida e tem botão **Re-sincronizar webhooks**; também há o comando `whatsapp:resync-webhooks`.

**Deploy:** `migrate --force` (coluna `evolution_settings.webhook_secret`) + `optimize:clear`. Após
o deploy, rodar `php artisan whatsapp:resync-webhooks` (ou o botão no super admin) para reaplicar o
segredo nas instâncias já existentes — senão os eventos delas passam a ser rejeitados (403).

### Google Drive externo para mídia de WhatsApp (concluído)

Implementado em 10/06/2026. Doc técnica: `docs/tecnico/whatsapp-drive-externo.md`. Atende o backlog
da iniciativa WhatsApp ("plugar drive externo"). O tenant conecta a própria conta Google (OAuth,
escopo `drive.file`); a partir daí a mídia da **inbox** (`entity_type='wa_media'`) é gravada no Drive
dele, **sem contar na cota do plano**. Disparos e demais anexos seguem na plataforma. Decisões: só
mídia nova; arquivos são de responsabilidade do tenant (aviso na UI).

Pontos-chave: tabela `tenant_drive_settings` (refresh_token encriptado); `GoogleDriveService` (Http
puro, sem google/apiclient); roteamento no `StorageService::storeRaw` com **fallback** para o disco
da plataforma se o Drive falhar; serving por proxy autenticado no `InboxController::media`; callback
OAuth central (`/oauth/google-drive/callback`, bypass no `ResolveTenant`, tenant no `state` assinado);
tela `Settings/Storage.tsx` em `/configuracoes/armazenamento` (menu Administração → Armazenamento).

**Deploy:** `migrate --force` + `optimize:clear`; criar OAuth Client no Google Cloud Console
(redirect_uri central) e setar `GOOGLE_DRIVE_CLIENT_ID/SECRET/REDIRECT_URI`. Sem novo seed.

Validado: `php -l` (todos), `route:list` (3 settings.storage + callback), `npm run build` verde.

### Endurecimento do X3 (concluído)

Entregue no commit `b9fd94e1 "Endurecimento do X3"`, logo após o X3 base. Fecha os três itens que
antes estavam listados como sugestões:

- **Fotos nas ocorrências públicas:** `PublicIntakeController@occurrenceStore` aceita `photos`
  (máx. 3, jpg/png/webp, 5 MB), anexa à `PublicSubmission` (`ATTACHMENT_ENTITY = public_submission`)
  e reaponta os `StorageObject` para a Ocorrência na aprovação. Estouro de cota não derruba o envio.
- **Acompanhamento por protocolo:** rotas `GET/POST /p/{token}/status` (`public.intake.status` /
  `public.intake.status.check`) e tela `resources/js/Pages/Public/Status.tsx`. Consulta exige
  protocolo + telefone (confere os dígitos) e mostra tipo/status.
- **Anti-abuso:** honeypot (`company_site`), captcha Turnstile via `App\Services\CaptchaVerifier`,
  `throttle:10,1` nas rotas de POST, dedupe (mesmo tipo+telefone+condomínio em 10 min) e teto de
  `MAX_PENDING_PER_IP_DAY = 5` por IP/condomínio em 24h.

Com isso o roadmap da Nova Onda + booster X3 está integralmente fechado.

**Status do MVP:** completo. X3, WhatsApp (Fases 1–6) e **Portaria (6.6)** — todos construídos e no
master. Portaria confirmada em 10/06: `Portaria\PortariaController` (check-in autorizado/avulso,
validar QR), `VisitorAuthorization`/`VisitorVisit`, `/portaria` + autorização de visitantes no portal
do morador, telas em `Pages/Portaria`.

Sugestões de continuidade (a definir com o usuário):

- **Hardening do WhatsApp — CONCLUÍDO (10/06).** Auth do webhook ✅, Drive externo ✅ e **limpeza de
  mídia** ✅. Ver `docs/tecnico/whatsapp-drive-externo.md`.
- **Ciclo Módulos operacionais — CONCLUÍDO (11/06):** Encomendas ✅, Enquetes ✅, Achados & Perdidos ✅,
  Multas/advertências regimentais ✅ e Mural/classificados ✅.
- **Temas maiores mapeados (não iniciados):** App/PWA + push do morador; Conformidade & segurança
  (2FA + LGPD: exportação/portabilidade/anonimização); Financeiro avançado (régua de cobrança +
  prestação de contas/balancete).
- **Bug aberto:** logo do tenant não persiste nos relatórios PDF (*Configurações → Dados do tenant*).
- **Logo do tenant não persiste** — o upload em *Configurações → Dados do tenant* (ainda usado no
  cabeçalho dos relatórios PDF) não reflete após refresh; investigar a fundo. O header do painel já
  foi desacoplado disso (usa a logo fixa do SindÂncora + nome do tenant).
- **Novo ciclo de produto** a partir do estudo de concorrentes.

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
