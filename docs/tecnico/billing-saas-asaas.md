# Billing SaaS + Asaas (plataforma → tenant)

> Implementado em 12/06/2026. Ciclo comercial automatizado do Sindâncora: site público → checkout
> Asaas → pagamento compensado → provisionamento automático do tenant → primeiro acesso → NFS-e →
> régua de cobrança → bloqueio por inadimplência → desbloqueio (manual e por confiança).

## Visão geral

Esta é a camada de **billing SaaS**: o Sindâncora cobrando os tenants, usando uma conta Asaas
**única da plataforma**. É **distinta** da integração já existente em `App\Services\Payments\*`
(tenant cobrando moradores, via `TenantPaymentSetting`). As duas convivem com webhooks separados:

| Camada | Conta Asaas | Config | Webhook |
| --- | --- | --- | --- |
| Tenant → morador (existente) | por tenant (`tenant_payment_settings`) | no painel do tenant | `POST /api/webhooks/asaas` |
| Plataforma → tenant (este doc) | única da plataforma | `config/services.asaas_billing` (env) | `POST /api/webhooks/asaas/saas` |

## Variáveis de ambiente

```env
# Conta Asaas única da plataforma (billing SaaS). NUNCA commitar a chave.
ASAAS_BILLING_ENABLED=true
ASAAS_BILLING_ENV=sandbox            # sandbox | production
ASAAS_API_KEY=                       # chave da conta Asaas da plataforma
ASAAS_WEBHOOK_TOKEN=                 # segredo do header asaas-access-token do webhook
# URLs já existem como defaults; sobreponha só se necessário:
# ASAAS_BASE_URL=                    # força a base URL (ignora sandbox/production)
# ASAAS_SANDBOX_URL=https://sandbox.asaas.com/api/v3
# ASAAS_PRODUCTION_URL=https://api.asaas.com/v3
```

Em desenvolvimento, aponte para o **sandbox** (`ASAAS_BILLING_ENV=sandbox`). O fluxo inteiro funciona
no sandbox do Asaas.

## Configuração do webhook no painel do Asaas

1. Painel do Asaas → Integrações → Webhooks.
2. URL: `https://SEU_DOMINIO/api/webhooks/asaas/saas`.
3. Token de autenticação: o mesmo valor de `ASAAS_WEBHOOK_TOKEN` (enviado no header `asaas-access-token`).
4. Eventos: pagamentos (`PAYMENT_*`) e assinaturas.
5. A URL protegida aparece também em **Super Admin → Financeiro → Configurações**.

O webhook responde rápido (200/`queued`), persiste o evento bruto em `payment_events` e processa em
fila (`ProcessAsaasBillingWebhook`). **Idempotência** garantida por `payment_events.asaas_event_id`
único; o job é re-tentável e só roda uma vez por evento.

## Fluxo

1. **Vitrine** `GET /planos` → **checkout** `POST /checkout` cria `customer` + `subscription` no Asaas
   e grava um `pending_signups` (nenhum tenant ainda). Tela de pagamento em `/checkout/{id}/pendente`
   (PIX/QR, link da fatura ou boleto), com polling em `/checkout/{id}/status`.
2. Na **1ª cobrança compensada** (`PAYMENT_CONFIRMED`/`RECEIVED`), o webhook dispara
   `ProvisionTenantFromSignup` → `ProvisioningService` reusa `TenantService::create()` (mesma lógica do
   super admin), cria a `billing_subscriptions`, gera o **primeiro acesso** (link mágico assinado +
   senha temporária de fallback) e envia o e-mail de boas-vindas. Idempotente e com retry; se esgotar,
   alerta os super admins (`TenantProvisioningFailed`).
3. Cobranças seguintes da assinatura são espelhadas em `billing_payments` e conciliadas pelo webhook;
   `billing:reconcile` (diário) corrige divergências consultando o Asaas (fonte da verdade).

## Régua de cobrança e bloqueio (`billing:run-dunning`, diário 06:30)

Prazos **configuráveis** em `billing_settings` (Super Admin → Financeiro → Configurações). Defaults:

| Estágio | Quando | Ação |
| --- | --- | --- |
| Lembrete | D-3 | e-mail |
| Aviso 1 | D+3 | e-mail |
| Aviso 2 | D+7 | e-mail (alerta de bloqueio) |
| Último aviso | D+12 | e-mail |
| Bloqueio | D+15 | `suspended` (tenant bloqueado) |

Tenant `suspended` cai na tela `/assinatura-em-atraso` (acessível mesmo bloqueado — ver
`ResolveTenant`), com o link da fatura. Pagou → reativa automaticamente. App/API recebem
`402 TENANT_SUSPENDED`.

## Desbloqueios

- **Manual** (super admin): motivo obrigatório + prazo (data ou "até o próximo vencimento") →
  `grace_manual`. Expirou sem pagar → `suspended` (no job diário). Auditado na linha do tempo.
- **Por confiança** (automático, em D+15): se elegível (≥ N meses de cliente, histórico 100% em dia
  dentro da tolerância, sem cortesia nos últimos Z meses) → `grace_trust` por Y dias + e-mail. Tudo
  configurável; pode ser desabilitado globalmente e a carência revogada.

## NFS-e (Asaas `/invoices`)

Habilite e configure em **Super Admin → Financeiro → Configurações** (descrição do serviço, código de
serviço municipal, ISS, deduções, observações, envio do PDF/XML). Ao confirmar o pagamento, a NFS-e é
agendada (`scheduleNfse`); o vínculo `payment ↔ invoice` e o status (agendada/emitida/erro) aparecem
na listagem de pagamentos e no detalhe da assinatura. Erros de emissão (ex.: config municipal
pendente) aparecem como alerta no dashboard.

> A emissão depende do **emissor habilitado/configurado na conta Asaas** (dados da empresa e
> prefeitura). Sem isso, o agendamento registra erro.

## Arquivos-chave

- Migrations: `2026_07_10_000001_create_billing_tables.php`, `2026_07_10_000002_create_billing_settings_table.php`
- Models: `PendingSignup`, `BillingSubscription`, `BillingPayment`, `PaymentEvent`, `BillingTimelineEntry`, `BillingSetting`
- Serviços: `App\Services\Billing\{AsaasBillingClient, BillingService, DunningService, ProvisioningService}`
- Jobs: `ProcessAsaasBillingWebhook`, `ProvisionTenantFromSignup`
- Comandos: `billing:run-dunning`, `billing:reconcile` (em `routes/console.php`)
- Mail: `App\Mail\Billing\{TenantWelcomeMail, BillingDunningMail}` (+ views markdown em `resources/views/mail/billing`)
- Controllers: `Api\Billing\AsaasWebhookController`, `Public\CheckoutController`, `FirstAccessController`, `BillingBlockController`, `Admin\Billing\{BillingDashboardController, SubscriptionController, PaymentController, SettingController}`
- Frontend: `resources/js/Pages/Public/{Plans,CheckoutPending}.tsx`, `Pages/Billing/Suspended.tsx`, `Pages/Admin/Billing/*`
- Middleware: `ResolveTenant` (bypass do site público + tela de bloqueio do suspenso)
- Rotas: `routes/web.php` (público + bloqueio), `routes/api.php` (webhook), `routes/admin.php` (`admin.billing.*`)

## Deploy (Easypanel)

```bash
php artisan migrate --force        # cria billing_* e billing_settings (com defaults)
php artisan optimize:clear
```

Setar as `ASAAS_*` no ambiente e registrar o webhook no painel do Asaas. Garantir o scheduler
(`schedule:run`/`schedule:work`) — `billing:run-dunning` e `billing:reconcile` já estão agendados.
Sem novo seed.

## Testes

`tests/Feature/Billing/*` cobre: idempotência do webhook, gatilho de provisionamento, régua até D+15,
desbloqueio por confiança, expiração da carência manual e agendamento da NFS-e. A base
(`BillingTestCase`) monta só o schema de billing no SQLite (as 94 migrations usam recursos do
Postgres). Rodar: `php artisan test --filter=Billing`.
