# 01 — Arquitetura do SindÂncora

> Versão: 1.0 — 31/05/2026
> Status: Proposta aprovada para início de implementação

---

## 1. Decisão de Stack

### 1.1 Análise das Opções

#### Opção A — Laravel 12 (Recomendada)

| Critério | Avaliação |
|---|---|
| Familiaridade do desenvolvedor | Alta — já em uso em outros projetos |
| Ecossistema para SaaS multitenant | Excelente — stancl/tenancy ou manual com middleware |
| Autenticação | Laravel Sanctum (API + SPA) |
| Frontend integrado | Inertia.js + React — deploy em container único |
| Filas e agendamento | Laravel Horizon + Laravel Scheduler |
| ORM | Eloquent + PostgreSQL (pdo_pgsql) |
| Suporte a EasyPanel/Docker | Excelente — imagem PHP-FPM + Nginx |
| Maturidade para produção | Alta — comunidade ativa, LTS |
| Curva de aprendizado | Baixa — stack conhecida |

#### Opção B — NestJS + Next.js

| Critério | Avaliação |
|---|---|
| Familiaridade do desenvolvedor | Baixa |
| Ecossistema para SaaS | Bom, mas mais verboso |
| Frontend integrado | Não — requer 2 containers separados |
| Complexidade de deploy | Alta — Next.js + NestJS + workers |
| ORM | TypeORM ou Prisma — curva adicional |
| Suporte a EasyPanel | Médio — 2+ serviços separados |

### 1.2 Decisão Final: **Laravel 12 + Inertia + React**

**Justificativa:**

O usuário tem experiência comprovada com Laravel e VPS + EasyPanel. A combinação Laravel + Inertia.js + React permite construir um SPA completo e moderno mantendo um único container PHP no EasyPanel, o que simplifica drasticamente o deploy, as migrations, as filas e o agendamento. O ecossistema Laravel tem todas as ferramentas necessárias para SaaS multitenant de alta qualidade, com menos overhead operacional que NestJS + Next.js em ambiente VPS.

---

## 2. Stack Final

| Camada | Tecnologia | Versão | Papel |
|---|---|---|---|
| Backend | Laravel | 12.x | API REST + servidor Inertia |
| Frontend | React | 19.x | SPA via Inertia.js |
| Inertia.js | inertia-laravel + @inertiajs/react | ^2.x | Ponte server/client sem API REST separada para o painel |
| CSS | TailwindCSS | 4.x | Estilização utilitária |
| Componentes UI | shadcn/ui (adaptado para Inertia) | latest | Design system baseado em Radix UI |
| Banco de Dados | PostgreSQL | 16 | Banco principal + JSONB |
| Cache | Redis | 7 | Cache de sessão, rate limit, filas |
| Filas | Laravel Horizon | latest | Workers de e-mail, notificações, relatórios |
| Agendamento | Laravel Scheduler | nativo | Crons, snapshots de storage, alertas |
| Autenticação | Laravel Sanctum | nativo | API tokens + SPA sessions |
| Storage | Cloudflare R2 / MinIO | — | Documentos e arquivos dos tenants |
| E-mail | Resend (ou SMTP) | — | E-mails transacionais |
| Deploy | Docker + EasyPanel | — | Containerização e orquestração |
| Web Server | Nginx + PHP-FPM | 8.4 | Servidor de aplicação |
| Monitoramento | Sentry | — | Erros em produção |
| Documentação API | OpenAPI/Swagger (l5-swagger) | — | Documentação da API pública |

---

## 3. Arquitetura de Alto Nível

```
┌─────────────────────────────────────────────────────────────────┐
│                        INTERNET                                  │
└──────────────────────────┬──────────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────────┐
│                   Cloudflare (CDN + DNS)                         │
│  *.sindancora.com.br   |   dominio-proprio.com.br               │
└──────────────────────────┬──────────────────────────────────────┘
                           │
┌──────────────────────────▼──────────────────────────────────────┐
│                 EasyPanel (VPS própria)                          │
│                                                                  │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │              Nginx (reverse proxy)                       │    │
│  └──────────────┬──────────────────────────────────────────┘    │
│                 │                                                │
│  ┌──────────────▼──────────────────────────────────────────┐    │
│  │         Laravel App (PHP-FPM 8.4)                        │    │
│  │                                                          │    │
│  │  ┌─────────────┐  ┌──────────────┐  ┌───────────────┐  │    │
│  │  │  Middleware  │  │  Controllers │  │   Services    │  │    │
│  │  │  (Tenant     │  │  (API v1 +   │  │  (Business    │  │    │
│  │  │  Resolution) │  │  Inertia)    │  │   Logic)      │  │    │
│  │  └─────────────┘  └──────────────┘  └───────────────┘  │    │
│  │                                                          │    │
│  │  ┌─────────────┐  ┌──────────────┐  ┌───────────────┐  │    │
│  │  │  Eloquent   │  │   Policies   │  │  Jobs/Events  │  │    │
│  │  │  (Models)   │  │   (RBAC)     │  │  (Queues)     │  │    │
│  │  └─────────────┘  └──────────────┘  └───────────────┘  │    │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                  │
│  ┌───────────────┐  ┌────────────────┐  ┌─────────────────┐    │
│  │  PostgreSQL   │  │     Redis      │  │  Laravel Horizon │    │
│  │  (dados)      │  │  (cache+filas) │  │  (queue workers) │    │
│  └───────────────┘  └────────────────┘  └─────────────────┘    │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
                           │
               ┌───────────▼───────────┐
               │    Cloudflare R2      │
               │  (Storage de arquivos) │
               └───────────────────────┘
```

