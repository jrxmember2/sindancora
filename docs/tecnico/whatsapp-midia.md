# WhatsApp — Mídia & Respostas prontas (Fase 4 da iniciativa)

Quarta fatia: a inbox passa a **receber e enviar mídia** (imagens, vídeos, áudios, documentos) e os
atendentes ganham **respostas prontas** (mensagens canned) para responder com um clique.

## Armazenamento de mídia

Reusa o `StorageService`/`StorageObject` existentes. Como a mídia não vem como `UploadedFile` (webhook)
nem precisa da validação de MIME dos documentos, foi adicionado:

- **`StorageService::storeRaw(...)`**: grava bytes crus, respeita a **cota do tenant**
  (`StorageQuotaException`) e um **limite de tamanho** opcional (`maxBytes`). Cria o `StorageObject`
  (`entity_type = wa_media`, `entity_id = id da conversa`) e incrementa o contador `storage_mb`.
- Limite configurável: `config('services.evolution.media_max_mb')` (env `WHATSAPP_MEDIA_MAX_MB`,
  padrão **16 MB**) — aplicado no recebimento e no envio.

## Recebimento (webhook)

`EvolutionWebhookController::extractMedia` detecta `imageMessage|videoMessage|audioMessage|`
`documentMessage|stickerMessage` (e desembrulha `documentWithCaptionMessage`). O conteúdo (base64) é
lido do payload (webhook criado com `base64: true`) e, em falta, buscado via
**`EvolutionManager::fetchMediaBase64`** (`/chat/getBase64FromMediaMessage`). A `caption` vira o corpo
da mensagem. O `WaInboxService::ingestMessage` recebe um parâmetro `media` e, após criar a conversa,
armazena via `storeRaw` e grava `media_type` + `storage_object_id` na mensagem. **Estourar a cota não
derruba o webhook** — a mensagem fica registrada sem o arquivo (apenas o tipo).

## Envio (atendente)

`EvolutionManager::sendMedia` (`/message/sendMedia`). `Panel\InboxController::sendMedia`: valida o
arquivo (limite = `media_max_mb`), **armazena primeiro** (`storeRaw`; `StorageQuotaException → 402`),
envia e registra a saída (`recordOutbound` com `media_type`+`storage_object_id`). Se o envio falhar, o
objeto é apagado imediatamente (desfaz). O texto da caixa de resposta vira a `caption`.

## Exibição / download

Cada mensagem expõe `media = { type, name, mime, is_image, url }`. A `url` aponta para
**`GET inbox/midia/{object}`** (`inbox.media`), que valida tenant + **escopo por setor** (mesma regra
da conversa) e redireciona para a **URL assinada** (10 min) do disco, com fallback de streaming. No
front, imagens e vídeos/áudios são exibidos inline; documentos viram um chip de download. Mídia
removida/expirada aparece como "indisponível".

## Respostas prontas

Tabela **`wa_quick_replies`** (tenant_id, `sector_id` nullable, title, shortcut, body, sort_order).
`sector_id = null` → disponível em **todos** os setores; preenchido → só naquele setor.

- Gestão: `Panel\QuickReplyController` (CRUD) em `/respostas-rapidas` (`quick-replies.*`, permissão
  `sectors:manage`), página `Settings/QuickReplies.tsx`, menu "Respostas rápidas"
  (`MessageSquareText`).
- Uso: a inbox recebe as respostas que o usuário pode ver (globais + as dos seus setores; gestor vê
  todas) e o atendente insere o texto na caixa de resposta pelo botão ⚡.

## Dados (migration `2026_06_12_000001_create_wa_media_quick_replies_tables`)

- `wa_messages` += `media_type` (image|video|audio|document|sticker) + `storage_object_id`
  (FK storage_objects nullOnDelete).
- `wa_quick_replies` (ver acima). Model `WaQuickReply`; `WaMessage` ganhou `storageObject()`/`hasMedia()`.

## Deploy

`migrate --force` (colunas de mídia + wa_quick_replies) + `optimize:clear`. **Sem db:seed novo**
(nenhuma permissão nova — usa `inbox:use` e `sectors:manage`). Opcional: definir
`WHATSAPP_MEDIA_MAX_MB`. Atenção ao **consumo de storage** (mídia de WhatsApp cresce rápido) — o
limite por plano e os add-ons de armazenamento já se aplicam; backlog: drive externo (Google Drive).

## Teste

1. Mandar uma **imagem/PDF/áudio** do WhatsApp para o número → aparece na thread (inline/chip) e conta
   no storage do tenant.
2. Na inbox, **anexar** um arquivo (clipe) com legenda → o morador recebe; aparece como saída.
3. Cadastrar **respostas prontas** (uma global, uma de setor) → na inbox, botão ⚡ insere o texto;
   atendente de outro setor não vê a resposta específica.
4. Conferir o download via URL assinada e o bloqueio por escopo de setor.

## Próximas fases

5) tempo real (Laravel Reverb) + relatórios; (paralelo) disparo em massa. Hardening pendente:
autenticação/segredo no webhook da Evolution; expurgo de mídia antiga; drive externo (Google Drive).
