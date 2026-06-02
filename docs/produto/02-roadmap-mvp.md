# 02 — Roadmap e Fases de Desenvolvimento do SindÂncora

> Versão: 1.0 — 31/05/2026
> Metodologia: Desenvolvimento em fases incrementais com entregas funcionais ao fim de cada fase

---

## Visão Geral do Roadmap

```
Fase 1 — Base SaaS          ████████░░░░░░░░░░░░░░░░  [ 6 semanas ]
Fase 2 — Cadastros           ░░░░░░░░████████░░░░░░░░  [ 4 semanas ]
Fase 3 — Operação            ░░░░░░░░░░░░░░░░████████  [ 5 semanas ]
Fase 4 — Portal do Morador   ░░░░░░░░░░░░░░░░░░░░████  [ 4 semanas ]
Fase 5 — Financeiro          ░░░░░░░░░░░░░░░░░░░░░░░░  [ 6 semanas ]
Fase 6 — Integrações e IA    ░░░░░░░░░░░░░░░░░░░░░░░░  [ contínuo  ]
```

**Estimativa total até MVP completo (Fases 1-4):** 4-5 meses com dedicação integral
**Estimativa com Fase 5 (Financeiro):** 6-7 meses

---

## Fase 1 — Base SaaS

**Objetivo:** Ter um sistema funcional com autenticação, tenants, planos, usuários e RBAC. A Fase 1 é o alicerce de todo o produto — sem ela, nada mais funciona.

**Duração estimada:** 6 semanas

### Entregas desta fase

#### 1.1 Infraestrutura Base
- [ ] Estrutura do projeto Laravel 12 com Docker
- [ ] docker-compose.yml para desenvolvimento local (app, postgres, redis, minio, mailpit)
- [ ] Dockerfile de produção (PHP-FPM 8.4 + Nginx)
- [ ] `.env.example` com todas as variáveis necessárias
- [ ] Pipeline de migrations automatizadas
- [ ] Seeds de dados iniciais (planos, roles, permissões)
- [ ] Configuração do Laravel Horizon (filas)
- [ ] Configuração do Laravel Scheduler
- [ ] Healthcheck endpoint (`/health`)

#### 1.2 Multitenancy
- [ ] Tabela `tenants` com configurações e status
- [ ] Tabela `tenant_domains` para subdomínios e domínios customizados
- [ ] Middleware `ResolveTenant` — resolução por subdomínio
- [ ] Global Scope `BelongsToTenant` em todos os models operacionais
- [ ] Onboarding de novo tenant (formulário de cadastro do cliente)
- [ ] Tabela `tenant_settings` (logo, cores, white-label)
- [ ] Painel Super Admin para gerenciar tenants

#### 1.3 Planos e Limites
- [ ] Tabela `plans` com configurações de limite
- [ ] Tabela `plan_modules` (módulos habilitados por plano)
- [ ] Tabela `tenant_plan_subscriptions` (plano ativo por tenant)
- [ ] Tabela `tenant_limits` (limites configurados por tenant)
- [ ] Tabela `tenant_usage_counters` (uso atual por tenant)
- [ ] `PlanLimitService` — verificação antes de criar recursos
- [ ] Seed de 4 planos iniciais (Starter, Profissional, Business, Enterprise)
- [ ] Painel de limites no dashboard do tenant

#### 1.4 Autenticação e Segurança
- [ ] Login com e-mail e senha (Laravel Sanctum)
- [ ] Logout com revogação de sessão
- [ ] Recuperação de senha por e-mail (token seguro)
- [ ] Rate limit nas rotas de auth (proteção brute force)
- [ ] Middleware de verificação de tenant ativo
- [ ] Refresh de sessão automático

