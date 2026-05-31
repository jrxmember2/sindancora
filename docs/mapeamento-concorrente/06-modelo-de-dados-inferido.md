# 06 — Modelo de Dados Inferido

> Gerado automaticamente em 31/05/2026, 11:51:45
> Baseado em: observações das telas + padrões do domínio condominial

**Legenda:** `[OBSERVADO]` confirmado na tela | `[INFERIDO]` deduzido do padrão | `[RECOMENDADO]` para o novo sistema

---

## Diagrama ER Simplificado (texto)

```
tenants
  └── plans
  └── users
        └── user_roles → roles → role_permissions → permissions
  └── condominiums
        └── blocks
        └── units
              └── unit_persons → persons
              └── charges
              └── reservations → common_areas
        └── announcements
        └── occurrences
        └── documents
        └── notifications
  └── audit_logs
```

---

## Tabelas Propostas

### `tenants`

**Finalidade:** Administradoras ou grupos que contratam o SaaS

| Campo | Descrição |
| --- | --- |
| id | — |
| name | — |
| document | (CNPJ) |
| plan_id | — |
| status | — |
| settings | (jsonb) |
| created_at | — |
| updated_at | — |
| deleted_at | — |

**Chaves Estrangeiras:**
- `plan_id → plans`

**Índices Recomendados:** `name`, `document`, `status`

**Multitenancy:** Raiz da hierarquia multi-tenant

**Exclusão lógica:** deleted_at IS NULL

**Auditoria:** tenant_audit_logs

---

### `plans`

**Finalidade:** Planos de assinatura do SaaS

| Campo | Descrição |
| --- | --- |
| id | — |
| name | — |
| max_condominiums | — |
| max_units | — |
| max_users | — |
| features | (jsonb) |
| price_monthly | — |
| active | — |
| created_at | — |

**Chaves Estrangeiras:**
- N/A

**Índices Recomendados:** `name`, `active`

**Multitenancy:** Global — sem tenant_id

**Exclusão lógica:** active = false

**Auditoria:** plan_audit_logs

---

### `users`

**Finalidade:** Usuários do sistema (todos os tipos)

| Campo | Descrição |
| --- | --- |
| id | — |
| tenant_id | — |
| name | — |
| email | — |
| phone | — |
| document | (CPF) |
| password_hash | — |
| status | — |
| last_login_at | — |
| created_at | — |
| updated_at | — |
| deleted_at | — |

**Chaves Estrangeiras:**
- `tenant_id → tenants`

**Índices Recomendados:** `email`, `document`, `tenant_id`, `status`

**Multitenancy:** tenant_id obrigatório

**Exclusão lógica:** deleted_at IS NULL

**Auditoria:** user_audit_logs

---

### `roles`

**Finalidade:** Perfis de acesso (RBAC)

| Campo | Descrição |
| --- | --- |
| id | — |
| tenant_id | — |
| name | — |
| description | — |
| is_system | — |
| created_at | — |

**Chaves Estrangeiras:**
- `tenant_id → tenants`

**Índices Recomendados:** `tenant_id`, `name`

**Multitenancy:** Roles padrão (is_system=true) + customizadas por tenant

**Exclusão lógica:** N/A — nunca exclui roles do sistema

**Auditoria:** role_audit_logs

---

### `user_roles`

**Finalidade:** Vínculo usuário ↔ perfil ↔ condomínio

| Campo | Descrição |
| --- | --- |
| id | — |
| user_id | — |
| role_id | — |
| condominium_id | (nullable) |
| created_at | — |

**Chaves Estrangeiras:**
- `user_id → users`
- `role_id → roles`
- `condominium_id → condominiums`

**Índices Recomendados:** `user_id`, `role_id`, `condominium_id`

**Multitenancy:** Herdado via user_id.tenant_id

**Exclusão lógica:** N/A — delete físico ao revogar

**Auditoria:** user_audit_logs

---

### `permissions`

**Finalidade:** Permissões granulares do sistema

| Campo | Descrição |
| --- | --- |
| id | — |
| module | — |
| action | — |
| description | — |

**Chaves Estrangeiras:**
- N/A

**Índices Recomendados:** `module`, `action`

**Multitenancy:** Global

**Exclusão lógica:** N/A

**Auditoria:** N/A

---

### `role_permissions`

**Finalidade:** Permissões atribuídas a cada role

| Campo | Descrição |
| --- | --- |
| id | — |
| role_id | — |
| permission_id | — |

**Chaves Estrangeiras:**
- `role_id → roles`
- `permission_id → permissions`

