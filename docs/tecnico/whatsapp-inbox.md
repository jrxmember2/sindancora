# WhatsApp — Recebimento & Inbox (Fase 2 da iniciativa)

Segunda fatia: o sistema passa a **receber** mensagens e a equipe a **responder** de dentro do
painel. Sem setores e sem chatbot (Fase 3).

## Recebimento (webhook)

`POST /api/webhooks/evolution` (`Api\EvolutionWebhookController`, público — `ResolveTenant` ignora
`api/webhooks/*`). Configure a URL no super admin (`/admin/whatsapp` → campo webhook); a Evolution
passa a enviar eventos. Tratados:
- **`messages.upsert`**: identifica a conexão pela `instance`; ignora grupos (`@g.us`) e `status@broadcast`;
  extrai telefone (jid), nome (`pushName`), id (`key.id`) e texto; grava via `WaInboxService`. Mídia
  entra como placeholder (Fase 4).
- **`connection.update`**: atualiza o `status` da conexão automaticamente (não depende mais do polling da tela).

Responde sempre 200 (evita reenvio em massa). Dedup por `wa_message_id` (inclui o eco das mensagens
que nós mesmos enviamos).

## Resolução de condomínio (Fase 2)

`WaInboxService` resolve o condomínio **apenas quando a conexão atende exatamente 1 condomínio**.
Conexões **multi-condomínio** ficam com `condominium_id = null` ("sem condomínio") — o roteamento por
**menu de chatbot** chega na **Fase 3** (decisão do usuário: multi-condomínio 100% na Fase 3).

## Dados

Migration `2026_06_10_000001_create_wa_inbox_tables`:
- `wa_conversations` (tenant_id, connection_id, condominium_id nullable, contact_phone, contact_name,
  status open|closed, assigned_to, unread_count, last_message_at). Única por `connection_id+contact_phone`.
- `wa_messages` (conversation_id, direction in|out, body, `wa_message_id` único p/ dedupe, sent_by,
  created_at). Models `WaConversation`/`WaMessage` (`$table` explícito, BelongsToTenant).

## Inbox (painel)

Permissão nova **`inbox:use`** (admin + síndico no seed). `Panel\InboxController` em `/inbox`:
- `index`: duas colunas — lista de conversas (filtro por condomínio + abertas/encerradas, badge de não
  lidas) e thread da conversa selecionada (`?conversation=`); abrir marca como lida.
- `send`: envia pela conexão (`EvolutionManager::sendText`, captura `key.id`) e registra a saída.
- `assign`: atribui/desatribui a si; `toggleStatus`: encerra/reabre.
Página `Inbox/Index.tsx` (balões in/out, responder, **polling a cada 5s** via `router.reload`). Menu
"Atendimento" (`MessagesSquare`, permissão `inbox:use`). Escopo Fase 2: quem tem `inbox:use` vê todas
as conversas do tenant (macro) com filtro por condomínio; o escopo por **setor** vem na Fase 3.

## Deploy

`migrate --force` (wa_conversations + wa_messages) + `db:seed --force` (permissão `inbox:use`) +
`optimize:clear`. No super admin, preencher a **URL do webhook** (`https://app…/api/webhooks/evolution`)
para a Evolution entregar os eventos. As instâncias já criadas precisam do webhook configurado — novas
conexões já são criadas com ele quando o webhook está setado.

## Próximas fases

3) Setores + chatbot (menu de condomínio obrigatório no multi + roteamento + fora de expediente);
4) mídia (StorageService) + respostas prontas; 5) tempo real (Reverb) + relatórios; (paralelo) disparo
em massa. Hardening pendente: autenticação/segredo no webhook da Evolution.
