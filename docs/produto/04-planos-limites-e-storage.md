# 04 — Planos, Limites e Controle de Storage

> Versão: 1.1 — 07/06/2026
> Finalidade: Definir os modelos comerciais, limites técnicos por plano e a estratégia de controle de armazenamento por tenant

---

## 1. Planos de Assinatura

### 1.1 Planos Iniciais

| Plano | Público-alvo | Preço/mês | Status |
|---|---|---|---|
| **Starter** | Síndico morador, condomínio pequeno | R$ 149 | Disponível no MVP |
| **Profissional** | Síndico profissional, 2-3 condomínios | R$ 349 | Disponível no MVP |
| **Business** | Administradora pequena | R$ 749 | Disponível no MVP |
| **Enterprise** | Administradora grande, white-label real | Sob consulta | Após MVP |

### 1.2 Limites por Plano

| Recurso | Starter | Profissional | Business | Enterprise |
|---|---|---|---|---|
| Condomínios | 1 | 3 | 15 | Ilimitado |
| Unidades (total) | 100 | 500 | 3.000 | Ilimitado |
| Usuários internos | 5 | 15 | 60 | Ilimitado |
| Moradores com acesso ao portal | 100 | 500 | 3.000 | Ilimitado |
| Armazenamento | 5 GB | 20 GB | 100 GB | Configurável |
| Comunicados/mês | 50 | 200 | Ilimitado | Ilimitado |
| Notificações por e-mail/mês | 500 | 2.000 | Ilimitado | Ilimitado |
| Chamadas de API/mês | 1.000 | 10.000 | 100.000 | Configurável |
| Webhooks | Não | 5 | 20 | Ilimitado |
| White-label (logo + cores) | Não | Sim | Sim | Sim |
| Domínio próprio | Não | Não | Sim | Sim |
| Suporte prioritário | Não | Não | Sim | Sim dedicado |

### 1.3 Módulos por Plano

| Módulo | Starter | Profissional | Business | Enterprise |
|---|---|---|---|---|
| Autenticação + Tenants | ✅ | ✅ | ✅ | ✅ |
| Condomínios + Unidades + Pessoas | ✅ | ✅ | ✅ | ✅ |
| Comunicados | ✅ | ✅ | ✅ | ✅ |
| Ocorrências | ✅ | ✅ | ✅ | ✅ |
| Reservas de áreas comuns | ✅ | ✅ | ✅ | ✅ |
| Documentos | ✅ | ✅ | ✅ | ✅ |
| Portal do morador (web) | ✅ | ✅ | ✅ | ✅ |
| Notificações in-app + e-mail | ✅ | ✅ | ✅ | ✅ |
| Relatórios básicos | ✅ | ✅ | ✅ | ✅ |
| Financeiro — cobranças manuais | ❌ | ✅ | ✅ | ✅ |
| Financeiro — boleto/PIX (gateway) | ❌ | ❌ | ✅ | ✅ |
| Orçamentos/Cotações multi-fornecedor | ❌ | ✅ | ✅ | ✅ |
| Importação CSV de unidades/pessoas | ❌ | ✅ | ✅ | ✅ |
| Exportação PDF/XLSX | ❌ | ✅ | ✅ | ✅ |
| API pública com API Key | ❌ | ❌ | ✅ | ✅ |
| Webhooks | ❌ | ❌ | ✅ | ✅ |
| WhatsApp (add-on) | ❌ | ❌ | ✅ | ✅ |
| IA assistente (add-on) | ❌ | ❌ | ❌ | ✅ |
| Assembleias digitais | ❌ | ❌ | ❌ | ✅ |
| Portaria digital | ❌ | ❌ | ❌ | ✅ |
| White-label completo | ❌ | Parcial | Parcial | ✅ |

---

## 2. Modelo de Dados de Planos e Limites

### 2.1 Tabelas

