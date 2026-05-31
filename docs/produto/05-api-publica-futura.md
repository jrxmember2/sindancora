# 05 — Estratégia da API Pública do SindÂncora

> Versão: 1.0 — 31/05/2026
> Estratégia: API REST versionada, preparada desde o início, exposta progressivamente conforme as fases de desenvolvimento

---

## 1. Filosofia da API

### 1.1 Princípios

1. **Versionada desde o início** — toda rota começa em `/api/v1` para permitir evolução sem quebrar integrações existentes
2. **Preparada antes de ser pública** — a estrutura, autenticação e tabelas de API são criadas na Fase 1, mas a API pública (via API Key) só é liberada na Fase 6
3. **Tenant-first** — toda operação na API está vinculada a um tenant; não existe chamada global sem autenticação
4. **REST puro** — sem GraphQL no MVP; verbos HTTP corretos (GET, POST, PUT, PATCH, DELETE)
5. **Respostas consistentes** — envelope JSON padronizado em todas as respostas
6. **Documentação viva** — Swagger gerado automaticamente a partir das annotations PHP

### 1.2 Audiências da API

| Audiência | Tipo de acesso | Disponibilidade |
|---|---|---|
| Painel admin (React/Inertia) | Cookie de sessão Laravel | Desde a Fase 1 (interno) |
| Portal do morador (SPA) | Bearer token Sanctum | Desde a Fase 4 |
| App mobile futuro | Bearer token Sanctum | Fase 5+ |
| Integrações externas (n8n, etc.) | API Key por tenant | Fase 6 |
| Webhooks de saída | Assinado com secret | Fase 6 |

---

## 2. Autenticação

### 2.1 Autenticação do Painel (interno)

Usa **Laravel Sanctum em modo SPA** (cookie-based):
- `POST /sanctum/csrf-cookie` — obtém cookie CSRF
- `POST /api/v1/auth/login` — autentica e cria sessão cookie
- Sem tokens no localStorage — mais seguro contra XSS
- Refresh automático pela sessão Laravel

### 2.2 Autenticação Mobile / API Token

Usa **Laravel Sanctum em modo API Token**:
- `POST /api/v1/auth/login` — retorna `access_token` e `refresh_token`
- `Authorization: Bearer {access_token}` em todas as requisições
- Access token expira em 60 minutos
- Refresh token expira em 30 dias
- `POST /api/v1/auth/refresh` — renova access token

### 2.3 API Keys por Tenant (Fase 6 — Integrações Externas)

```http
Authorization: Bearer sa_live_xxxxxxxxxxxxxxxxxxxxxxxx
X-Tenant-ID: {tenant_uuid}   # opcional se a key já identifica o tenant
```

Cada API Key:
- Pertence a um tenant específico
- Tem um conjunto de escopos definidos (ex: `condominiums:read`, `announcements:create`)
- Tem rate limit próprio
- Tem data de expiração opcional
- Pode ser revogada a qualquer momento
- Gera log em `api_request_logs`

---

## 3. Padrão de Resposta

### 3.1 Resposta de Sucesso

```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "timestamp": "2026-05-31T10:30:00Z",
    "version": "v1"
  }
}
```

### 3.2 Resposta de Lista (Paginada)

```json
{
  "success": true,
  "data": [ ... ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8,
    "from": 1,
    "to": 20
  },
  "meta": {
    "timestamp": "2026-05-31T10:30:00Z",
    "version": "v1"
  }
}
```

### 3.3 Resposta de Erro

```json
{
  "success": false,
  "error": {
    "code": "PLAN_LIMIT_EXCEEDED",
    "message": "Você atingiu o limite de condomínios do seu plano (Starter: 1 condomínio).",
    "details": {
      "resource": "condominiums",
      "current": 1,
      "limit": 1,
      "plan": "Starter"
    },
    "upgrade_url": "/configuracoes/plano"
  },
  "meta": {
    "timestamp": "2026-05-31T10:30:00Z",
    "version": "v1"
  }
}
```

### 3.4 Códigos HTTP Utilizados

| Código | Situação |
|---|---|
| 200 | OK — sucesso |
| 201 | Created — recurso criado |
| 204 | No Content — operação sem retorno (DELETE) |
| 400 | Bad Request — dados inválidos na requisição |
| 401 | Unauthorized — não autenticado |
| 403 | Forbidden — autenticado mas sem permissão |
| 402 | Payment Required — limite de plano atingido |
| 404 | Not Found — recurso não encontrado |
| 409 | Conflict — conflito (ex: CPF duplicado, reserva conflitante) |
| 422 | Unprocessable Entity — falha de validação com detalhes |
| 429 | Too Many Requests — rate limit atingido |
| 500 | Internal Server Error — erro não tratado |
| 503 | Service Unavailable — manutenção ou tenant suspenso |

