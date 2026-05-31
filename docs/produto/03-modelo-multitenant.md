# 03 — Modelo Multitenant do SindÂncora

> Versão: 1.0 — 31/05/2026
> Estratégia: Shared Database, Shared Schema com tenant_id + Global Scopes + RLS

---

## 1. Estratégia de Multitenancy

### 1.1 Comparação de Estratégias

| Estratégia | Isolamento | Custo | Complexidade | Escolhida? |
|---|---|---|---|---|
| Database por tenant | Máximo | Alto (muitos bancos) | Alta (migrations multiplicadas) | Não |
| Schema por tenant | Alto | Médio | Média | Não |
| **Shared DB + tenant_id** | **Médio-alto** | **Baixo** | **Baixa-média** | **Sim** |

### 1.2 Decisão: Shared Database, Shared Schema

O SindÂncora usa um **banco de dados único** com todas as tabelas compartilhadas entre os tenants, identificadas pela coluna `tenant_id`. O isolamento é garantido por múltiplas camadas:

1. **Laravel Global Scopes** — filtro automático por `tenant_id` em todas as queries do Eloquent
2. **Middleware ResolveTenant** — injeta o tenant no contexto de cada request
3. **Laravel Policies** — verificação de ownership antes de qualquer operação
4. **PostgreSQL Row-Level Security (RLS)** — barreira adicional no nível do banco
5. **Testes automatizados** — testes de vazamento de dados entre tenants

**Por que não database-per-tenant?**
Em VPS com EasyPanel, múltiplos bancos aumentam drasticamente o custo operacional, a complexidade de backup, e as migrations. Para o volume inicial do SindÂncora (dezenas a centenas de tenants), shared schema é a decisão correta. Se necessário, migrar para schema-per-tenant no futuro é viável.

---

## 2. Resolução de Tenant

### 2.1 Fluxo de Resolução

```
HTTP Request (qualquer rota)
        │
        ▼
Middleware: ResolveTenant
        │
        ├─ 1. Extrair host da request
        │       ex: "clinica.sindancora.com.br" ou "app.administradora.com.br"
        │
        ├─ 2. Verificar cache Redis
        │       key: "tenant:domain:{host}"
        │       TTL: 5 minutos
        │
        ├─ 3. Se cache miss → buscar no banco
        │       SELECT * FROM tenant_domains WHERE domain = '{host}' AND active = true
        │       → buscar tenant via tenant_id
        │
        ├─ 4. Verificar tenant ativo
        │       Se tenant.status != 'active' → 503 (manutenção) ou 402 (suspenso)
        │
        ├─ 5. Injetar tenant no contexto da aplicação
        │       app()->instance('tenant', $tenant)
        │       app()->instance('tenant_id', $tenant->id)
        │
        └─ 6. Configurar Global Scopes
                TenantScope::setTenantId($tenant->id)
```

### 2.2 Estratégias de Resolução por Contexto

| Contexto | Estratégia | Exemplo |
|---|---|---|
| Painel web (admin, síndico) | Subdomínio do SindÂncora | `minhaadmin.sindancora.com.br` |
| Domínio próprio (white-label) | CNAME mapeado para o EasyPanel | `app.meusistema.com.br` |
| Portal do morador | Subdomínio `portal.` ou path `/morador` | `portal.minhaadmin.sindancora.com.br` |
| API externa via API Key | Header `X-Tenant-ID` ou JWT claim | `Authorization: Bearer {api_key}` |
| Super Admin | Domínio especial `admin.sindancora.com.br` | Sem tenant_id — acesso global |

### 2.3 Tabelas de Domínio

```sql
-- Tabela principal de tenants
CREATE TABLE tenants (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name        VARCHAR(255) NOT NULL,
    slug        VARCHAR(100) UNIQUE NOT NULL,  -- usado no subdomínio padrão
    document    VARCHAR(18),                   -- CNPJ
    status      VARCHAR(20) DEFAULT 'active',  -- active, suspended, cancelled, trial
    plan_id     UUID REFERENCES plans(id),
    settings    JSONB DEFAULT '{}',
    created_at  TIMESTAMPTZ DEFAULT NOW(),
    updated_at  TIMESTAMPTZ DEFAULT NOW(),
    deleted_at  TIMESTAMPTZ
);

-- Domínios e subdomínios por tenant
CREATE TABLE tenant_domains (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id),
    domain      VARCHAR(255) NOT NULL UNIQUE,   -- ex: minhaadmin.sindancora.com.br
    type        VARCHAR(20) DEFAULT 'subdomain', -- subdomain, custom
    active      BOOLEAN DEFAULT true,
    verified_at TIMESTAMPTZ,
    created_at  TIMESTAMPTZ DEFAULT NOW()
);
```

---

## 3. Isolamento de Dados no Eloquent

### 3.1 Trait BelongsToTenant

Todos os models operacionais usam a trait `BelongsToTenant`, que aplica automaticamente o Global Scope:

```php
// app/Traits/BelongsToTenant.php

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $tenantId = app('tenant_id');
            if ($tenantId) {
                $query->where(static::getTenantColumn(), $tenantId);
            }
        });

        static::creating(function (Model $model) {
            if (! $model->{static::getTenantColumn()}) {
                $model->{static::getTenantColumn()} = app('tenant_id');
            }
        });
    }

    protected static function getTenantColumn(): string
    {
        return 'tenant_id';
    }
}
```

### 3.2 Hierarquia de Isolamento

Nem todos os models têm `tenant_id` diretamente. A hierarquia define a herança do isolamento:

```
tenant_id direto:
  tenants, users, roles, persons, condominiums, audit_logs,
  storage_objects, api_keys, notifications, tenant_plan_subscriptions

tenant_id herdado via condominium_id:
  blocks, units, announcements, occurrences, occurrence_updates,
  common_areas, reservations, documents, charges, webhooks

tenant_id herdado via unit_id:
  unit_person_links, reservation (via common_area)

tenant_id herdado via user_id:
  user_roles, notifications

Tabelas globais (sem tenant_id):
  plans, plan_modules, permissions, role_permissions
```

### 3.3 Verificação de Ownership em Policies

Para models com herança (sem `tenant_id` direto), as Policies verificam o caminho até o tenant:

```php
// Exemplo: verificar se uma Unidade pertence ao tenant atual
class UnitPolicy
{
    public function view(User $user, Unit $unit): bool
    {
        return $unit->condominium->tenant_id === $user->tenant_id;
    }
}
```

---

## 4. Row-Level Security no PostgreSQL

O RLS é configurado como segunda camada de segurança — mesmo que o código tenha um bug e esqueça de filtrar por tenant, o banco rejeitará a query.

### 4.1 Configuração do RLS

```sql
-- Habilitar RLS na tabela de usuários
ALTER TABLE users ENABLE ROW LEVEL SECURITY;

-- Política: usuário só vê registros do próprio tenant
CREATE POLICY tenant_isolation ON users
    USING (tenant_id = current_setting('app.current_tenant_id')::uuid);

-- O mesmo para todas as tabelas com tenant_id direto
ALTER TABLE condominiums ENABLE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON condominiums
    USING (tenant_id = current_setting('app.current_tenant_id')::uuid);
```

### 4.2 Configurar o tenant_id na sessão PostgreSQL

No middleware do Laravel, após resolver o tenant:

```php
// app/Http/Middleware/ResolveTenant.php
DB::statement("SET app.current_tenant_id = '{$tenant->id}'");
```

### 4.3 Usuário do banco de dados

O Laravel se conecta ao PostgreSQL com um usuário sem privilégio de `BYPASSRLS`, garantindo que o RLS sempre seja aplicado.

---

## 5. Estrutura de Contexto do Tenant na Request

### 5.1 O que está disponível em qualquer lugar da aplicação

```php
// Acessar o tenant atual
$tenant = app('tenant');
$tenantId = app('tenant_id');

// Helpers globais
tenant()        // retorna o objeto Tenant atual
tenantId()      // retorna o UUID do tenant atual

// No Blade / Inertia
Inertia::share('tenant', fn() => TenantResource::make(app('tenant')));
```

### 5.2 Super Admin — Sem tenant_id

O Super Admin (`role: super_admin`) acessa o sistema pelo domínio `admin.sindancora.com.br`. Neste caso:

- O middleware `ResolveTenant` detecta o domínio especial e **não define** `tenant_id`
- Os Global Scopes são **desativados** (`withoutGlobalScope('tenant')`)
- O Super Admin pode filtrar por tenant manualmente
- Todas as ações do Super Admin são registradas no audit_log com `tenant_id = null`

---

## 6. Tabelas Completas com tenant_id

### 6.1 Mapa completo de tenant_id

| Tabela | tenant_id | Fonte do isolamento |
|---|---|---|
| `tenants` | É a própria raiz | — |
| `tenant_domains` | `tenant_id` direto | JOIN tenants |
| `tenant_settings` | `tenant_id` direto | — |
| `tenant_plan_subscriptions` | `tenant_id` direto | — |
| `tenant_limits` | `tenant_id` direto | — |
| `tenant_usage_counters` | `tenant_id` direto | — |
| `tenant_storage_addons` | `tenant_id` direto | — |
| `users` | `tenant_id` direto | — |
| `roles` | `tenant_id` direto (null = global) | is_system para roles globais |
| `user_roles` | Herdado via `user_id` | — |
| `persons` | `tenant_id` direto | — |
| `condominiums` | `tenant_id` direto | — |
| `blocks` | Herdado via `condominium_id` | — |
| `units` | Herdado via `condominium_id` | — |
| `unit_person_links` | Herdado via `unit_id` | — |
| `announcements` | `tenant_id` direto + `condominium_id` | Redundante para performance |
| `occurrences` | `tenant_id` direto + `condominium_id` | Redundante para performance |
| `occurrence_updates` | Herdado via `occurrence_id` | — |
| `common_areas` | Herdado via `condominium_id` | — |
| `reservations` | Herdado via `common_area_id` | — |
| `documents` | `tenant_id` direto + `condominium_id` | Redundante para performance |
| `storage_objects` | `tenant_id` direto | Necessário para cálculo de quota |
| `charges` | Herdado via `condominium_id` | — |
| `notifications` | Herdado via `user_id` | — |
| `audit_logs` | `tenant_id` direto | Obrigatório para isolamento de auditoria |
| `api_keys` | `tenant_id` direto | — |
| `api_request_logs` | Herdado via `api_key_id` | — |
| `webhooks` | `tenant_id` direto | — |
| `webhook_deliveries` | Herdado via `webhook_id` | — |