```sql
-- Planos disponíveis
CREATE TABLE plans (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name             VARCHAR(100) NOT NULL,            -- 'starter', 'profissional'
    display_name     VARCHAR(100) NOT NULL,            -- 'Plano Starter'
    description      TEXT,
    price_monthly    DECIMAL(10,2),                   -- null = sob consulta
    price_yearly     DECIMAL(10,2),
    is_active        BOOLEAN DEFAULT true,
    is_public        BOOLEAN DEFAULT true,             -- false = Enterprise (oculto)
    sort_order       SMALLINT DEFAULT 0,
    created_at       TIMESTAMPTZ DEFAULT NOW(),
    updated_at       TIMESTAMPTZ DEFAULT NOW()
);

-- Limites de cada plano
CREATE TABLE plan_limits (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    plan_id          UUID NOT NULL REFERENCES plans(id),
    resource         VARCHAR(100) NOT NULL,            -- 'condominiums', 'units', 'storage_mb'
    limit_value      BIGINT NOT NULL,                  -- -1 = ilimitado
    created_at       TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(plan_id, resource)
);

-- Módulos habilitados por plano
CREATE TABLE plan_modules (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    plan_id          UUID NOT NULL REFERENCES plans(id),
    module           VARCHAR(100) NOT NULL,            -- 'financial', 'api', 'webhooks'
    enabled          BOOLEAN DEFAULT true,
    created_at       TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(plan_id, module)
);

-- Assinatura ativa de cada tenant
CREATE TABLE tenant_plan_subscriptions (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id        UUID NOT NULL REFERENCES tenants(id),
    plan_id          UUID NOT NULL REFERENCES plans(id),
    status           VARCHAR(20) DEFAULT 'active',    -- active, cancelled, expired, trial
    trial_ends_at    TIMESTAMPTZ,
    starts_at        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    ends_at          TIMESTAMPTZ,                     -- null = recorrente
    cancelled_at     TIMESTAMPTZ,
    created_at       TIMESTAMPTZ DEFAULT NOW(),
    updated_at       TIMESTAMPTZ DEFAULT NOW()
);

-- Limites customizados por tenant (override do plano)
CREATE TABLE tenant_limits (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id        UUID NOT NULL REFERENCES tenants(id),
    resource         VARCHAR(100) NOT NULL,
    limit_value      BIGINT NOT NULL,
    reason           TEXT,                            -- ex: "negociação comercial"
    set_by           UUID REFERENCES users(id),      -- super admin que configurou
    created_at       TIMESTAMPTZ DEFAULT NOW(),
    updated_at       TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(tenant_id, resource)
);

-- Contadores de uso atual por tenant
CREATE TABLE tenant_usage_counters (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id        UUID NOT NULL REFERENCES tenants(id),
    resource         VARCHAR(100) NOT NULL,
    current_value    BIGINT DEFAULT 0,
    reset_at         TIMESTAMPTZ,                    -- para contadores mensais
    updated_at       TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(tenant_id, resource)
);
```

### 2.2 Recursos Rastreados

| Chave (`resource`) | Tipo | Descrição |
|---|---|---|
| `condominiums` | Permanente | Total de condomínios ativos |
| `units` | Permanente | Total de unidades ativas |
| `users` | Permanente | Total de usuários internos ativos |
| `residents` | Permanente | Total de moradores com acesso ao portal |
| `storage_mb` | Permanente | Armazenamento total usado em MB |
| `announcements_monthly` | Mensal (reset) | Comunicados enviados no mês |
| `emails_monthly` | Mensal (reset) | E-mails enviados no mês |
| `api_calls_monthly` | Mensal (reset) | Chamadas de API no mês |

---

## 3. Serviço de Verificação de Limites

### 3.1 PlanLimitService

Implementação atual: para recursos permanentes (`condominiums`, `units`, `users`, `residents` e
`storage_mb`), o serviço calcula o uso pela base real e sincroniza `tenant_usage_counters`. O contador
continua útil para dashboard/histórico, mas não é a única fonte de verdade. Isso evita que downgrade
de plano permita criar acima do limite por contador desatualizado.