---

## 4. Estrutura de Diretórios do Projeto

```
sindancora/
├── app/
│   ├── Console/            # Commands e Scheduler
│   ├── Exceptions/         # Handlers de exceções
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/V1/     # Endpoints da API pública
│   │   │   └── Panel/      # Controllers Inertia (painel admin)
│   │   ├── Middleware/
│   │   │   ├── ResolveTenant.php
│   │   │   ├── CheckTenantPlan.php
│   │   │   └── CheckPermission.php
│   │   └── Requests/       # Form Requests com validação
│   ├── Jobs/               # Jobs assíncronos (e-mail, notificações)
│   ├── Models/             # Eloquent Models
│   ├── Policies/           # RBAC policies por model
│   ├── Services/           # Lógica de negócio (TenantService, StorageService...)
│   └── Traits/             # BelongsToTenant, HasAuditLog, etc.
├── database/
│   ├── migrations/         # Todas as migrations
│   └── seeders/            # Planos, roles, permissões padrão
├── resources/
│   ├── js/
│   │   ├── Components/     # Componentes React reutilizáveis
│   │   ├── Layouts/        # Layout do painel, layout do morador
│   │   ├── Pages/          # Páginas Inertia (um arquivo por rota)
│   │   │   ├── Auth/
│   │   │   ├── Dashboard/
│   │   │   ├── Condominiums/
│   │   │   ├── Units/
│   │   │   ├── Persons/
│   │   │   ├── Announcements/
│   │   │   ├── Occurrences/
│   │   │   ├── Reservations/
│   │   │   ├── Documents/
│   │   │   └── Settings/
│   │   └── hooks/          # Custom React hooks
│   └── views/
│       └── app.blade.php   # Entry point Inertia
├── routes/
│   ├── web.php             # Rotas Inertia (painel)
│   ├── api.php             # Rotas API v1
│   └── auth.php            # Rotas de autenticação
├── docs/                   # Documentação do projeto
├── docker/                 # Configurações Docker
│   ├── nginx/
│   ├── php/
│   └── supervisor/
├── Dockerfile
├── docker-compose.yml
└── .env.example
```

---

## 5. Camadas da Aplicação

### 5.1 Resolução de Tenant (Middleware)

```
Request HTTP
    │
    ▼
ResolveTenant Middleware
    │
    ├── Extrai subdomínio ou domínio customizado
    ├── Busca tenant no banco (com cache Redis)
    ├── Verifica se tenant está ativo
    ├── Injeta tenant no contexto da request (app()->instance('tenant', $tenant))
    └── Configura scopes globais dos Models (tenant_id)
```

### 5.2 RBAC com Laravel Policies

```
Request → Controller
    │
    ├── Gate::authorize('condominiums:create') → Policy::create($user, ...)
    │       │
    │       ├── Verifica se user tem a permissão no seu role
    │       ├── Verifica se user pertence ao mesmo tenant
    │       └── Verifica se o condomínio pertence ao tenant
    │
    └── Resposta 403 ou continua
```

### 5.3 Verificação de Limites de Plano

```
Antes de criar condomínio/unidade/usuário/upload:
    │
    ├── PlanLimitService::check($tenant, 'condominiums')
    │       │
    │       ├── Busca plano ativo do tenant
    │       ├── Busca limite configurado (condominiums_limit)
    │       ├── Busca uso atual (tenant_usage_counters)
    │       └── Se uso >= limite → lança PlanLimitException (402)
    │
    └── Continua com a operação
```

---

## 6. Banco de Dados — Estratégia