#### 1.5 Usuários e RBAC
- [ ] Tabela `users` com `tenant_id` obrigatório
- [ ] Tabela `roles` (sistema + customizáveis por tenant)
- [ ] Tabela `permissions` (module:action)
- [ ] Tabela `role_permissions`
- [ ] Tabela `user_roles` (com escopo por condomínio opcional)
- [ ] CRUD de usuários pelo admin do tenant
- [ ] Seed de roles padrão: super_admin, admin, sindico, subsindico, conselheiro, morador
- [ ] Seed de permissões granulares para cada módulo
- [ ] Laravel Policies para autorização
- [ ] Middleware `CheckPermission`
- [ ] Interface de gerenciamento de usuários e roles

#### 1.6 Storage — Controle de Quota
- [ ] Tabela `storage_objects` (metadados de cada arquivo)
- [ ] Tabela `storage_usage_snapshots` (snapshots periódicos de uso)
- [ ] Tabela `storage_quota_packages` (pacotes de armazenamento)
- [ ] Tabela `tenant_storage_addons` (pacotes contratados por tenant)
- [ ] `StorageService` — validação de quota antes de upload
- [ ] Dashboard de uso de armazenamento por tenant
- [ ] Configuração do filesystem (MinIO local / R2 produção)

#### 1.7 Auditoria
- [ ] Tabela `audit_logs` com tenant_id, user_id, action, entity, old/new values
- [ ] Trait `HasAuditLog` para registrar automaticamente
- [ ] Interface de auditoria no painel admin
- [ ] Filtros por entidade, usuário e período

#### 1.8 Dashboard Inicial
- [ ] Rota `/dashboard` com cards de KPIs por tenant
- [ ] Cards: condomínios ativos, total de unidades, total de usuários, uso de storage
- [ ] Layout principal com menu lateral e topbar
- [ ] Componentes base do design system (botões, inputs, tabelas, modais, toasts)

#### 1.9 API Base
- [ ] Estrutura de rotas em `/api/v1`
- [ ] Autenticação via Bearer token (Sanctum)
- [ ] Padrão de resposta JSON (success, error, pagination)
- [ ] Middleware de throttle para a API
- [ ] Configuração do OpenAPI/Swagger (l5-swagger)
- [ ] Endpoint `GET /api/v1/me`
- [ ] Endpoints de autenticação (login, logout, refresh, forgot, reset)

#### 1.10 Deploy e Documentação
- [ ] Documentação de deploy no EasyPanel (`docs/deploy/easypanel.md`)
- [ ] README.md com instruções de desenvolvimento local
- [ ] Documentação da API (`docs/api/`)
- [ ] Configuração de variáveis de ambiente em produção

### Critérios de Aceite da Fase 1

Fase 1 considera-se **concluída** quando:

- [ ] Um Super Admin consegue criar um novo tenant pelo painel
- [ ] O tenant tem um subdomínio funcional
- [ ] Um usuário admin do tenant consegue fazer login
- [ ] Roles e permissões funcionam (admin não consegue fazer o que não tem permissão)
- [ ] Um usuário de tenant A não consegue ver dados do tenant B
- [ ] O tenant tem um plano com limites configurados
- [ ] O sistema rejeita operações que excedem os limites do plano
- [ ] Upload de arquivo registra uso de storage e bloqueia ao atingir quota
- [ ] Todas as ações sensíveis aparecem no log de auditoria
- [ ] A aplicação roda via Docker com um único `docker-compose up`
- [ ] Deploy no EasyPanel documentado e funcional

---

## Fase 2 — Cadastros Condominiais

**Objetivo:** Cadastro completo da estrutura física e de pessoas do condomínio.

**Duração estimada:** 4 semanas
**Dependência:** Fase 1 concluída

### Entregas

#### 2.1 Condomínios
- [ ] CRUD de condomínios com dados completos (nome, CNPJ, endereço com CEP autocomplete)
- [ ] Wizard step-by-step para criação (dados básicos → endereço → configurações)
- [ ] Configurações por condomínio (JSONB com configurações específicas)
- [ ] Verificação de limite de condomínios do plano
- [ ] Soft delete de condomínio