```php
// app/Services/PlanLimitService.php

class PlanLimitService
{
    public function check(Tenant $tenant, string $resource, int $increment = 1): void
    {
        $limit = $this->getLimit($tenant, $resource);

        if ($limit === -1) {
            return; // ilimitado
        }

        $current = $this->getCurrent($tenant, $resource);

        if (($current + $increment) > $limit) {
            throw new PlanLimitException(
                resource: $resource,
                current: $current,
                limit: $limit,
                plan: $tenant->activePlan->display_name
            );
        }
    }

    public function increment(Tenant $tenant, string $resource, int $by = 1): void
    {
        TenantUsageCounter::where('tenant_id', $tenant->id)
            ->where('resource', $resource)
            ->increment('current_value', $by);
    }

    public function decrement(Tenant $tenant, string $resource, int $by = 1): void
    {
        TenantUsageCounter::where('tenant_id', $tenant->id)
            ->where('resource', $resource)
            ->decrement('current_value', $by);
    }

    private function getLimit(Tenant $tenant, string $resource): int
    {
        // 1. Verificar override específico do tenant
        $override = TenantLimit::where('tenant_id', $tenant->id)
            ->where('resource', $resource)->first();

        if ($override) {
            return $override->limit_value;
        }

        // 2. Buscar no plano ativo
        $planLimit = PlanLimit::where('plan_id', $tenant->activePlan->id)
            ->where('resource', $resource)->first();

        return $planLimit?->limit_value ?? -1;
    }
}
```

### 3.2 Exceção de Limite

Quando o limite é atingido, o sistema lança uma `PlanLimitException` que resulta em:

- **HTTP 402 Payment Required** na API
- **Mensagem amigável** no painel com CTA para upgrade
- **Registro no audit_log** para monitoramento

---

## 4. Controle de Armazenamento (Storage)

### 4.1 Tabelas de Storage

```sql
-- Registro de cada arquivo no sistema
CREATE TABLE storage_objects (
    id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id         UUID NOT NULL REFERENCES tenants(id),
    condominium_id    UUID REFERENCES condominiums(id),
    entity_type       VARCHAR(100),                   -- 'document', 'announcement', 'occurrence'
    entity_id         UUID,
    storage_provider  VARCHAR(50) DEFAULT 'r2',       -- 'r2', 'minio', 's3'
    storage_bucket    VARCHAR(255),
    storage_path      VARCHAR(1000) NOT NULL,          -- path completo no bucket
    original_filename VARCHAR(500),
    mime_type         VARCHAR(100),
    file_size_bytes   BIGINT NOT NULL,
    checksum_sha256   VARCHAR(64),
    visibility        VARCHAR(30) DEFAULT 'tenant',   -- private, tenant, condominium, public_to_residents
    uploaded_by       UUID REFERENCES users(id),
    deleted_at        TIMESTAMPTZ,                    -- soft delete para lixeira
    permanent_delete_at TIMESTAMPTZ,                 -- quando remover do storage de fato
    created_at        TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_storage_tenant ON storage_objects(tenant_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_storage_tenant_entity ON storage_objects(tenant_id, entity_type, entity_id);

-- Snapshots periódicos de uso (para histórico e billing)
CREATE TABLE storage_usage_snapshots (
    id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id         UUID NOT NULL REFERENCES tenants(id),
    total_bytes       BIGINT NOT NULL,
    total_files       INTEGER NOT NULL,
    snapshot_at       TIMESTAMPTZ DEFAULT NOW()
);

-- Pacotes adicionais de storage disponíveis para compra
CREATE TABLE storage_quota_packages (
    id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name              VARCHAR(100) NOT NULL,          -- 'Pacote +10 GB'
    size_gb           INTEGER NOT NULL,               -- 10, 50, 100
    price_monthly     DECIMAL(10,2) NOT NULL,
    is_active         BOOLEAN DEFAULT true,
    created_at        TIMESTAMPTZ DEFAULT NOW()
);

-- Pacotes adicionais contratados por tenant
CREATE TABLE tenant_storage_addons (
    id                UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id         UUID NOT NULL REFERENCES tenants(id),
    package_id        UUID REFERENCES storage_quota_packages(id),
    size_gb           INTEGER NOT NULL,               -- cópia para histórico
    price_paid        DECIMAL(10,2),
    starts_at         TIMESTAMPTZ DEFAULT NOW(),
    ends_at           TIMESTAMPTZ,
    active            BOOLEAN DEFAULT true,
    added_by          UUID REFERENCES users(id),      -- super admin ou automação
    created_at        TIMESTAMPTZ DEFAULT NOW()
);
```

