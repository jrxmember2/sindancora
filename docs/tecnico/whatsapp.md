# WhatsApp (Evolution API) — Fase 6.3

> Status: implementado como **canal de notificação** adicional. As notificações que já existem
> passam a sair também por WhatsApp quando o tenant tem a integração ligada (degradação graciosa:
> sem config ou sem telefone, não envia e nada quebra).

## Arquitetura

`App\Notifications\Channels\WhatsAppChannel` é um canal custom do Laravel, incluído no `via()` das
notificações. Para cada notificação que implementa `toWhatsapp($notifiable)`, o canal:
1. resolve o número via `$notifiable->routeNotificationForWhatsapp()` (telefone da Pessoa ou do
   usuário, só dígitos, com DDI 55 quando BR sem código de país);
2. resolve a config do tenant (`tenant_whatsapp_settings`) pelo `tenant_id` do notifiable;
3. se `isUsable()`, envia via `App\Services\Whatsapp\WhatsAppClient` (Evolution
   `POST /message/sendText/{instance}`, header `apikey`).

As notificações `AnnouncementPublished`, `OccurrenceUpdated`, `ReservationUpdated` e `ChargeOverdue`
ganharam `toWhatsapp()` e o canal no `via()`. As três primeiras passaram a `ShouldQueue` (o envio
WhatsApp é HTTP, então roda em fila).

## Configuração (por tenant)

Tabela `tenant_whatsapp_settings` (model `TenantWhatsappSetting`): `base_url`, `instance`,
`api_key` (**cast `encrypted`**), `enabled`. `Tenant::whatsappSetting()` (hasOne).

Tela `/configuracoes/whatsapp` (`Panel\WhatsappSettingController`, permissão **`settings:whatsapp`**
— admin já tem): URL da Evolution, instância, chave (write-only/mascarada), ativar, **testar
conexão** (`GET /instance/connectionState/{instance}`). Menu: item "WhatsApp". Tela
`Settings/Whatsapp.tsx`.

## Pré-requisito

Uma instância da Evolution API no ar (URL + instância + API key). Enquanto não configurada/ativa, o
canal não envia. O telefone vem de `Person.phone` (morador) ou `User.phone`.

## Deploy

Migration nova `2026_06_05_000001_create_tenant_whatsapp_settings_table` → `migrate --force`.
`db:seed --force` aplica a permissão `settings:whatsapp`. `optimize:clear`. Worker de fila ativo.

## Fora de escopo

2ª via de boleto por WhatsApp sob demanda (o `ChargeOverdue` já avisa vencimento); templates por
tenant; mídia/anexos; recebimento de mensagens.