---

## 4. Endpoints — Fase 1 (Base)

### Autenticação

| Método | Rota | Descrição |
|---|---|---|
| POST | `/api/v1/auth/login` | Login com e-mail e senha |
| POST | `/api/v1/auth/refresh` | Renovar access token |
| POST | `/api/v1/auth/logout` | Revogar sessão |
| POST | `/api/v1/auth/forgot-password` | Solicitar redefinição de senha |
| POST | `/api/v1/auth/reset-password` | Redefinir senha com token |
| GET | `/api/v1/me` | Dados do usuário autenticado |

### Tenant Atual

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/v1/tenants/current` | Dados do tenant + plano + limites |
| GET | `/api/v1/tenants/current/usage` | Uso atual de recursos |
| GET | `/api/v1/tenants/current/storage` | Uso e quota de armazenamento |

### Usuários

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/v1/users` | Listar usuários do tenant |
| POST | `/api/v1/users` | Criar usuário |
| GET | `/api/v1/users/{id}` | Buscar usuário |
| PATCH | `/api/v1/users/{id}` | Atualizar usuário |
| DELETE | `/api/v1/users/{id}` | Desativar usuário |

### Roles e Permissões

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/v1/roles` | Listar perfis do tenant |
| POST | `/api/v1/roles` | Criar perfil customizado |
| GET | `/api/v1/roles/{id}` | Buscar perfil |
| PATCH | `/api/v1/roles/{id}` | Atualizar permissões |
| GET | `/api/v1/permissions` | Listar todas as permissões disponíveis |

---

## 5. Endpoints — Fase 2 (Cadastros)

### Condomínios

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/v1/condominiums` | Listar condomínios do tenant |
| POST | `/api/v1/condominiums` | Criar condomínio |
| GET | `/api/v1/condominiums/{id}` | Buscar condomínio |
| PATCH | `/api/v1/condominiums/{id}` | Atualizar condomínio |
| DELETE | `/api/v1/condominiums/{id}` | Arquivar condomínio |

### Blocos e Unidades

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/v1/condominiums/{id}/blocks` | Listar blocos |
| POST | `/api/v1/condominiums/{id}/blocks` | Criar bloco |
| GET | `/api/v1/condominiums/{id}/units` | Listar unidades |
| POST | `/api/v1/condominiums/{id}/units` | Criar unidade |
| GET | `/api/v1/units/{id}` | Buscar unidade |
| PATCH | `/api/v1/units/{id}` | Atualizar unidade |
| POST | `/api/v1/condominiums/{id}/units/import` | Importar unidades via CSV |

### Pessoas e Vínculos

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/v1/persons` | Listar pessoas do tenant |
| POST | `/api/v1/persons` | Cadastrar pessoa |
| GET | `/api/v1/persons/{id}` | Buscar pessoa |
| PATCH | `/api/v1/persons/{id}` | Atualizar pessoa |
| GET | `/api/v1/units/{id}/persons` | Listar vínculos da unidade |
| POST | `/api/v1/units/{id}/persons` | Vincular pessoa à unidade |
| PATCH | `/api/v1/units/{unit_id}/persons/{link_id}` | Atualizar vínculo |
| DELETE | `/api/v1/units/{unit_id}/persons/{link_id}` | Remover vínculo |

---

## 6. Endpoints — Fase 3 (Operação)

### Comunicados

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/v1/condominiums/{id}/announcements` | Listar comunicados |
| POST | `/api/v1/condominiums/{id}/announcements` | Criar comunicado |
| GET | `/api/v1/announcements/{id}` | Buscar comunicado |
| PATCH | `/api/v1/announcements/{id}` | Atualizar comunicado |
| DELETE | `/api/v1/announcements/{id}` | Remover comunicado |
| POST | `/api/v1/announcements/{id}/read` | Marcar como lido (morador) |

### Ocorrências

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/v1/condominiums/{id}/occurrences` | Listar ocorrências |
| POST | `/api/v1/condominiums/{id}/occurrences` | Registrar ocorrência |
| GET | `/api/v1/occurrences/{id}` | Buscar ocorrência |
| PATCH | `/api/v1/occurrences/{id}` | Atualizar status/campos |
| POST | `/api/v1/occurrences/{id}/updates` | Adicionar comentário/atualização |
| GET | `/api/v1/occurrences/{id}/updates` | Histórico de atualizações |

