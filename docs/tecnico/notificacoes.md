# Base de Notificações (Fase 3.5)

Infraestrutura transversal de notificações **in-app** para os usuários do painel, reutilizável
por todos os módulos operacionais. Complementa o e-mail aos moradores (ver `comunicados.md`).

## Modelo de dados

Tabela padrão do Laravel `notifications` (migration `2026_06_01_000002_create_notifications_table`),
com uma diferença importante: `uuidMorphs('notifiable')` em vez de `morphs`, porque os models
(`User`) usam **PK UUID**. Sem isso o `notifiable_id` seria `bigint` e não casaria com o id do usuário.

Não há `tenant_id`: as notificações são sempre consultadas por usuário (`$user->notifications()`),
e cada usuário pertence a um único tenant — o isolamento é inerente.

## Como notificar

1. Criar uma classe em `App\Notifications\*` estendendo `Illuminate\Notifications\Notification`,
   com `via()` retornando `['database']` e `toArray()` no formato padronizado:
   ```php
   ['title' => '...', 'message' => '...', 'url' => route(...), 'icon' => 'megaphone']
   ```
   O front renderiza qualquer notificação por essas chaves (mapeia `icon` → ícone lucide).
2. Disparar com `Notification::send($users, new MinhaNotificacao(...))`.

Exemplo já em uso: `App\Notifications\AnnouncementPublished`, disparada por
`AnnouncementService::notifyPanelUsers()` ao publicar um comunicado, para todos os usuários
**ativos** do tenant (`where('tenant_id', X)->where('status','active')` — exclui super admins de
tenant_id null, necessário no contexto do comando agendado sem tenant resolvido).

## Exposição no front

- `HandleInertiaRequests` compartilha em toda requisição autenticada:
  `notifications.unread_count` e `notifications.recent` (8 mais recentes), via closures.
- `Layouts/AppLayout.tsx` — sino na topbar com badge de não lidas e dropdown das recentes;
  clicar numa notificação faz `POST /notificacoes/{id}/lida` (marca lida + redireciona à `url`).
- `Pages/Notifications/Index.tsx` (`/notificacoes`) — listagem paginada + "marcar todas como lidas".
- Tipos em `resources/js/types/index.d.ts`: `AppNotification`, `SharedNotifications`.

## Rotas

`GET /notificacoes`, `POST /notificacoes/marcar-todas`, `POST /notificacoes/{id}/lida`
— sem middleware de permissão (qualquer usuário autenticado vê as suas).

## Deploy

Só `php artisan migrate --force`. Não exige seed.

## Pendências

Templates de e-mail por tipo de evento, preferências de e-mail por usuário, e notificar
moradores in-app (depende do portal — Fase 4). Ver `docs/produto/02-roadmap-mvp.md` §3.5.
