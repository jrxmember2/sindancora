# WhatsApp — Conexões & Licenciamento (Fase 1 da iniciativa Inbox)

Primeira fatia do inbox WhatsApp próprio: **gestão de conexões (números) via Evolution API
auto-hospedada**, licenciada por quantidade. Não inclui recebimento/inbox/chatbot (fases seguintes).

## Conceito

- **1 conexão = 1 instância Evolution = 1 número.** Recurso **licenciado** (`whatsapp_connections`).
- O **servidor Evolution é nosso** (auto-hospedado); a **chave global** fica em `config/services.evolution`
  (env `EVOLUTION_API_KEY` / `EVOLUTION_BASE_URL`) e **nunca** é exposta ao tenant.
- O síndico cria conexões na área **"Conexão do WhatsApp"** até o limite do plano (+ add-ons) e pareia
  cada número lendo o **QR Code** no painel.
- Cada conexão atende **N condomínios** (pivô `whatsapp_connection_condominium`): 1→1, 1→N ou N números
  cada um seu condomínio. Quando atende >1 condomínio, um chatbot de seleção será obrigatório (fase futura).

## Licenciamento

Recurso `whatsapp_connections` adicionado ao mecanismo existente (Fase 1 do produto):
- **Plano** (`PlanSeeder`): Starter 1, Profissional 3, Business 10, Enterprise ilimitado (-1). Label no
  `Admin\PlanController::RESOURCES`.
- **Add-on**: tabela `tenant_whatsapp_addons` (espelha `tenant_storage_addons`) — conexões avulsas.
- **Override** por tenant via `TenantLimit` (já existente).
- `App\Services\WhatsappConnectionService`: `limit()` (plano/override + add-ons ativos), `used()`
  (contagem viva), `assertCanCreate()` → lança `PlanLimitException` (**402 PLAN_LIMIT_EXCEEDED**).

## Dados

Migration `2026_06_09_000001_create_whatsapp_connection_tables`:
- `whatsapp_connections` (tenant_id, name, `instance` único, `token` **encrypted**, phone_number,
  status disconnected|connecting|connected, bot_enabled, last_connected_at). Model `WhatsappConnection`
  (`$table` explícito; relação `condominiums()` belongsToMany).
- `whatsapp_connection_condominium` (pivô N:N).
- `tenant_whatsapp_addons` (model `TenantWhatsappAddon`).
- `Tenant::whatsappConnections()` e `whatsappAddons()`.

## Evolution (gestão de instância)

`App\Services\Whatsapp\EvolutionManager` (chave global): `createInstance`, `connect` (QR), `connectionState`,
`logout`, `deleteInstance`, `sendText(connection,…)` (usa o token da instância). `isConfigured()` guarda
quando o servidor não está configurado.

## Painel

`Panel\WhatsappConnectionController` (permissão `settings:whatsapp`) em `/configuracoes/whatsapp/conexoes`:
- `index` (lista + uso/limite + condomínios + flag de configuração),
- `store` (cria respeitando a licença → cria instância na Evolution → grava token/instância),
- `connect` (JSON com QR/código — consumido por fetch no modal, com **polling** de `state`),
- `state` (atualiza status/`last_connected_at`),
- `syncCondominiums` (aloca condomínios),
- `destroy` (logout+delete na Evolution + soft delete local).
Página `Settings/WhatsappConnections.tsx` (criar, modal de QR com polling, alocação de condomínios,
status, remover). Menu "WhatsApp" repontado para esta área. A tela legada `/configuracoes/whatsapp`
(instância única, `TenantWhatsappSetting`) permanece registrada, porém **desvinculada do menu**.

## Notificações (canal migrado)

`WhatsAppChannel` agora resolve uma **conexão conectada** do tenant (preferindo a que atende um
condomínio do destinatário; senão a primeira conectada) e envia via `EvolutionManager::sendText`.
Sem conexão conectada → não envia (degradação graciosa). Supera o uso do `TenantWhatsappSetting` no envio.

## Deploy

`migrate --force` (tabelas de conexões + `evolution_settings`) + `db:seed --force` (PlanSeeder adiciona
o limite `whatsapp_connections` aos planos) + `optimize:clear`. O servidor Evolution é configurado pelo
**super admin** em `/admin/whatsapp` (URL + chave global, encriptada) — ver `docs/tecnico/evolution-servidor.md`.
As envs `EVOLUTION_BASE_URL`/`EVOLUTION_API_KEY` continuam como fallback opcional. Worker de fila ativo
(notificações WhatsApp rodam em fila).

## Próximas fases

2) Recebimento (webhook) + inbox; 3) setores + chatbot (menu de condomínio obrigatório no multi) +
fora de expediente; 4) mídia (StorageService) + respostas prontas; 5) tempo real (Reverb); (paralelo)
comunicado em massa. Backlog: drive externo (Google Drive) e limite de tamanho de mídia.
