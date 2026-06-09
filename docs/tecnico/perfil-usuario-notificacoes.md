# Perfil de usuario e preferencias de notificacao

> Entregue em 09/06/2026 como D11 da nova onda operacional.

## Objetivo

A rota unica `/perfil` concentra ajustes pessoais do usuario para superadmin, painel e portal:

- dados basicos: nome, e-mail e telefone;
- troca de senha com validacao da senha atual;
- upload/remocao de foto do usuario;
- preferencias granulares de notificacao por evento e canal.

As rotas antigas do portal em `/portal/perfil` foram preservadas como redirect para `/perfil`.

## Dados persistidos

A migration `2026_06_29_000001_create_user_notification_preferences.php` adiciona ao usuario:

- `avatar_path`;
- `avatar_mime_type`;
- `avatar_original_filename`.

A foto e gravada no disk padrao do Laravel (`config('filesystems.default')`) em:

```text
avatars/{user_id}/{uuid}.{extensao}
```

Em deploy com disk local, esse arquivo depende do mesmo volume persistente usado para `storage/app`.

As preferencias ficam em `user_notification_preferences`, com unicidade por:

```text
user_id + event + channel
```

Quando nao existe linha para um evento/canal, o comportamento padrao e receber a notificacao.

## Eventos e canais

A matriz de eventos/canais fica centralizada em `App\Support\NotificationPreferenceRegistry`.
Ela define o que aparece na UI e quais canais podem ser ligados/desligados para cada evento.

Canais atuais:

- `database`: sino/in-app;
- `broadcast`: tempo real;
- `mail`: e-mail;
- `whatsapp`: canal `WhatsAppChannel`.

Eventos atuais:

- comunicados publicados;
- atualizacoes de ocorrencias;
- alertas de SLA de ocorrencias;
- reservas;
- visitante chegou;
- cobrancas vencidas;
- contas a pagar;
- documentos vencendo;
- manutencoes preventivas;
- ferias de funcionarios.

## Como as notificacoes respeitam a preferencia

As notificacoes usam o trait `App\Notifications\Concerns\RespectsNotificationPreferences`.
No metodo `via()`, a notificacao informa:

```php
return $this->preferredChannels($notifiable, 'event_key', ['database', 'mail', 'broadcast']);
```

O trait converte `WhatsAppChannel::class` para a chave `whatsapp`, consulta
`User::notificationChannelsFor()` e retorna apenas os canais habilitados.

Notificacoes ja integradas:

- `AnnouncementPublished`;
- `OccurrenceUpdated`;
- `OccurrenceSlaDue`;
- `ReservationUpdated`;
- `VisitorArrived`;
- `ChargeOverdue`;
- `ExpenseDue`;
- `DocumentExpiring`;
- `MaintenanceDue`;
- `EmployeeVacationDue`.

## UI

A pagina Inertia `resources/js/Pages/Profile/Edit.tsx` usa:

- `AdminLayout` para superadmin;
- `AppLayout` para usuarios do painel;
- `PortalLayout` para moradores.

Os menus dos layouts passam a mostrar o avatar quando existir e linkam para `/perfil`.

## Deploy

Rodar:

```bash
php artisan migrate --force
php artisan optimize:clear
```

Se `public/build` estiver versionado no ambiente, commitar tambem os assets gerados por `npm run build`.