> **Nota sobre redundância:** Algumas tabelas como `announcements`, `occurrences` e `documents` têm `tenant_id` tanto direto quanto herdado via `condominium_id`. Isso é intencional para performance: evita JOINs em queries de listagem e permite índices compostos simples.

---

## 7. Índices Compostos Obrigatórios

Para performance em ambiente multitenant, todo campo consultado deve ter índice composto com `tenant_id`:

```sql
-- Usuários
CREATE INDEX idx_users_tenant_email ON users(tenant_id, email);
CREATE INDEX idx_users_tenant_status ON users(tenant_id, status);

-- Condomínios
CREATE INDEX idx_condominiums_tenant ON condominiums(tenant_id);
CREATE INDEX idx_condominiums_tenant_status ON condominiums(tenant_id, status);

-- Unidades
CREATE INDEX idx_units_condominium ON units(condominium_id);
CREATE INDEX idx_units_condominium_status ON units(condominium_id, status);

-- Ocorrências
CREATE INDEX idx_occurrences_tenant_status ON occurrences(tenant_id, status);
CREATE INDEX idx_occurrences_tenant_category ON occurrences(tenant_id, category);

-- Audit logs
CREATE INDEX idx_audit_logs_tenant_created ON audit_logs(tenant_id, created_at DESC);
CREATE INDEX idx_audit_logs_tenant_entity ON audit_logs(tenant_id, entity, entity_id);

-- Storage
CREATE INDEX idx_storage_tenant ON storage_objects(tenant_id);
```

---

## 8. Onboarding de Novo Tenant

### 8.1 Fluxo de Criação

```
Admin do SindÂncora (Super Admin) cria novo tenant
    OU
Formulário de auto-cadastro (futuro)
        │
        ▼
1. Criar registro em `tenants`
2. Criar registro em `tenant_domains` (slug.sindancora.com.br)
3. Atribuir plano (tenant_plan_subscriptions)
4. Criar limites iniciais (tenant_limits baseado no plano)
5. Criar contadores zerados (tenant_usage_counters)
6. Criar usuário admin do tenant
7. Criar roles padrão (admin, sindico, morador, etc.) via seed
8. Enviar e-mail de boas-vindas com link de acesso
9. Registrar no audit_log
```

### 8.2 Exclusão de Tenant

O SindÂncora implementa **soft delete** no tenant. Ao cancelar:

1. `tenants.status = 'cancelled'` e `deleted_at = NOW()`
2. `tenant_domains.active = false`
3. Dados permanecem no banco por 90 dias (período de recuperação)
4. Após 90 dias: anonimização de dados pessoais conforme LGPD
5. Arquivos no storage: removidos após período de retenção configurável
6. Exportação de dados disponível ao cliente até o momento da exclusão definitiva

---

## 9. White-Label por Tenant

Cada tenant pode ter identidade visual própria, armazenada em `tenant_settings`:

```json
{
  "brand": {
    "name": "MeuSistema Condominial",
    "logo_url": "https://r2.sindancora.com.br/{tenant_id}/brand/logo.png",
    "favicon_url": "https://r2.sindancora.com.br/{tenant_id}/brand/favicon.ico",
    "primary_color": "#1e40af",
    "secondary_color": "#16a34a"
  },
  "contact": {
    "support_email": "suporte@meusistema.com.br",
    "support_phone": "(11) 9999-9999",
    "website": "https://meusistema.com.br"
  },
  "features": {
    "powered_by_sindancora": true,
    "custom_domain": false
  }
}
```

O layout Inertia lê `tenant_settings` via `Inertia::share()` e aplica as cores e logo dinamicamente via CSS variables.

---

## 10. Checklist de Segurança Multitenant

Antes de fazer merge de qualquer feature:

- [ ] Todo model operacional usa a trait `BelongsToTenant`?
- [ ] Toda query manual inclui `->where('tenant_id', tenantId())`?
- [ ] Toda Policy verifica o tenant do usuário vs. o recurso?
- [ ] Nenhuma rota expõe IDs sem verificar o tenant?
- [ ] A rota de Super Admin está protegida por middleware separado?
- [ ] Audit logs registram o tenant_id corretamente?
- [ ] Storage paths incluem o tenant_id no caminho?
- [ ] Notificações só chegam para usuários do mesmo tenant?
- [ ] APIs públicas verificam o tenant via API Key antes de qualquer operação?

---

*Documento de arquitetura multitenant do SindÂncora. Revisão obrigatória antes de cada nova feature que envolva dados sensíveis.*