### 4.2 StorageService — Validação de Quota

```php
// app/Services/StorageService.php

class StorageService
{
    public function checkQuota(Tenant $tenant, int $fileSizeBytes): void
    {
        $quotaBytes = $this->getTotalQuotaBytes($tenant);
        $usedBytes  = $this->getUsedBytes($tenant);

        if (($usedBytes + $fileSizeBytes) > $quotaBytes) {
            $usedMb  = number_format($usedBytes / 1024 / 1024, 1);
            $quotaMb = number_format($quotaBytes / 1024 / 1024, 1);

            throw new StorageQuotaExceededException(
                used: $usedMb,
                quota: $quotaMb,
                file_size_mb: number_format($fileSizeBytes / 1024 / 1024, 1)
            );
        }
    }

    public function getTotalQuotaBytes(Tenant $tenant): int
    {
        // Storage base do plano (em GB → bytes)
        $planGb = $this->getPlanStorageGb($tenant);

        // Add-ons contratados
        $addonGb = TenantStorageAddon::where('tenant_id', $tenant->id)
            ->where('active', true)
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->sum('size_gb');

        return ($planGb + $addonGb) * 1024 * 1024 * 1024;
    }

    public function getUsedBytes(Tenant $tenant): int
    {
        return StorageObject::where('tenant_id', $tenant->id)
            ->whereNull('deleted_at')
            ->sum('file_size_bytes');
    }

    public function getUsageStats(Tenant $tenant): array
    {
        $used  = $this->getUsedBytes($tenant);
        $quota = $this->getTotalQuotaBytes($tenant);

        return [
            'used_bytes'      => $used,
            'quota_bytes'     => $quota,
            'used_mb'         => round($used / 1024 / 1024, 1),
            'quota_mb'        => round($quota / 1024 / 1024, 1),
            'used_gb'         => round($used / 1024 / 1024 / 1024, 2),
            'quota_gb'        => round($quota / 1024 / 1024 / 1024, 2),
            'percentage_used' => $quota > 0 ? round(($used / $quota) * 100, 1) : 0,
            'is_near_limit'   => $quota > 0 && ($used / $quota) > 0.85,
            'is_at_limit'     => $quota > 0 && ($used / $quota) >= 1.0,
        ];
    }
}
```

### 4.3 Fluxo de Upload com Validação

```
Usuário envia arquivo
        │
        ▼
1. Validar tipo MIME (lista de tipos permitidos)
2. Validar tamanho máximo por arquivo (ex: 50 MB por arquivo)
3. StorageService::checkQuota($tenant, $fileSize)
        │
        ├── Se quota excedida → HTTP 402 + mensagem comercial
        │
        └── Se quota OK → prosseguir
4. Gerar UUID como nome do arquivo
5. Calcular SHA-256 do arquivo
6. Upload para R2/MinIO via Laravel Filesystem
7. Criar registro em storage_objects
8. Incrementar tenant_usage_counters.storage_mb
9. Retornar URL assinada (signed URL) com expiração
```

### 4.4 Regras de Upload

| Tipo de arquivo | Extensões permitidas | Tamanho máximo |
|---|---|---|
| Documentos | PDF, DOC, DOCX, XLS, XLSX, ODS, ODT | 50 MB |
| Imagens | JPG, JPEG, PNG, WEBP, GIF | 20 MB |
| Vídeos (futuro) | MP4, MOV | 500 MB |
| Outros | ZIP, RAR | 100 MB |

### 4.5 Lixeira e Remoção Definitiva

```
Usuário exclui arquivo
        │
        ├── storage_objects.deleted_at = NOW()
        ├── storage_objects.permanent_delete_at = NOW() + 30 dias
        ├── Arquivo permanece no storage durante os 30 dias
        ├── Uso NÃO é decrementado imediatamente (para evitar abuso)
        │
        └── Scheduler diário: remover arquivos com permanent_delete_at < NOW()
                ├── Delete físico no R2/MinIO
                ├── Delete do registro em storage_objects
                └── Decrementar tenant_usage_counters.storage_mb
```