#### 2.2 Blocos e Torres
- [ ] CRUD de blocos/torres vinculados ao condomínio
- [ ] Número de andares por bloco
- [ ] Listagem de unidades por bloco

#### 2.3 Unidades
- [ ] CRUD de unidades (número, bloco, andar, tipo, área, fração ideal)
- [ ] Status da unidade: Ocupada / Vazia / Em Obras
- [ ] Verificação de limite de unidades do plano
- [ ] Importação de unidades via CSV (com validação e preview antes de confirmar)
- [ ] Copiar configuração de outra unidade
- [ ] Listagem com busca, filtros por bloco/status/tipo

#### 2.4 Pessoas e Vínculos
- [ ] CRUD de pessoas (nome, CPF único no tenant, e-mail, telefone, data de nascimento, endereço)
- [ ] Busca por CPF com prevenção de duplicidade
- [ ] Vínculo pessoa ↔ unidade com tipo (Proprietário, Locatário, Morador, Dependente)
- [ ] Data de início e fim do vínculo
- [ ] Histórico de moradores por unidade (vínculos passados)
- [ ] Morador principal (is_primary)
- [ ] Múltiplas unidades por pessoa (proprietário com vários imóveis)
- [x] Convite por e-mail para ativar acesso ao portal do morador — entregue na Fase 4 (`InvitationService`, botão na ficha da Pessoa).
- [ ] Importação de pessoas via CSV

#### 2.5 Síndicos e Conselheiros
- [x] Marcação de pessoa como Síndico, Subsíndico ou Conselheiro do condomínio
- [x] Mandato com data de início e fim
- [x] Role automático ao marcar como síndico — entregue na Fase 4: ao convidar, o `InvitationService` deriva os papéis de gestão de `CondominiumManager` (síndico/subsíndico/conselheiro) além do `morador`.

### Critérios de Aceite da Fase 2

- [ ] Admin cria condomínio com wizard completo
- [ ] Admin cria blocos e unidades manualmente
- [ ] Admin importa planilha de unidades sem erro
- [ ] Admin cadastra pessoa e vincula à unidade com tipo definido
- [ ] Sistema impede CPF duplicado no tenant
- [ ] Histórico de moradores acessível por unidade
- [ ] Convidado recebe e-mail com link de ativação do portal

---

## Fase 3 — Operação Condominial

**Objetivo:** Módulos operacionais do dia a dia do condomínio.

**Duração estimada:** 5 semanas
**Dependência:** Fase 2 concluída

### Entregas

#### 3.1 Comunicados
- [x] CRUD de comunicados (título, corpo rico via TipTap, categoria, nível de urgência)
- [ ] Segmentação de público: todos, bloco, unidade, perfil — [→ Fase 4] depende de moradores como usuários (portal)
- [x] Publicação imediata ou agendada (comando `announcements:publish-scheduled` no scheduler)
- [ ] Anexos (upload de arquivos via storage) — adiado para junto do módulo Documentos (3.4)
- [x] Expiração automática por data (scope `visible()` filtra; status "Expirado" na UI)
- [ ] Registro de leituras por morador (confirmação de leitura) — [→ Fase 4] depende do portal do morador
- [x] Notificação por e-mail ao publicar comunicado (Mailable enfileirado aos moradores com e-mail do condomínio)
- [ ] Templates de comunicados (reutilizáveis) — adiado

#### 3.2 Ocorrências / Chamados
- [x] CRUD de ocorrências (título, categoria, descrição, prioridade) — anexos adiados (junto do módulo Documentos 3.4)
- [ ] Categorias configuráveis por condomínio — usando lista fixa (`Occurrence::CATEGORIES`) por enquanto
- [x] Ciclo de vida: Aberta → Em Andamento → Encerrada (encerrar exige `occurrences:close`)
- [x] Histórico de atualizações com comentários (tabela `occurrence_comments`, timeline)
- [x] Atribuição a responsável (via serviço, com histórico e notificação)
- [x] Notificações a cada mudança de status (in-app, `OccurrenceUpdated`)
- [ ] Morador só vê suas próprias ocorrências (no portal) — [→ Fase 4]
- [x] Admin/síndico vê todas as ocorrências do condomínio
- [ ] SLA opcional por categoria (prazo esperado de resolução) — adiado
- [x] Filtros: status, categoria, prioridade, condomínio (filtros por unidade/data adiados)

