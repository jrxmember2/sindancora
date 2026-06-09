# Links públicos + QR por condomínio (X3)

> Implementado em 09/06/2026. Fecha o roadmap da Nova Onda (`docs/produto/06-roadmap-nova-onda.md`).

Booster de aquisição/onboarding: cada condomínio ganha um link público (com QR) que permite a
qualquer pessoa **se auto-cadastrar como morador** ou **abrir uma ocorrência** sem login. Todo
envio entra em uma **fila de moderação** e só vira Pessoa/vínculo ou Ocorrência depois que um
gestor aprova. Reaproveita portal, QR (`qrcode.react`), convite ao portal (`InvitationService`) e
o `OccurrenceService`.

## Módulo e permissões

- Módulo de plano: `public_links` — habilitado em **todos os planos** (inclusive Starter), por ser
  booster de aquisição.
- Permissões:
  - `public_links:read` — ver links e a fila de moderação;
  - `public_links:manage` — gerar/rotacionar token, ativar/desativar, habilitar ações e
    aprovar/reprovar envios.
- Papéis padrão: `admin`/`sindico` (read+manage), `subsindico` (read+manage), `conselheiro` (read).
- Registrado tanto via migration idempotente quanto nos seeders (`PermissionSeeder`, `RoleSeeder`,
  `PlanSeeder`) para instalações novas.

## Banco de dados

- `condominium_public_links` (1 linha por condomínio):
  - `token` (único, 16 chars), `active`, `allow_resident_signup`, `allow_occurrence`.
- `public_submissions` (fila de moderação):
  - `type` (`resident_signup` | `occurrence`), `status` (`pending` | `approved` | `rejected`);
  - contato denormalizado (`name`, `email`, `phone`, `document`) para busca rápida;
  - `payload` JSON com os dados completos (relação, unidade, título/descrição/categoria etc.);
  - trilha: `reviewed_by`, `reviewed_at`, `review_notes`;
  - referências geradas: `person_id`, `occurrence_id`; mais `ip_address`.

## Fluxo público (sem autenticação)

Rotas `public.intake.*` sob `/p/{token}` — o tenant é resolvido pelo domínio (`ResolveTenant`) e o
token é escopado ao tenant pelo global scope de `CondominiumPublicLink`. POSTs têm `throttle:10,1`.

- `GET /p/{token}` — landing com as ações habilitadas.
- `GET|POST /p/{token}/morador` — auto-cadastro de morador.
- `GET|POST /p/{token}/ocorrencia` — abertura de ocorrência.
- `GET /p/{token}/enviado` — confirmação.

Páginas Inertia usam o `PublicLayout` (branding do tenant via props compartilhadas). Links/ações
desabilitados ou inativos retornam 404.

## Fluxo de moderação (painel)

Rotas `public-links.*` sob `/links-publicos` (middleware `module:public_links`).

- `GET /links-publicos` — gestão por condomínio: gerar/rotacionar token, copiar URL, ver/QR,
  ativar/desativar e habilitar cada ação. Mostra contador de pendências.
- `GET /links-publicos/moderacao` — fila filtrável por status/tipo/condomínio.
- `GET /links-publicos/moderacao/{submission}` — detalhe + ações.
- `POST .../aprovar` e `POST .../reprovar`.

Aprovação (em `PublicSubmissionService`, transacional):

- **Auto-cadastro**: reaproveita a Pessoa pelo documento (ou cria), vincula à unidade com a relação
  escolhida (sem duplicar vínculo ativo) e, **opcionalmente**, envia o convite ao portal pelos
  canais escolhidos (e-mail/WhatsApp). O gestor pode ajustar unidade/relação antes de aprovar.
- **Ocorrência**: cria a Ocorrência aberta com o contato do solicitante anexado à descrição e o
  prazo de SLA calculado pela prioridade (`OccurrenceService::ensureDueAt`).

Reprovar apenas registra status/motivo/revisor.

## Escopo por condomínio

`ScopesCondominiumsByRole` (concern reutilizável): usuários tenant-wide e super admin veem todos os
condomínios ativos; usuários escopados por `user_roles.condominium_id` veem apenas seu escopo. Vale
para a listagem de links, a fila e as ações de moderação.