**Índices Recomendados:** `role_id`, `permission_id`

**Multitenancy:** Herdado via role

**Exclusão lógica:** N/A

**Auditoria:** N/A

---

### `condominiums`

**Finalidade:** Condomínios gerenciados

| Campo | Descrição |
| --- | --- |
| id | — |
| tenant_id | — |
| name | — |
| document | (CNPJ) |
| address | (jsonb) |
| total_units | — |
| status | — |
| settings | (jsonb) |
| created_at | — |
| updated_at | — |
| deleted_at | — |

**Chaves Estrangeiras:**
- `tenant_id → tenants`

**Índices Recomendados:** `tenant_id`, `document`, `status`

**Multitenancy:** tenant_id obrigatório

**Exclusão lógica:** deleted_at IS NULL

**Auditoria:** condominium_audit_logs

---

### `blocks`

**Finalidade:** Blocos/Torres de um condomínio

| Campo | Descrição |
| --- | --- |
| id | — |
| condominium_id | — |
| name | — |
| floors | — |
| created_at | — |

**Chaves Estrangeiras:**
- `condominium_id → condominiums`

**Índices Recomendados:** `condominium_id`

**Multitenancy:** Herdado via condominium_id

**Exclusão lógica:** N/A

**Auditoria:** N/A

---

### `units`

**Finalidade:** Unidades (apartamentos, salas, garagens)

| Campo | Descrição |
| --- | --- |
| id | — |
| condominium_id | — |
| block_id | (nullable) |
| unit_number | — |
| floor | — |
| type | — |
| area | — |
| fraction | — |
| status | — |
| created_at | — |
| updated_at | — |

**Chaves Estrangeiras:**
- `condominium_id → condominiums`
- `block_id → blocks`

**Índices Recomendados:** `condominium_id`, `block_id`, `status`

**Multitenancy:** Herdado via condominium_id

**Exclusão lógica:** status = inactive

**Auditoria:** unit_audit_logs

---

### `persons`

**Finalidade:** Cadastro de pessoas (proprietários, moradores, locatários)

| Campo | Descrição |
| --- | --- |
| id | — |
| tenant_id | — |
| name | — |
| document | (CPF) |
| email | — |
| phone | — |
| birth_date | — |
| address | (jsonb) |
| created_at | — |
| updated_at | — |
| deleted_at | — |

**Chaves Estrangeiras:**
- `tenant_id → tenants`

**Índices Recomendados:** `tenant_id`, `document`, `email`

**Multitenancy:** tenant_id obrigatório

**Exclusão lógica:** deleted_at IS NULL

**Auditoria:** person_audit_logs

---

### `unit_persons`

**Finalidade:** Vínculos pessoa ↔ unidade (proprietário, locatário, morador)

| Campo | Descrição |
| --- | --- |
| id | — |
| unit_id | — |
| person_id | — |
| type | (owner|tenant|resident|dependent) |
| start_date | — |
| end_date | — |
| is_primary | — |
| user_id | (nullable) |
| created_at | — |

**Chaves Estrangeiras:**
- `unit_id → units`
- `person_id → persons`
- `user_id → users`

**Índices Recomendados:** `unit_id`, `person_id`, `type`, `end_date`

**Multitenancy:** Herdado via unit_id

**Exclusão lógica:** end_date = data de saída

**Auditoria:** unit_person_audit_logs

---

### `announcements`

**Finalidade:** Comunicados para moradores

| Campo | Descrição |
| --- | --- |
| id | — |
| condominium_id | — |
| author_id | — |
| title | — |
| body | — |
| audience | (all|block|unit|role) |
| audience_ids | (jsonb) |
| published_at | — |
| expires_at | — |
| created_at | — |
| updated_at | — |

**Chaves Estrangeiras:**
- `condominium_id → condominiums`
- `author_id → users`

**Índices Recomendados:** `condominium_id`, `published_at`, `audience`

**Multitenancy:** Herdado via condominium_id

**Exclusão lógica:** expires_at

**Auditoria:** N/A

---

### `occurrences`

**Finalidade:** Registro de ocorrências/reclamações

| Campo | Descrição |
| --- | --- |
| id | — |
| condominium_id | — |
| unit_id | — |
| reporter_id | — |
| title | — |
| description | — |
| category | — |
| status | (open|in_progress|closed) |
| priority | — |
| resolved_at | — |
| created_at | — |
| updated_at | — |

**Chaves Estrangeiras:**
- `condominium_id → condominiums`
- `unit_id → units`
- `reporter_id → users`

**Índices Recomendados:** `condominium_id`, `status`, `category`, `reporter_id`

