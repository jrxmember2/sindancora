# Tempo real (Laravel Reverb) & Relatórios de atendimento (Fase 5 da iniciativa)

Quinta fatia: a inbox e o sino de notificações deixam de depender de polling e passam a atualizar
**em tempo real** via WebSocket (Laravel Reverb, auto-hospedado). Inclui também os **relatórios
operacionais** do atendimento.

## Stack de tempo real

- **Servidor:** `laravel/reverb` (`composer require laravel/reverb`) — servidor WebSocket
  pusher-compatível, rodando como processo próprio (`php artisan reverb:start`).
- **Broadcaster:** conexão `reverb` em `config/broadcasting.php` (default vem de
  `BROADCAST_CONNECTION`). Eventos `ShouldBroadcast` são enfileirados (fila `default`, já coberta pelo
  worker do supervisor) e publicados no Reverb.
- **Cliente:** `laravel-echo` + `pusher-js` (`resources/js/bootstrap.ts`). Só inicializa quando
  `VITE_REVERB_APP_KEY` está definido — sem isso, inbox/sino continuam por polling/refresh.
- **Canais** (`routes/channels.php`, registrado em `bootstrap/app.php` via `withRouting(channels:)`):
  - `App.Models.User.{id}` — notificações do usuário (corrigido p/ comparar **UUID como string**).
  - `tenant.{tenantId}.inbox` — privado; autoriza quem pertence ao tenant e tem `inbox:use`.

## Eventos / integração

- **Inbox:** `App\Events\WaConversationUpdated` (`ShouldBroadcast`, evento `.conversation.updated`,
  canal `tenant.{id}.inbox`) é disparado em `WaInboxService::ingestMessage` (recebida) e
  `recordOutbound` (enviada pelo atendente ou bot). Carrega só ids; o front recarrega do servidor.
  `Inbox/Index.tsx` assina o canal e faz `router.reload(['conversations','selected'])`; mantém um
  **poll de fallback** (30s com Reverb, 5s sem).
- **Sino:** as notificações ganharam o canal `broadcast` (trait `BroadcastsNotification` +
  `'broadcast'` no `via()` de AnnouncementPublished, ChargeOverdue, OccurrenceUpdated,
  ReservationUpdated, VisitorArrived). O `AppLayout` assina `App.Models.User.{id}` via
  `.notification()` e recarrega o prop `notifications`.

## Relatórios de atendimento

`Panel\WhatsappReportController` em `/inbox/relatorios` (`inbox.reports`, permissão `sectors:manage`),
página `Inbox/Reports.tsx`, menu "Relatório de atendimento". Filtro por período (padrão últimos 30
dias). Métricas: conversas no período, abertas agora, encerradas no período, mensagens recebidas/
enviadas (com quebra atendente vs bot), **tempo médio de 1ª resposta** (entre a 1ª recebida e a 1ª
resposta humana), conversas **por setor** e **por condomínio**, e **ranking de atendentes**.

## Variáveis de ambiente (produção)

No backend (EasyPanel → env do app):
```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...           # gerar (php artisan reverb:install gera no .env local)
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST="app.seudominio.com"   # host público (sem esquema)
REVERB_PORT=443
REVERB_SCHEME=https
# servidor interno (o supervisor sobe em 0.0.0.0:8080):
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```
No build do frontend (precisam existir **no momento do build**, pois o Vite as embute):
```
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

## Infra (Docker/EasyPanel)

- **supervisor** (`docker/supervisor/supervisord.conf`): novo programa `reverb`
  (`artisan reverb:start --host=0.0.0.0 --port=8080`).
- **nginx** (`docker/nginx/default.conf`): `location /app` faz proxy WebSocket para
  `127.0.0.1:8080` com headers de `Upgrade/Connection` (o pusher-js conecta em
  `wss://host/app/{key}`). Sem porta extra exposta — tudo pela 443 do app.
- A fila precisa estar ativa (worker já existe) para entregar os eventos/broadcasts.

## Deploy

`optimize:clear` (sem migration nova). Definir as env acima e **rebuildar o front** com as `VITE_*`.
Subir o container: o supervisor passa a rodar o Reverb; o nginx já roteia `/app`. Conferir no
navegador (Network → WS) a conexão `wss://host/app/{key}` em `101 Switching Protocols`. Sem as env,
o sistema degrada para polling automaticamente.

## Teste

1. Abrir a inbox em duas abas/usuários; receber/enviar uma mensagem → aparece nas duas **sem
   refresh** (poll de 30s é só fallback).
2. Disparar uma notificação (publicar comunicado, registrar visitante) → o sino do destinatário
   atualiza na hora.
3. `/inbox/relatorios` → conferir KPIs, quebras por setor/condomínio e ranking; mudar o período.

## Próximas fases

(paralelo) Comunicado em massa por WhatsApp com fila/throttle. Hardening pendente: auth/segredo no
webhook da Evolution; expurgo de mídia antiga; drive externo (Google Drive); escalonamento do Reverb
(REVERB_SCALING_ENABLED + Redis) se houver múltiplas instâncias do app.
