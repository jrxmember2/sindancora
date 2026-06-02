# Webhooks de saída (Fase 6.2)

> Status: implementado. O tenant cadastra URLs que recebem **POST assinado** a cada evento do
> condomínio. Sem migration nova — tabelas `webhooks`/`webhook_deliveries` já existiam (Fase 1).

## Como funciona

Quando algo acontece (cobrança paga, comunicado publicado, ocorrência aberta…), o
`App\Services\WebhookService::dispatch(tenantId, evento, dados)` localiza os webhooks **ativos** do
tenant que **assinam** aquele evento e enfileira um `App\Jobs\DeliverWebhook` por destino.

Cada entrega: `POST` com corpo JSON `{ event, created_at, data }`, headers:
- `X-SindAncora-Event`: nome do evento
- `X-SindAncora-Signature`: `sha256=<hmac>` — HMAC-SHA256 do corpo usando o `secret` do webhook

O destino valida a assinatura recalculando o HMAC com o segredo (exibido/copiável no painel).

## Retry

`DeliverWebhook` tem `tries=4` e backoff `[60, 300, 900]s`. Cada tentativa grava uma linha em
`webhook_deliveries` (status, corpo, duração, nº de tentativas, `next_retry_at`, `delivered_at`,
`failed_at`). 2xx = entregue; demais = re-tenta até esgotar (`failed_at`).

## Eventos (catálogo)

`App\Models\Webhook::EVENTS`:

| Evento | Disparado em |
|---|---|
| `charge.created` | criação de cobrança (model event) |
| `charge.paid` | `ChargeService::registerPayment` (manual + conciliação Asaas) |
| `charge.overdue` | comando `charges:mark-overdue` |
| `announcement.published` | `AnnouncementService::publish` |
| `occurrence.created` | criação de ocorrência (model event) |
| `occurrence.status_changed` | `OccurrenceService::changeStatus` |
| `reservation.created` | `ReservationService::request` |
| `reservation.approved` | `ReservationService::request` (auto) / `approve` |
| `reservation.rejected` | `ReservationService::reject` |

Payload `data`: representação compacta via `Model::toWebhookArray()` (Charge/Occurrence/Reservation)
ou array inline (Announcement).

## Gestão (painel)

`/configuracoes/webhooks` (`Panel\WebhookController`, permissão **`webhooks:manage`** — admin já tem).
CRUD de webhooks (URL, eventos, ativo), copiar o segredo, **enviar teste** (evento `ping`) e ver as
**50 últimas entregas**. Menu: item "Webhooks". Tela `Settings/Webhooks.tsx`.

## Segurança

Segredo por webhook (`secret`, hidden no model). Assinatura HMAC por entrega. O `WebhookService`
consulta os webhooks por `tenant_id` ignorando o escopo global (seguro em fila/console).

## Deploy

**Sem migration nova.** `optimize:clear`. Garantir o worker de fila rodando (o supervisor já roda).

## Fora de escopo

Reenvio manual de uma entrega específica pela UI; assinatura por timestamp; mais eventos.