#### 3.3 Áreas Comuns e Reservas
- [x] CRUD de áreas comuns (nome, descrição, capacidade, regras) — fotos adiadas (reusar StorageService)
- [x] Configuração: requer aprovação, taxa, caução, antecedência mínima, horários
- [x] Calendário visual de disponibilidade (mensal) — visão semanal adiada
- [x] Solicitação de reserva com preenchimento de horário — pelo painel; pelo morador na Fase 4 (portal)
- [x] Fluxo de aprovação: Pendente → Aprovada / Recusada
- [x] Cancelamento com motivo
- [x] Prevenção de conflito de horários (lock no banco — `lockForUpdate` em transação)
- [x] Notificações: solicitação, aprovação, recusa, cancelamento (in-app)
- [ ] Regras automáticas: bloqueio de inadimplente (futura integração financeiro)

#### 3.4 Documentos
- [x] Upload de documentos (PDF, imagens, Word, Excel) — via `StorageService`
- [x] Categorias: Ata, Regulamento, Contrato, Comprovante, Outro
- [x] Visibilidade: público (moradores) ou restrito (admin/síndico) — armazenada; enforcement do morador na Fase 4
- [x] Busca por nome, categoria, condomínio (filtro por data adiado)
- [x] Controle de storage por arquivo (tamanho, path, hash sha256) — `StorageObject` + cota por plano
- [x] Download com URL assinada e expiração (R2/S3/MinIO; streaming como fallback local)
- [x] Soft delete com lixeira temporária (30 dias antes de remover do storage)

#### 3.5 Notificações
- [x] Tabela de notificações in-app por usuário (tabela `notifications` do Laravel, canal database)
- [x] Sino no topbar com contador de não lidas (dropdown com as recentes)
- [x] Painel de notificações com listagem e marcação de leitura (`/notificacoes`)
- [x] Envio de e-mail via fila (Laravel Horizon) — `AnnouncementPublishedMail implements ShouldQueue`
- [ ] Templates de e-mail por tipo de evento — só o template de comunicado por enquanto
- [ ] Configuração por usuário: quais notificações receber por e-mail — adiado

### Critérios de Aceite da Fase 3

- [x] Admin publica comunicado e moradores recebem e-mail
- [x] Ocorrência aberta com acompanhamento (histórico/timeline e atualizações)
- [x] Área comum configurada com disponibilidade no calendário
- [x] Reserva criada e síndico aprova/recusa com notificação
- [x] Documento uploadado com visibilidade definida e download funcional
- [x] Notificações in-app e por e-mail funcionando nos eventos principais

> Itens dependentes do **Portal do Morador** (morador abrir/acompanhar as próprias ocorrências e
> fazer reservas diretamente) foram movidos para a **Fase 4**. No painel (Fases 3) o fluxo é operado
> por admin/síndico. Anexos de Comunicados/Ocorrências e fotos de áreas seguem adiados (reusar `StorageService`).

---

## Fase 4 — Portal do Morador

**Objetivo:** Interface dedicada ao morador — autônoma e com UX impecável.

**Duração estimada:** 4 semanas
**Dependência:** Fase 3 concluída

### Entregas