**Multitenancy:** Herdado via condominium_id

**Exclusão lógica:** status = closed

**Auditoria:** occurrence_updates

---

### `common_areas`

**Finalidade:** Áreas comuns disponíveis para reserva

| Campo | Descrição |
| --- | --- |
| id | — |
| condominium_id | — |
| name | — |
| description | — |
| capacity | — |
| requires_approval | — |
| fee | — |
| deposit | — |
| rules | — |
| active | — |
| created_at | — |

**Chaves Estrangeiras:**
- `condominium_id → condominiums`

**Índices Recomendados:** `condominium_id`, `active`

**Multitenancy:** Herdado via condominium_id

**Exclusão lógica:** active = false

**Auditoria:** N/A

---

### `reservations`

**Finalidade:** Reservas de áreas comuns

| Campo | Descrição |
| --- | --- |
| id | — |
| common_area_id | — |
| unit_id | — |
| requester_id | — |
| date_start | — |
| date_end | — |
| status | (pending|approved|cancelled|done) |
| fee_paid | — |
| notes | — |
| created_at | — |
| updated_at | — |

**Chaves Estrangeiras:**
- `common_area_id → common_areas`
- `unit_id → units`
- `requester_id → users`

**Índices Recomendados:** `common_area_id`, `date_start`, `status`

**Multitenancy:** Herdado via common_area_id

**Exclusão lógica:** status = cancelled

**Auditoria:** reservation_audit_logs

---

### `documents`

**Finalidade:** Documentos do condomínio

| Campo | Descrição |
| --- | --- |
| id | — |
| condominium_id | — |
| uploader_id | — |
| name | — |
| category | — |
| description | — |
| file_path | — |
| file_size | — |
| mime_type | — |
| is_public | — |
| created_at | — |

**Chaves Estrangeiras:**
- `condominium_id → condominiums`
- `uploader_id → users`

**Índices Recomendados:** `condominium_id`, `category`, `is_public`

**Multitenancy:** Herdado via condominium_id

**Exclusão lógica:** N/A — delete físico controlado

**Auditoria:** document_audit_logs

---

### `charges`

**Finalidade:** Cobranças por unidade

| Campo | Descrição |
| --- | --- |
| id | — |
| condominium_id | — |
| unit_id | — |
| type | (condo_fee|extra|fine|other) |
| amount | — |
| due_date | — |
| paid_at | — |
| status | (open|paid|overdue|cancelled) |
| reference_month | — |
| description | — |
| created_at | — |
| updated_at | — |

**Chaves Estrangeiras:**
- `condominium_id → condominiums`
- `unit_id → units`

**Índices Recomendados:** `condominium_id`, `unit_id`, `status`, `due_date`, `reference_month`

**Multitenancy:** Herdado via condominium_id

**Exclusão lógica:** status = cancelled

**Auditoria:** charge_audit_logs

---

### `notifications`

**Finalidade:** Notificações do sistema

| Campo | Descrição |
| --- | --- |
| id | — |
| user_id | — |
| type | — |
| title | — |
| body | — |
| channel | (email|push|whatsapp|sms) |
| read_at | — |
| sent_at | — |
| created_at | — |

**Chaves Estrangeiras:**
- `user_id → users`

**Índices Recomendados:** `user_id`, `read_at`, `sent_at`

**Multitenancy:** Herdado via user_id

**Exclusão lógica:** N/A

**Auditoria:** N/A

---

### `audit_logs`

**Finalidade:** Trilha de auditoria geral

| Campo | Descrição |
| --- | --- |
| id | — |
| tenant_id | — |
| user_id | — |
| action | — |
| entity | — |
| entity_id | — |
| old_value | (jsonb) |
| new_value | (jsonb) |
| ip_address | — |
| user_agent | — |
| created_at | — |

**Chaves Estrangeiras:**
- `tenant_id → tenants`
- `user_id → users`

**Índices Recomendados:** `tenant_id`, `user_id`, `entity`, `entity_id`, `created_at`

**Multitenancy:** tenant_id obrigatório

**Exclusão lógica:** N/A — imutável

**Auditoria:** Própria tabela

---

## Observações de Multitenancy

- Toda query deve filtrar por `tenant_id` (ou via JOIN até tenant)
- Usar Row-Level Security (RLS) no PostgreSQL para garantia adicional
- Nunca expor `id` numérico em APIs — usar UUIDs
- Índice composto obrigatório: `(tenant_id, <campo_buscado>)`

---

> **Próximo passo:** Execute `npm run phase:07` para mapear perfis e permissões.