## Endurecimento anti-abuso (superfície pública)

Como os formulários são públicos e sem login, o intake tem camadas de proteção:

- **Honeypot**: campo oculto `company_site` nos dois formulários. Se vier preenchido (bot), o
  envio é descartado silenciosamente (finge sucesso). Componente `HoneypotField`.
- **Captcha Cloudflare Turnstile (opcional)**: `config/services.php` → `turnstile`. Sem
  `TURNSTILE_SITE_KEY`/`TURNSTILE_SECRET`, a verificação é no-op e o widget não aparece (dev segue
  funcionando). Com as chaves, o token é validado em `CaptchaVerifier::verify()`; falha de rede não
  derruba o envio (honeypot + rate-limit seguem). Widget no front: `TurnstileWidget`.
- **Throttle por IP**: `throttle:10,1` nas rotas POST (envios e consulta de status).
- **Limite por IP+condomínio**: máx. `MAX_PENDING_PER_IP_DAY` (5) envios por IP/condomínio em 24h.
- **Dedupe**: envio idêntico (mesmo tipo + telefone + condomínio) pendente nos últimos 10 min é
  tratado como duplicado e cai direto na confirmação, sem criar novo registro.

Variáveis de ambiente (opcionais) para ativar o captcha:

```
TURNSTILE_SITE_KEY=...
TURNSTILE_SECRET=...
```

## Foto na ocorrência pública

O formulário de ocorrência aceita até **3 imagens** (jpg/png/webp, 5 MB cada). As fotos são
anexadas à própria `PublicSubmission` (`entity_type = public_submission`). Na **aprovação**, os
`StorageObject` são re-apontados para `entity_type = occurrence` + `entity_id` da ocorrência, então
aparecem automaticamente como anexos dela. Na **reprovação**, as fotos são soft-deletadas (liberando
cota). O `AttachmentController` autoriza `public_submission` por `public_links:read`/`:manage`. As
fotos também aparecem na tela de moderação.

## Acompanhamento por protocolo

Cada `PublicSubmission` recebe um `protocol` (8 chars, único por tenant), exibido na tela de
confirmação. Em `/p/{token}/status`, o solicitante informa protocolo + telefone usado no envio e vê
o status (Pendente/Aprovado/Reprovado) com mensagem genérica — sem expor dados sensíveis.

## Notificações

Novo envio dispara `PublicSubmissionReceived` aos gestores (papéis de painel) do tenant. Respeita as
preferências granulares (D11) pelo evento `public_submission_received` (canais database, broadcast,
mail).

## Arquivos-chave

- `database/migrations/2026_06_30_000001_create_condominium_public_links_table.php`
- `database/migrations/2026_06_30_000002_create_public_submissions_table.php`
- `database/migrations/2026_06_30_000003_register_public_links_permissions_and_module.php`
- `app/Models/CondominiumPublicLink.php`, `app/Models/PublicSubmission.php`
- `app/Services/PublicSubmissionService.php`
- `app/Http/Controllers/PublicIntakeController.php`
- `app/Http/Controllers/Panel/PublicLinkController.php`
- `app/Http/Controllers/Panel/PublicSubmissionController.php`
- `app/Http/Controllers/Concerns/ScopesCondominiumsByRole.php`
- `app/Notifications/PublicSubmissionReceived.php`
- `app/Support/NotificationPreferenceRegistry.php`
- `resources/js/Layouts/PublicLayout.tsx`
- `resources/js/Pages/Public/` (Landing, ResidentSignup, Occurrence, Sent)
- `resources/js/Pages/PublicLinks/` (Index, Moderation/Index, Moderation/Show)
- `resources/js/Layouts/AppLayout.tsx`
- `routes/web.php`
- `database/seeders/PermissionSeeder.php`, `RoleSeeder.php`, `PlanSeeder.php`

## Validações feitas

- `php -l` nos PHP novos/alterados.
- `php artisan route:list --name=public --except-vendor` (13 rotas).
- `npm run build` (`tsc && vite build`).
- `git diff --check` (apenas avisos CRLF do Windows).