#### 4.1 Autenticação do Morador
- [~] Login no mesmo domínio com roteamento por papel — decisão: **mesmo domínio + `/portal`** em vez de subdomínio separado (sem wildcard DNS/cert; deploy de container único). `User::canAccessPanel()` separa gestor de morador.
- [x] Cadastro via link de convite enviado pelo admin (a partir da ficha da Pessoa; `InvitationService` + `ResidentInvitationMail`)
- [x] Recuperação de senha — reutiliza o fluxo do Laravel (broker de senha), escopado por tenant
- [~] Sessão única com roteamento por papel — não isolada por subdomínio; gate `panel`/`resident` separa as áreas

#### 4.2 Dashboard do Morador
- [x] Boas-vindas com dados da unidade
- [x] Resumo de comunicados não lidos
- [x] Reservas pendentes/aprovadas
- [x] Ocorrências em aberto
- [x] Documentos disponíveis (contagem)

#### 4.3 Módulos do Portal
- [x] Comunicados: listagem com leitura completa e confirmação (tabela `announcement_reads`)
- [x] Ocorrências: abrir nova + acompanhar histórico das próprias (notifica gestores na abertura)
- [x] Reservas: visualizar ocupação do mês + fazer reserva + cancelar própria (reusa `ReservationService`)
- [x] Documentos: listar e baixar documentos públicos (`visibility = residents`)
- [x] Dados da unidade: ver informações da unidade e histórico de vínculos
- [x] Meu perfil: atualizar dados de contato e senha

#### 4.4 UX do Portal
- [x] Layout responsivo (mobile-first) — `PortalLayout` (sidebar no desktop, tab bar no mobile)
- [x] Modo claro com identidade visual do tenant (white-label) — logo/cor compartilhados via Inertia
- [x] Notificações in-app — sino reusado; `Portal/Notifications`
- [→ adiado] PWA básico (instalável no celular)

### Critérios de Aceite da Fase 4

- [x] Morador ativa conta via e-mail de convite
- [x] Morador acessa o portal em dispositivo móvel sem dificuldades
- [x] Morador consegue fazer reserva completa sem ajuda do admin
- [x] Morador consegue abrir e acompanhar ocorrência
- [x] Morador não consegue ver dados de outros moradores (escopo por `person_id`/vínculos ativos)

---

## Fase 5 — Financeiro

**Objetivo:** Controle financeiro do condomínio, cobranças e inadimplência.

**Duração estimada:** 6 semanas
**Dependência:** Fase 4 concluída

### Entregas

#### 5.1 Cobranças Manuais
- [x] CRUD de cobranças por unidade (taxa condominial, extra, multa)
- [x] Definição de mês de referência, vencimento e valor
- [x] Registro manual de pagamento (com data, forma e comprovante via StorageService)
- [x] Multa e juros configuráveis por cobrança (cálculo de valor atualizado em `Charge::currentAmount()`)
- [x] Histórico de cobranças por unidade
- [x] Geração em lote por condomínio (valor base + ajuste por unidade na pré-visualização)

#### 5.2 Inadimplência
- [x] Listagem de unidades inadimplentes (na tela de Relatórios)
- [x] Relatório de inadimplência por período
- [x] Notificação automática ao morador sobre cobranças vencidas (comando `charges:mark-overdue` + `ChargeOverdue` in-app/e-mail)
- [ ] Bloqueio de inadimplente em reservas — [→ adiado] (inadimplência informativa por ora)

#### 5.3 Relatórios Financeiros
- [x] Relatório de receitas e despesas por período (inclui CRUD de Despesas)
- [x] Prestação de contas mensal (saldo = recebido − despesas; quebra mensal)
- [x] Exportação para PDF (dompdf) e XLSX (maatwebsite/excel)

#### 5.4 Integração com Gateway (Asaas) — [x] entregue
- [x] Configuração de conta Asaas por tenant (`tenant_payment_settings`, tela `/configuracoes/pagamentos`)
- [x] Geração de boleto bancário por cobrança (linha digitável + PDF)
- [x] Geração de QR Code PIX por cobrança (QR + copia-e-cola; fatura única `billingType: UNDEFINED`)
- [x] Webhook de retorno de pagamento (conciliação automática, `POST /api/webhooks/asaas`)
- [x] Envio de segunda via por e-mail (`ChargeIssuedMail`, painel e portal)

