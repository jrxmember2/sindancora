# WhatsApp — Disparo em massa (Fase 6 da iniciativa)

Sexta fatia: **campanhas** de envio em massa para moradores, segmentadas por condomínio/bloco/unidade,
com **throttle anti-ban**, **opt-out (LGPD)** e mídia opcional. Decisões do usuário: alvo por
condomínio + segmentação por bloco/unidade; envios **fire-and-forget** (não registram conversa na
inbox); **opt-out automático**.

## Modelo

Migration `2026_06_13_000001_create_wa_campaign_tables`:
- `wa_campaigns`: connection_id, condominium_id, name, body, media_storage_object_id (opcional),
  `target_type` (all|blocks|units) + `block_ids`/`unit_ids` (json), `throttle_seconds`, status
  (draft|scheduled|sending|completed|cancelled), scheduled_at, contadores
  (total/sent/failed/skipped), started_at/completed_at.
- `wa_campaign_recipients`: snapshot do telefone no momento da montagem (person_id, name, phone,
  status pending|sent|failed|skipped, wa_message_id, error, sent_at).
- `wa_opt_outs`: telefone descadastrado por tenant (único tenant_id+phone).

Models `WaCampaign`/`WaCampaignRecipient`/`WaOptOut` (`WaOptOut::normalizePhone` = dígitos com DDI,
prefixa 55 quando BR sem DDI).

## Montagem da audiência (`WaCampaignService`)

`collect()` junta `person_unit_links` ativos → `units` (do condomínio, filtrando por bloco/unidade
conforme `target_type`) → `persons` (do tenant, com telefone), **normaliza + deduplica por telefone**
e **remove os opt-outs**. `previewCount()` alimenta a prévia do formulário; `buildRecipients()`
congela os destinatários (bulk insert) e grava `total_recipients`.

## Envio com throttle

`start()` marca `sending` e enfileira **um `SendCampaignMessage` por destinatário** com `delay`
incremental = índice × `throttle_seconds` + jitter aleatório (0–3s) — espalha os envios (anti-ban).
O job reconfere **opt-out** e **conexão conectada** no momento do envio, manda texto (ou mídia com
legenda quando há anexo) via `EvolutionManager`, atualiza o destinatário e os contadores; ao zerar os
pendentes, marca a campanha `completed`. Campanha `cancelled` faz os jobs restantes pularem.

Agendamento: campanhas com `scheduled_at` ficam `scheduled` e são iniciadas pelo comando
`campaigns:dispatch-scheduled` (scheduler, a cada minuto), igual aos comunicados agendados.

## Opt-out (LGPD / anti-ban)

- **Automático:** `WaInboxService` registra opt-out quando o contato envia `SAIR|PARAR|CANCELAR|`
  `DESCADASTRAR|STOP` (mensagem recebida).
- **Manual:** página de descadastros (adicionar/remover telefone).
- Os disparos sempre ignoram quem está na lista (na montagem e novamente no envio).

## Telas (painel) — permissão `campaigns:manage` (admin + síndico)

`/disparos` (`campaigns.*`): lista (`Campaigns/Index`), criação (`Campaigns/Create` — segmentação
dinâmica via `GET disparos/condominio/{id}/alvos`, prévia via `POST disparos/previa`, anexo, throttle,
agendamento), detalhe/progresso (`Campaigns/Show` — iniciar/cancelar/excluir, tabela de
destinatários, poll enquanto envia) e descadastros (`Campaigns/OptOuts`). Menu "Disparos" (Send).

## Licenciamento

Mantido o licenciamento **por conexão** (Fase 1). Volume de mensagens **não** é medido/cobrado nesta
fase (decisão do usuário).

## Deploy

`migrate --force` (wa_campaigns + wa_campaign_recipients + wa_opt_outs) + `db:seed --force`
(permissão `campaigns:manage`) + `optimize:clear`. Worker de fila ativo (envios em fila) e scheduler
rodando (campanhas agendadas). Sem env nova.

## Teste

1. Criar campanha → escolher conexão/condomínio → segmentar (todos/bloco/unidade) → "Calcular
   destinatários" → escrever a mensagem (+anexo opcional) → criar.
2. Em /disparos/{id} → "Iniciar disparo" → acompanhar o progresso (enviadas/falhas/puladas).
3. Responder "SAIR" de um número → conferir que entra em Descadastros e é pulado num novo disparo.
4. Agendar uma campanha (scheduled_at futuro) → conferir que o scheduler inicia sozinha.

## Riscos / próximos

Número não-oficial (Baileys via Evolution) tem **risco de bloqueio** em disparo de volume — para
escala, considerar a **WhatsApp Cloud API** oficial (templates aprovados). Backlog: medir/licenciar
por mensagens; relatórios de entrega; janelas de envio (horário comercial); confirmação de leitura.
Com isto, a iniciativa de WhatsApp (Fases 1–6) está completa.
