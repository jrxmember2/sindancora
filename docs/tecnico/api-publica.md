# API Pública com API Keys (Fase 6.1)

> Status: implementado. API REST versionada em `/api/v1` autenticada por **API Key por tenant**,
> com escopos, rate limit e log de requisições. Endpoints de **leitura e escrita** dos recursos core
> (condomínios, unidades, pessoas, cobranças). Sem migration nova — as tabelas (`api_keys`,
> `api_key_scopes`, `api_request_logs`) já existiam da Fase 1 (`2026_01_01_000014_create_api_tables`).

## Autenticação

`Authorization: Bearer sk_live_xxxxxxxx`. A chave em claro (`sk_live_` + 40 chars) é exibida **uma
única vez** na criação; guardamos só `key_hash` (sha256) e `key_prefix` (12 chars, para exibição).

O tenant é resolvido pelo **domínio** (`ResolveTenant`) e a chave precisa pertencer a ele — chave de
outro tenant no host errado → **403**. Chave ausente/inválida/expirada/revogada → **401**.

## Escopos

Cada recurso expõe `:read` e `:write`. Fonte da verdade: `App\Models\ApiKey::SCOPES` (semeada em
`api_key_scopes` via `ApiScopeSeeder`). Atuais: `condominiums`, `units`, `persons`, `charges`
(`:read`/`:write`). Rota sem o escopo da chave → **403** (`INSUFFICIENT_SCOPE`). Curinga `*` libera
todos.

## Rate limit

Por chave (120 req/min) e por tenant (600 req/min). Ao estourar → **429** com `Retry-After` e
`X-RateLimit-*`. (`App\Http\Middleware\ApiKeyAuth`.)

## Log de requisições

`App\Http\Middleware\LogApiRequest` (terminable) grava em `api_request_logs` método, path, status,
duração, IP, user-agent e `request_id` (devolvido no header `X-Request-Id`). As 50 últimas aparecem
na tela de gestão.

## Endpoints

Envelope padrão: `{ "success": true, "data": ... }` e, em listas, `"meta"` de paginação
(`current_page`, `per_page`, `total`, `last_page`). Erros: `{ "success": false, "error": { code, message } }`.

| Método | Path | Escopo |
|---|---|---|
| GET/POST | `/api/v1/condominiums` | `condominiums:read` / `:write` |
| GET/PUT | `/api/v1/condominiums/{id}` | `condominiums:read` / `:write` |
| GET/POST | `/api/v1/units` | `units:read` / `:write` |
| GET/PUT | `/api/v1/units/{id}` | `units:read` / `:write` |
| GET/POST | `/api/v1/persons` | `persons:read` / `:write` |
| GET/PUT | `/api/v1/persons/{id}` | `persons:read` / `:write` |
| GET/POST | `/api/v1/charges` | `charges:read` / `:write` |
| GET | `/api/v1/charges/{id}` | `charges:read` |

Filtros de lista: `condominiums` (`search`,`status`), `units` (`condominium_id`,`status`),
`persons` (`search`), `charges` (`condominium_id`,`unit_id`,`status`,`reference_month`). Paginação
por `per_page` (1..100, default 20) e `page`.

**Regras reusadas**: limites de plano (`PlanLimitService::check`/`increment` em condomínios e
unidades → `402 PLAN_LIMIT_EXCEEDED`), validação de CPF/CNPJ (`App\Rules\CpfCnpj`), envelope/handlers
de exceção já existentes em `bootstrap/app.php`.

## Gestão (painel)

`/configuracoes/api` (`Panel\ApiKeyController`, permissão `api_keys:manage` — admin já tem). Criar
(nome + escopos + expiração opcional; chave exibida 1x), revogar (`revoked_at`), ver escopos, último
uso e últimas requisições. Menu: item "API" (admin).

## Exemplos

```bash
# Listar condomínios
curl -H "Authorization: Bearer sk_live_xxx" https://CLIENTE.sindancora.com.br/api/v1/condominiums

# Criar pessoa
curl -X POST -H "Authorization: Bearer sk_live_xxx" -H "Content-Type: application/json" \
  -d '{"name":"Maria","cpf":"12345678909","email":"maria@ex.com"}' \
  https://CLIENTE.sindancora.com.br/api/v1/persons
```

## Swagger

Anotações `#[OA\...]` nos controllers `Api\V1\*`; `l5-swagger:generate` roda no `entrypoint.sh`.
Documentação em `/api/documentation` (securityScheme `apiKey` = Bearer `sk_`).

## Deploy

**Sem migration nova.** `optimize:clear` + `l5-swagger:generate` (entrypoint) + `db:seed --force`
(aplica `ApiScopeSeeder`).

## Fora de escopo (próximas fatias)

6.2 Webhooks de saída (tabelas `webhooks`/`webhook_deliveries` já existem); DELETE via API; escopos
por condomínio; paginação por cursor.
