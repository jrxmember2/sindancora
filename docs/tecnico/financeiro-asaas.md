# Financeiro — Integração Asaas (Fase 5.4)

> Status: implementado. Cada tenant conecta a própria conta Asaas e passa a gerar **boleto + PIX**
> (fatura única, `billingType: UNDEFINED`) por cobrança, enviar 2ª via por e-mail e **conciliar o
> pagamento automaticamente via webhook**. O fluxo manual (registro de pagamento, comprovante)
> continua disponível como fallback quando a integração está desligada.

## Configuração por tenant

- Tabela `tenant_payment_settings` (model `App\Models\TenantPaymentSetting`): `provider` (`asaas`),
  `environment` (`sandbox`/`production`), `api_key` (**cast `encrypted`**), `wallet_id`,
  `webhook_token`, `billing_type` (`UNDEFINED`), `enabled`. Único por `(tenant_id, provider)`.
  `Tenant::paymentSetting()` (hasOne, provider asaas). Helpers: `isUsable()`, `baseUrl()`.
- URLs base em `config/services.php` → `asaas.sandbox` / `asaas.production` (env
  `ASAAS_SANDBOX_URL` / `ASAAS_PRODUCTION_URL`).
- Tela `Settings/Payments.tsx` em `/configuracoes/pagamentos` (`Panel\PaymentSettingController`),
  sob a permissão **`settings:payments`** (admin por padrão; síndico não tem). Permite salvar a
  chave (write-only, mascarada), escolher ambiente, ligar/desligar, **testar conexão**
  (`GET /myAccount`) e copiar a **URL do webhook** + **token** (gerado/rotacionável).

## Camada de integração (`app/Services/Payments/`)

- `AsaasClient` — cliente HTTP fino amarrado a um `TenantPaymentSetting`. Header `access_token`,
  `baseUrl()` por ambiente. Métodos: `createCustomer`, `createPayment`, `getPayment`,
  `getPixQrCode`, `getIdentificationField` (linha digitável), `myAccount`. Lança `AsaasException`
  (com a mensagem de erro do Asaas) em resposta não-2xx.
- `AsaasService` — domínio:
  - `settingFor(Tenant)`: configuração utilizável (ligada + com chave) ou null.
  - `ensureCustomer(Person, setting)`: cria o cliente no Asaas e grava `persons.gateway_customer_id`.
  - `issueCharge(Charge)`: idempotente — se já há `gateway_payment_id`, ressincroniza; senão cria o
    pagamento (`billingType UNDEFINED`, `value`=amount, `dueDate`, `externalReference`=charge.id,
    `fine`/`interest` derivados de `fine_rate`/`interest_rate`). Depois busca PIX + linha digitável e
    grava `gateway_*`, `pix_*`, `bank_slip_*`, `gateway_synced_at` na cobrança.
  - `reconcile(payload)`: mapeia o evento do webhook → `PAYMENT_CONFIRMED/RECEIVED` reusa
    `ChargeService::registerPayment` (idempotente: ignora se já paga); `PAYMENT_OVERDUE` → overdue;
    `PAYMENT_DELETED/REFUNDED` → cancelled.
  - `sendSecondCopy(Charge)`: emite se necessário e enfileira `ChargeIssuedMail` ao morador.

## Emissão (painel) e portal

- `Panel\ChargeController`: `issueGateway` (POST `cobrancas/{charge}/boleto`, `charges:update`) e
  `secondCopy` (POST `cobrancas/{charge}/segunda-via`). `show()` envia `gatewayEnabled` + campos de
  gateway. `Charges/Show.tsx`: botão "Gerar/Atualizar boleto/PIX", card com QR Code, PIX
  copia-e-cola, linha digitável, links de fatura/boleto e "Enviar 2ª via".
- `Portal\ChargeController`: `secondCopy` (POST `portal.charges.second-copy`) + `gatewayEnabled` no
  `show`. `Portal/Charges/Show.tsx`: QR/PIX/boleto + "Receber 2ª via por e-mail".
- **Emissão em lote**: `generateConfirm` aceita `issue_gateway`; quando marcado (checkbox no
  `Generate.tsx`, só visível com gateway ligado) dispara `App\Jobs\IssueGatewayCharge` (queued) por
  cobrança criada — uma chamada ao Asaas por cobrança, fora do request. `generateBatch` retorna as
  cobranças criadas.

## Consistência valor/cancelamento

- **Edição travada**: com boleto/PIX já emitido (`hasGatewayCharge()`), `update()` rejeita alteração
  de `amount`/`due_date` (422); o `Charges/Edit.tsx` desabilita esses campos. Evita divergência com
  o valor registrado no Asaas. Para mudar, cancele e gere uma nova cobrança.
- **Cancelamento**: `destroy()` chama `AsaasService::cancelCharge` (→ `DELETE /payments/{id}`) antes
  de cancelar/soft-deletar localmente, para o boleto deixar de ser pagável. Falha do gateway só
  registra aviso (não bloqueia o cancelamento local).
- **Pagamento em corrida**: `resolveCharge` (webhook e service) usa `withTrashed()`, então um
  pagamento que chegue para uma cobrança já cancelada/soft-deletada ainda é conciliado.

## Webhook (conciliação automática)

- Rota pública `POST /api/webhooks/asaas` (`Api\WebhookController@asaas`) — **fora do escopo de
  tenant por host**: `ResolveTenant` ignora `api/webhooks/*`.
- O controller resolve o `Charge` pelo `payment.externalReference` (= charge.id), **sem o global
  scope de tenant** (`Charge::withoutGlobalScope('tenant')`), deriva o tenant e valida o header
  `asaas-access-token` contra `tenant_payment_settings.webhook_token` (401 se inválido). Responde
  **200** quando processado; o Asaas re-tenta em não-2xx. Idempotente (evento repetido não duplica
  pagamento).
- No painel Asaas → Integrações → Webhooks: cadastrar a URL exibida na tela e informar o token como
  "Token de autenticação".

## Segurança / multitenancy

- `api_key` nunca trafega para o front (campo write-only; o controller só envia `has_api_key`).
- Armazenamento criptografado (`encrypted` cast). Webhook autenticado por token por tenant
  (`hash_equals`).
- A cobrança só é localizada/conciliada pelo seu próprio `externalReference`/`gateway_payment_id`,
  garantindo o isolamento entre tenants mesmo numa URL de webhook compartilhada.

## Validação / deploy

- `php -l` nos arquivos novos; `route:list` (rotas `charges.issue`, `charges.second-copy`,
  `settings.payments.*`, `webhooks.asaas`, `portal.charges.second-copy`); `npm run build`.
- Deploy: `migrate --force` (cria `tenant_payment_settings`, colunas de gateway em `charges`,
  `persons.gateway_customer_id`) + `optimize:clear`; `db:seed --force` aplica `settings:payments`.
- Teste sandbox: salvar chave sandbox + gerar token → testar conexão → gerar boleto/PIX numa
  cobrança → simular `PAYMENT_CONFIRMED` no webhook → cobrança vira paga; reenviar evento confirma
  idempotência.

## Fora de escopo (adiado)

Split/repasse por wallet, assinaturas recorrentes nativas do Asaas, estornos parciais, bloqueio de
inadimplente em reservas.