- **Banco único** com coluna `tenant_id` em todas as tabelas operacionais (shared database, shared schema).
- **Global scopes** no Eloquent para filtrar automaticamente por tenant em todas as queries.
- **Índices compostos** obrigatórios: `(tenant_id, campo_consultado)`.
- **UUIDs** (ou ULIDs) como chave primária em todas as tabelas (segurança e distribuição).
- **Soft delete** (`deleted_at`) onde fizer sentido semântico.
- **JSONB** para campos semi-estruturados (settings, address, features).
- **Row-Level Security (RLS) no PostgreSQL** como segunda camada de proteção (além do Eloquent scope).

---

## 7. Frontend — React via Inertia.js

### Por que Inertia e não Next.js separado?

- Container único no EasyPanel (sem gerenciar 2 serviços)
- Auth e sessão funcionam nativamente com Laravel (sem JWT adicional para o painel)
- Rotas definidas no PHP (sem duplicação de rotas no Next.js)
- Server-side data passing sem necessidade de API para cada componente de UI

### Design System

- **TailwindCSS 4** para estilos utilitários
- **shadcn/ui** (baseado em Radix UI) para componentes acessíveis: dialogs, dropdowns, tables, forms
- **Fonte própria:** Inter ou Geist (Google Fonts / open source) — nunca Gilroy (proprietária do concorrente)
- **Ícones:** Lucide Icons (open source)
- **Paleta de cores:** definida na identidade do SindÂncora (azul profissional + verde-âncora)

### Estrutura de Páginas

Cada página Inertia recebe dados do controller PHP via props e renderiza a UI em React. Não há fetch de dados no cliente para o painel administrativo — os dados chegam prontos no carregamento da página.

---

## 8. API REST v1 — Estrutura

A API REST em `/api/v1` é destinada a:
- Portal do morador (cliente SPA ou futuro app mobile)
- Integrações externas via API Key
- Futuramente: webhooks e automações via n8n

Autenticação da API:
- **Painel admin:** Laravel Sanctum (SPA cookie-based ou token)
- **API pública:** Bearer token (API key por tenant)

---

## 9. Storage — Arquivos dos Tenants

- **Desenvolvimento:** MinIO local (S3-compatible)
- **Produção:** Cloudflare R2 (S3-compatible, custo baixo por egress gratuito)
- **Paths:** `/{tenant_id}/{condominium_id}/{module}/{year}/{month}/{uuid}.ext`
- **Controle de quota:** antes de cada upload, verificar `StorageService::checkQuota($tenant, $fileSize)`
- **Visibilidade:** todo arquivo tem visibilidade (`private`, `tenant`, `condominium`, `public_to_residents`)
- URLs pré-assinadas (signed URLs) para acesso a arquivos privados, válidas por tempo limitado

---

## 10. Segurança

| Camada | Medida |
|---|---|
| Autenticação | Bcrypt/Argon2 para senhas; tokens Sanctum com expiração |
| Autorização | Laravel Policies (RBAC granular por tenant) |
| Isolamento de dados | Global scope por tenant_id em todos os models |
| Segunda camada | PostgreSQL RLS (Row-Level Security) |
| Rate limiting | Laravel Throttle Middleware por IP e por tenant |
| Brute force | Bloqueio após N tentativas falhas (Laravel/custom) |
| HTTPS | Obrigatório; HSTS configurado via Nginx |
| Upload | Validação de tipo MIME, tamanho e extensão antes de aceitar |
| Logs | Auditoria de todas as ações sensíveis; nunca logar senhas/tokens |
| LGPD | Soft delete com anonimização, exportação de dados, consentimento |
| Headers | X-Frame-Options, X-Content-Type-Options, CSP configurados |

---

## 11. Deploy — EasyPanel

O projeto será containerizado com Docker e implantado no EasyPanel. Cada serviço será um container separado:

| Serviço | Imagem | Porta Interna |
|---|---|---|
| `app` | PHP-FPM 8.4 + Nginx | 80 |
| `postgres` | postgres:16-alpine | 5432 |
| `redis` | redis:7-alpine | 6379 |
| `horizon` | PHP-FPM 8.4 (worker) | — |
| `scheduler` | PHP-FPM 8.4 (cron) | — |
| `minio` | minio/minio (dev only) | 9000 |

O EasyPanel gerencia os containers, SSL automático via Let's Encrypt, e variáveis de ambiente.

---

## 12. Ambientes

| Ambiente | Banco | Storage | E-mail | Observação |
|---|---|---|---|---|
| Desenvolvimento | PostgreSQL local | MinIO local | Mailpit (local) | docker-compose.yml |
| Staging | PostgreSQL (EasyPanel) | MinIO ou R2 | Resend (sandbox) | Ambiente de testes |
| Produção | PostgreSQL (EasyPanel) | Cloudflare R2 | Resend (produção) | EasyPanel |

---

*Documento de arquitetura interna do SindÂncora. Revisão prevista ao início de cada nova fase.*