### Reservas

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/v1/condominiums/{id}/common-areas` | Listar áreas comuns |
| POST | `/api/v1/condominiums/{id}/common-areas` | Criar área comum |
| GET | `/api/v1/common-areas/{id}/availability` | Disponibilidade (calendário) |
| GET | `/api/v1/condominiums/{id}/reservations` | Listar reservas |
| POST | `/api/v1/condominiums/{id}/reservations` | Criar reserva |
| GET | `/api/v1/reservations/{id}` | Buscar reserva |
| PATCH | `/api/v1/reservations/{id}/approve` | Aprovar reserva |
| PATCH | `/api/v1/reservations/{id}/reject` | Recusar reserva |
| PATCH | `/api/v1/reservations/{id}/cancel` | Cancelar reserva |

### Documentos

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/v1/condominiums/{id}/documents` | Listar documentos |
| POST | `/api/v1/condominiums/{id}/documents` | Upload de documento |
| GET | `/api/v1/documents/{id}` | Buscar documento |
| GET | `/api/v1/documents/{id}/download` | URL de download (signed URL) |
| DELETE | `/api/v1/documents/{id}` | Remover documento |

### Notificações

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/v1/notifications` | Listar notificações do usuário |
| PATCH | `/api/v1/notifications/{id}/read` | Marcar como lida |
| POST | `/api/v1/notifications/read-all` | Marcar todas como lidas |

---

## 7. Endpoints — Fase 5 (Financeiro)

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/v1/condominiums/{id}/charges` | Listar cobranças |
| POST | `/api/v1/condominiums/{id}/charges` | Criar cobrança manual |
| POST | `/api/v1/condominiums/{id}/charges/generate` | Gerar cobranças do mês |
| GET | `/api/v1/charges/{id}` | Buscar cobrança |
| PATCH | `/api/v1/charges/{id}/mark-paid` | Marcar pagamento manual |
| GET | `/api/v1/condominiums/{id}/charges/delinquency` | Relatório de inadimplência |
| POST | `/api/v1/condominiums/{id}/reports/export` | Exportar relatório (PDF/XLSX) |

---

## 8. Endpoints — Fase 6 (API Pública e Webhooks)

### API Keys

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/v1/api-keys` | Listar API Keys do tenant |
| POST | `/api/v1/api-keys` | Criar API Key |
| GET | `/api/v1/api-keys/{id}` | Buscar API Key |
| PATCH | `/api/v1/api-keys/{id}` | Atualizar nome/escopos/expiração |
| DELETE | `/api/v1/api-keys/{id}` | Revogar API Key |

### Webhooks

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/v1/webhooks` | Listar webhooks do tenant |
| POST | `/api/v1/webhooks` | Criar webhook |
| GET | `/api/v1/webhooks/{id}` | Buscar webhook |
| PATCH | `/api/v1/webhooks/{id}` | Atualizar webhook |
| DELETE | `/api/v1/webhooks/{id}` | Remover webhook |
| GET | `/api/v1/webhooks/{id}/deliveries` | Histórico de entregas |
| POST | `/api/v1/webhooks/{id}/test` | Testar webhook |

### Storage (Público)

| Método | Rota | Descrição |
|---|---|---|
| GET | `/api/v1/storage/usage` | Uso atual do tenant |
| GET | `/api/v1/storage/quota` | Quota total do tenant |

---

## 9. Tabelas de Suporte à API

```sql
-- Chaves de API por tenant
CREATE TABLE api_keys (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id        UUID NOT NULL REFERENCES tenants(id),
    name             VARCHAR(100) NOT NULL,
    key_hash         VARCHAR(64) NOT NULL UNIQUE,    -- hash SHA-256 da key
    key_prefix       VARCHAR(10) NOT NULL,           -- ex: 'sa_live_' para exibição
    scopes           TEXT[] DEFAULT '{}',            -- ex: ['condominiums:read']
    expires_at       TIMESTAMPTZ,
    last_used_at     TIMESTAMPTZ,
    created_by       UUID REFERENCES users(id),
    revoked_at       TIMESTAMPTZ,
    created_at       TIMESTAMPTZ DEFAULT NOW()
);

-- Escopos possíveis (para documentação e validação)
CREATE TABLE api_key_scopes (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    scope            VARCHAR(100) UNIQUE NOT NULL,   -- ex: 'condominiums:read'
    description      TEXT,
    module           VARCHAR(50)
);

-- Log de requisições da API
CREATE TABLE api_request_logs (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id        UUID REFERENCES tenants(id),
    api_key_id       UUID REFERENCES api_keys(id),
    method           VARCHAR(10),
    path             VARCHAR(500),
    status_code      SMALLINT,
    duration_ms      INTEGER,
    ip_address       INET,
    user_agent       TEXT,
    request_id       UUID,
    created_at       TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_api_logs_tenant_created ON api_request_logs(tenant_id, created_at DESC);
CREATE INDEX idx_api_logs_key ON api_request_logs(api_key_id, created_at DESC);

-- Webhooks configurados por tenant
CREATE TABLE webhooks (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id        UUID NOT NULL REFERENCES tenants(id),
    url              VARCHAR(500) NOT NULL,
    description      VARCHAR(200),
    events           TEXT[] NOT NULL,                -- ex: ['occurrence.created', 'reservation.approved']
    secret           VARCHAR(64),                    -- para assinar o payload
    active           BOOLEAN DEFAULT true,
    created_at       TIMESTAMPTZ DEFAULT NOW(),
    updated_at       TIMESTAMPTZ DEFAULT NOW()
);

-- Histórico de entregas de webhook
CREATE TABLE webhook_deliveries (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    webhook_id       UUID NOT NULL REFERENCES webhooks(id),
    event            VARCHAR(100) NOT NULL,
    payload          JSONB,
    response_status  SMALLINT,
    response_body    TEXT,
    duration_ms      INTEGER,
    attempts         SMALLINT DEFAULT 1,
    next_retry_at    TIMESTAMPTZ,
    delivered_at     TIMESTAMPTZ,
    failed_at        TIMESTAMPTZ,
    created_at       TIMESTAMPTZ DEFAULT NOW()
);
```