---

## 5. Dashboard de Armazenamento

O painel do tenant exibe um widget de storage com:

```
┌─────────────────────────────────────────────────────────┐
│  Armazenamento                                          │
│                                                         │
│  ████████████████░░░░  82% usado                        │
│  4,1 GB usados de 5,0 GB                                │
│                                                         │
│  ⚠️  Você está próximo do limite.                        │
│                                                         │
│  [ Contratar mais espaço ]  [ Ver arquivos ]            │
└─────────────────────────────────────────────────────────┘
```

**Alertas automáticos:**
- E-mail ao atingir 80% do storage
- E-mail ao atingir 95% do storage
- Bloqueio de upload ao atingir 100% (com mensagem comercial)

---

## 6. Snapshot Periódico de Uso

Um Job agendado diariamente registra o uso atual de cada tenant:

```php
// app/Jobs/TakeStorageUsageSnapshot.php

class TakeStorageUsageSnapshot implements ShouldQueue
{
    public function handle(): void
    {
        Tenant::active()->each(function (Tenant $tenant) {
            $bytes = StorageObject::where('tenant_id', $tenant->id)
                ->whereNull('deleted_at')
                ->sum('file_size_bytes');

            $files = StorageObject::where('tenant_id', $tenant->id)
                ->whereNull('deleted_at')
                ->count();

            StorageUsageSnapshot::create([
                'tenant_id'   => $tenant->id,
                'total_bytes' => $bytes,
                'total_files' => $files,
            ]);
        });
    }
}
```

Os snapshots permitem:
- Histórico de crescimento de storage por tenant
- Faturamento por uso médio (futuro)
- Alertas de crescimento acelerado

---

## 7. Pacotes Adicionais de Storage (Modelo Comercial)

```
Plano Starter (5 GB) + Pacote +10 GB = 15 GB total
Plano Starter (5 GB) + 2x Pacote +10 GB = 25 GB total
```

| Pacote | Tamanho | Preço/mês |
|---|---|---|
| Storage +10 GB | 10 GB adicionais | R$ 29 |
| Storage +50 GB | 50 GB adicionais | R$ 99 |
| Storage +100 GB | 100 GB adicionais | R$ 179 |

**Super Admin pode conceder storage adicional gratuitamente** (para suporte comercial, período de teste, etc.) com campo `reason` obrigatório.

---

## 8. Verificação de Módulos Habilitados

Além de limites quantitativos, o sistema verifica se o módulo está habilitado no plano antes de permitir acesso:

```php
// app/Http/Middleware/CheckModuleEnabled.php

class CheckModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $tenant = app('tenant');

        $enabled = PlanModule::where('plan_id', $tenant->activePlan->id)
            ->where('module', $module)
            ->where('enabled', true)
            ->exists();

        if (! $enabled) {
            return response()->json([
                'error'   => 'module_not_available',
                'message' => "O módulo '{$module}' não está disponível no seu plano atual.",
                'upgrade' => '/settings/upgrade'
            ], 402);
        }

        return $next($request);
    }
}
```

Uso nas rotas:
```php
Route::middleware(['auth', 'tenant', 'module:financial'])
    ->prefix('financeiro')
    ->group(function () {
        // Rotas do módulo financeiro
    });
```

---

## 9. Monitoramento e Alertas de Limites

O sistema gera alertas automáticos quando:

| Situação | Ação |
|---|---|
| Storage > 80% | E-mail para admin do tenant + registro no dashboard |
| Storage > 95% | E-mail urgente para admin do tenant + notificação in-app |
| Storage = 100% | Bloquear uploads + CTA comercial |
| Condomínios = limite | Bloquear criação + CTA de upgrade |
| Unidades > 90% do limite | Aviso no dashboard |
| API calls > 90%/mês | E-mail de alerta |
| API calls = limite | Bloquear chamadas com HTTP 429 + mensagem comercial |

---

*Documento de modelo comercial e controle de recursos do SindÂncora.*
