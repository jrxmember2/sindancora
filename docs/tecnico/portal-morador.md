# Portal do Morador (Fase 4)

> Status: implementado. Interface dedicada ao morador, escopada à própria pessoa/unidades.

## Visão geral

O portal é uma área `/portal` no **mesmo domínio** do painel (sem subdomínio separado). O login é
único: após autenticar, o usuário é roteado por papel.

- **Super admin** → `/admin` (painel SaaS).
- **Gestor** (admin/síndico/subsíndico/conselheiro) → `/dashboard` (painel do tenant).
- **Morador** (apenas role `morador`) → `/portal` (portal do morador).

A decisão "gestor vs morador" usa `User::canAccessPanel()` (super admin ou qualquer papel de
gestão). Moradores "puros" ficam restritos ao portal.

## Identidade: Person ↔ User

- `users.person_id` (nullable, FK → `persons`, `nullOnDelete`) liga o usuário do portal à pessoa
  cadastrada na Fase 2. Usuários administrativos podem ter `person_id` nulo.
- `Person::user()` (hasOne) e `User::person()` (belongsTo).
- O escopo de dados do morador deriva dos **vínculos ativos** (`PersonUnitLink` sem `end_date`):
  unidades ativas → condomínios. Ver `App\Http\Controllers\Portal\Concerns\InteractsWithResident`.

## Convite e ativação

Fluxo disparado pela **ficha da Pessoa** (`Persons/Show`, botão "Convidar para o portal"):

1. `PersonController@invite` → `App\Services\InvitationService::invite(Person)`.
2. O serviço garante o `User` (status `invited`), sincroniza papéis e envia o e-mail.
   - **Papéis**: `morador` (base) + papéis de gestão ativos derivados de `CondominiumManager`
     (síndico/subsíndico/conselheiro). Isso atende ao item adiado da Fase 2 (role automático).
   - **Limite de plano**: criação de novo usuário respeita `PlanLimitService` (`users`).
   - **Token**: reutiliza o broker de senha do Laravel (`Password::broker()->createToken`).
3. E-mail `App\Mail\ResidentInvitationMail` (enfileirado, `ShouldQueue`) com link
   `convite/{token}?email=...` (view `mail/invitations/resident.blade.php`).

Ativação (rotas `guest` em `routes/auth.php`):

- `GET convite/{token}` → `Auth\InvitationController@create` → página `Auth/AcceptInvitation`.
- `POST convite` → `store`: valida o token via `Password::reset`, define a senha, marca
  `status=active` + `email_verified_at`, autentica e redireciona para `/portal`.

Reenvio: se o `User` já existe com status `invited`, o mesmo botão reenvia o convite. Contas já
`active` bloqueiam novo convite.

## Roteamento e middlewares

- `routes/portal.php` (registrado em `bootstrap/app.php`): grupo `auth + verified + resident`,
  prefixo `portal`, nome `portal.`.
- `EnsurePanelAccess` (alias `panel`): aplicado ao grupo do painel em `web.php`. Redireciona
  moradores "puros" ao portal.
- `EnsureResident` (alias `resident`): restringe o portal a usuários com `person_id`. Gestor sem
  vínculo volta ao painel; conta sem papel e sem pessoa recebe 403 (evita loop de redirecionamento).
- **Notificações** (`/notificacoes`) ficam **fora** do gate `panel` (usadas pelos dois mundos).
  `NotificationController@index` escolhe a página: `Notifications/Index` (painel) ou
  `Portal/Notifications` (portal).

## Módulos do portal

Controllers em `app/Http/Controllers/Portal/`, páginas em `resources/js/Pages/Portal/`,
layout `PortalLayout.tsx` (mobile-first: sidebar no desktop, tab bar inferior no mobile).

| Módulo | Escopo | Reúso |
|---|---|---|
| Dashboard | KPIs + atalhos | — |
| Comunicados | publicados nos condomínios do morador; confirmação de leitura | `Announcement::visible()`, tabela `announcement_reads` |
| Ocorrências | apenas as abertas pelo próprio morador (`created_by`) | `OccurrenceService::addComment` / `notifyNew` |
| Reservas | áreas dos condomínios do morador; reservas próprias | `ReservationService::request/cancel` |
| Documentos | `visibility = residents` nos condomínios do morador | download via URL assinada (R2/S3/MinIO) |
| Minha unidade | vínculos ativos + histórico | — |
| Meu perfil | nome/telefone/senha do próprio usuário | — |

### Confirmação de leitura (Comunicados)

- Tabela `announcement_reads` (`announcement_id` + `user_id` único, `read_at`).
- A leitura é registrada (idempotente) ao abrir `Portal/Announcements/Show`.
- O índice usa `withExists('reads as is_read')` para marcar lidos/não lidos.

### Notificação de nova ocorrência

`OccurrenceService::notifyNew()` notifica (in-app) os usuários ativos com papel de gestão do tenant
quando um morador abre uma ocorrência pelo portal. As demais atualizações (status, comentário,
atribuição) seguem o fluxo já existente da Fase 3.

## Não incluído nesta fase (adiado)

- PWA (manifest/service worker/instalação) — portal é responsivo, mas não instalável ainda.
- Subdomínio dedicado do portal (decisão: mesmo domínio).
- Anexos em ocorrências do portal (depende do item de anexos ainda adiado da Fase 3).

## Validação

`php -l` em todos os arquivos novos, `route:list` (20 rotas novas: 18 `portal.*` + convite/ativação),
`npm run build` (tsc + Vite) OK. Migrations **não** rodadas localmente (banco pode ser produção):
no deploy, `php artisan migrate --force && php artisan optimize:clear` (sem `db:seed` — o role
`morador` já está semeado).