> **Decisão:** o financeiro **manual** (5.1–5.3) foi entregue primeiro; a integração Asaas (5.4) veio
> em fatia dedicada. Detalhes técnicos em `docs/tecnico/financeiro-asaas.md`.

### Critérios de Aceite da Fase 5

- [x] Admin gera cobrança mensal para todas as unidades (geração em lote)
- [x] Morador recebe boleto/PIX por e-mail (5.4 — `ChargeIssuedMail`, 2ª via no painel e no portal)
- [x] Pagamento confirmado automaticamente via webhook (5.4 — `POST /api/webhooks/asaas`); registro manual segue como fallback
- [x] Relatório de inadimplência correto e exportável (PDF/XLSX)

---

## Fase 6 — Integrações e IA (Contínuo)

**Objetivo:** Diferenciais de mercado — automações, WhatsApp, IA e API pública.

**Dependência:** Fase 5 concluída

### Entregas

#### 6.1 API Pública com API Keys
- [ ] Tabela `api_keys` por tenant com escopos
- [ ] Middleware de autenticação por API Key
- [ ] Rate limit por tenant e por key
- [ ] Logs de requisições (`api_request_logs`)
- [ ] Documentação Swagger atualizada

#### 6.2 Webhooks
- [ ] CRUD de webhooks por tenant
- [ ] Envio de payload a cada evento configurado
- [ ] Tabela `webhook_deliveries` com logs de envio e retry

#### 6.3 WhatsApp (Evolution API)
- [ ] Configuração de instância WhatsApp por tenant
- [ ] Notificações via WhatsApp: comunicado, vencimento, ocorrência
- [ ] Segunda via de boleto/PIX via WhatsApp

#### 6.4 IA Assistente (Claude API)
- [ ] Chat IA para síndico (Anthropic Claude API)
- [ ] RAG com documentos do condomínio (busca semântica)
- [ ] Análise de inadimplência com sugestões automáticas

#### 6.5 Assembleias Digitais
- [ ] Criação de assembleia com pauta
- [ ] Votação online por unidade (um voto por unidade)
- [ ] Ata gerada automaticamente
- [ ] Registro de presença digital

#### 6.6 Portaria Digital
- [ ] Controle de visitantes com QR Code
- [ ] Cadastro de visitantes autorizados por unidade
- [ ] Perfil porteiro com acesso restrito
- [ ] Log de entradas e saídas

---

## Marcos de Lançamento

| Marco | Fase | Quando | O que entrega |
|---|---|---|---|
| **Alpha interno** | Fase 1 | Semana 6 | Sistema funcional para testes internos |
| **Beta fechado** | Fase 2+3 | Semana 15 | Primeiros clientes pilotos |
| **MVP público** | Fase 4 | Semana 19 | Lançamento com portal do morador |
| **Produto completo** | Fase 5 | Semana 25 | Financeiro integrado |
| **Produto premium** | Fase 6 | Contínuo | Diferenciais IA e WhatsApp |

---

## Prioridades no Desenvolvimento

### Regras gerais de priorização

1. **Nunca quebrar o que já funciona** — toda mudança passa por testes antes de ir para produção
2. **Segurança e isolamento de tenant primeiro** — qualquer funcionalidade que comprometesse o isolamento é bloqueante
3. **UX antes de quantidade de features** — menos funcionalidades feitas com excelência é melhor que muitas mal-feitas
4. **Documentação acompanha implementação** — toda feature implementada tem documentação atualizada

### O que NÃO entra no MVP

- Portaria digital (Fase 6)
- Assembleias online (Fase 6)
- WhatsApp (Fase 6)
- IA (Fase 6)
- App mobile nativo (futuro)
- Integração CNAB bancária (somente gateway na Fase 5)

---

*Documento de roadmap interno. Atualizar ao início de cada nova fase.*