---

## 10. Rate Limiting

### 10.1 Limites por Contexto

| Contexto | Limite | Janela |
|---|---|---|
| Auth (login, reset) | 10 req | 1 minuto por IP |
| API interna (painel) | 300 req | 1 minuto por usuário |
| API pública (API Key — Starter) | 30 req | 1 minuto |
| API pública (API Key — Profissional) | 100 req | 1 minuto |
| API pública (API Key — Business) | 500 req | 1 minuto |
| API pública (API Key — Enterprise) | Configurável | Configurável |

### 10.2 Resposta ao Atingir Rate Limit

```http
HTTP/1.1 429 Too Many Requests
Retry-After: 30
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1748694600

{
  "success": false,
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Muitas requisições. Aguarde 30 segundos antes de tentar novamente.",
    "retry_after": 30
  }
}
```

---

## 11. Eventos de Webhook

Os eventos disponíveis para webhook seguem o padrão `entidade.acao`:

| Evento | Quando dispara |
|---|---|
| `announcement.published` | Comunicado publicado |
| `occurrence.created` | Nova ocorrência aberta |
| `occurrence.status_changed` | Status de ocorrência alterado |
| `occurrence.closed` | Ocorrência encerrada |
| `reservation.requested` | Nova reserva solicitada |
| `reservation.approved` | Reserva aprovada |
| `reservation.rejected` | Reserva recusada |
| `reservation.cancelled` | Reserva cancelada |
| `charge.created` | Nova cobrança criada |
| `charge.paid` | Cobrança marcada como paga |
| `charge.overdue` | Cobrança vencida (job diário) |
| `user.invited` | Usuário convidado |
| `resident.invited` | Morador convidado para o portal |
| `document.uploaded` | Documento enviado |
| `storage.quota_warning` | Uso de storage > 80% |

---

## 12. Idempotency Keys

Para operações de escrita críticas (criação de cobranças, envio de comunicados), a API suportará Idempotency Keys:

```http
POST /api/v1/condominiums/{id}/charges
Idempotency-Key: {client-generated-uuid}
```

Se a mesma key for enviada novamente dentro de 24 horas, o servidor retorna a resposta original em vez de processar novamente — protegendo contra duplicações por retry.

---

## 13. Versionamento

| Versão | Status | Notas |
|---|---|---|
| `/api/v1` | Ativa — desenvolvimento inicial | MVP completo |
| `/api/v2` | Futura | Somente após v1 estável em produção |

Regras de versionamento:
- Mudanças breaking (remoção de campos, mudança de contratos) exigem nova versão
- Adição de campos ou novos endpoints não exige nova versão
- Versões antigas são suportadas por no mínimo 12 meses após deprecação
- Deprecações são anunciadas via header `Deprecation` e e-mail

---

## 14. Documentação

### 14.1 OpenAPI / Swagger

- Gerado automaticamente via `darkaonline/l5-swagger`
- Disponível em `/api/documentation` (apenas ambiente staging e produção com acesso autenticado)
- Annotations PHP nas rotas e controllers
- Inclui exemplos de request/response para cada endpoint

### 14.2 Guias de Integração

A partir da Fase 6, criar documentação pública em `docs/api/`:
- Guia de autenticação com API Key
- Exemplos de integração com n8n
- Exemplos de webhook listener
- SDK JavaScript básico (futuro)

---

*Documento de estratégia de API do SindÂncora. A API pública será liberada gradualmente conforme as fases de desenvolvimento.*
