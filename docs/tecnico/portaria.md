# Portaria Digital (Fase 6.6)

Controle de visitantes do condomínio: pré-autorização pelo morador (com QR Code),
registro de entradas/saídas pelo porteiro e log de acessos. Última subfase do MVP.

## Conceito

Três atores, três áreas:

| Ator | Área | O que faz |
|------|------|-----------|
| **Morador** | Portal (`/portal/visitantes`) | Pré-autoriza visitantes da sua unidade e gera o QR Code/código. |
| **Porteiro** | Área dedicada (`/portaria`) | Valida o QR/código na entrada, registra entradas/saídas (inclusive avulsas). |
| **Gestor** (admin/síndico) | Painel (`/visitantes`) | Monitora visitantes presentes e o histórico; cria/revoga autorizações. |

## Modelo de dados

Migration `2026_06_08_000001_create_gatehouse_tables`:

- **`visitor_authorizations`** — pré-autorizações. Campos: `condominium_id`, `unit_id`,
  `created_by` (usuário que autorizou), `visitor_name/document/phone`, `type`
  (`single` | `recurring`), `valid_from`/`valid_until`, **`token`** (único, apresentado
  como QR), `status` (`active` | `used` | `expired` | `revoked`), `notes`. SoftDeletes.
- **`visitor_visits`** — log de acesso (entradas/saídas). Campos: `condominium_id`,
  `unit_id` (nullable), `authorization_id` (nullable → walk-in), `visitor_name/document`,
  **`check_in_at`** / **`check_out_at`** (nullable = presente), `registered_by` (porteiro),
  `notes`.

Models: `App\Models\VisitorAuthorization` (consts `TYPES`/`STATUSES`, `isValid()`,
scope `active`) e `App\Models\VisitorVisit` (scope `present()` = sem `check_out_at`).
Ambos com `protected $table` explícito (regra do projeto após o bug de pluralização).

## Serviço

`App\Services\GatehouseService`:

- `authorize(array, ?User)` — cria a autorização e gera `token` único (`Str::upper(Str::random(8))`).
- `findByToken(string)` — localiza por token (case-insensitive), no escopo do tenant.
- `checkInAuthorized(VisitorAuthorization, ?User)` — cria a visita, vincula a autorização,
  marca `used` se `single`, e **notifica o morador** (`VisitorArrived`). Em transação.
- `checkInWalkIn(array, ?User)` — entrada avulsa (sem autorização prévia).
- `checkOut(VisitorVisit)` — fecha a visita (idempotente).
- `revoke(VisitorAuthorization)` — status `revoked`.

Notificação `App\Notifications\VisitorArrived` (canal `database`, in-app) ao usuário que
autorizou, quando o visitante é liberado na portaria.

## Permissões e papéis

Permissões novas (PermissionSeeder): `gatehouse:read`, `gatehouse:register`, `gatehouse:manage`.

Papéis (RoleSeeder):
- **`porteiro`** (NOVO): `gatehouse:read`, `gatehouse:register`. Acesso restrito a `/portaria`.
- `admin` e `sindico`: `gatehouse:read|register|manage`.
- `subsindico` e `conselheiro`: `gatehouse:read` (leitura/monitoramento no painel).

## Roteamento

`User::homeRoute()` centraliza o destino por papel: super admin → painel admin; gestor →
`/dashboard`; **porteiro → `/portaria`**; morador → `/portal`. Usado no login
(`AuthenticatedSessionController`) e nos middlewares `EnsurePanelAccess`/`EnsureResident`.

Middleware **`EnsureGatehouse`** (alias `gatehouse`): libera quem tem `gatehouse:register`
ou o papel `porteiro`; os demais são redirecionados à sua área. Arquivo de rotas
`routes/portaria.php` (registrado em `bootstrap/app.php`).

Rotas:
- **Porteiro** (`portaria.*`): `index`, `validate`/`validate.check`, `checkin.authorized`,
  `checkin.walkin`, `checkout`, `log`.
- **Gestor** (`gatehouse.*`, sob `permission:gatehouse:read|manage`): `index`,
  `authorizations.store`, `authorizations.revoke`.
- **Morador** (`portal.visitors.*`): `index`, `create`, `store`, `show`, `revoke`.

## QR Code

O QR é renderizado **no frontend** (lib `qrcode.react`, `QRCodeSVG`) a partir do `token`
da autorização — sem dependência de extensão `gd`/`imagick` no PHP. O porteiro lê o QR ou
digita o código em `/portaria/validar`; o backend valida via `findByToken` + `isValid()`.

## Frontend

- Layout `PortariaLayout.tsx` — minimalista, pensado para tablet de portaria.
- Páginas porteiro: `Portaria/{Index,Validate,Log}.tsx`.
- Página gestor: `Panel/Gatehouse/Index.tsx` (presentes + autorizações + histórico).
- Páginas morador: `Portal/Visitors/{Index,Create,Show}.tsx` (Show exibe o QR).
- Itens de menu "Portaria" (painel, ícone `DoorOpen`) e "Visitantes" (portal).

## Deploy

`migrate --force` (cria `visitor_authorizations` + `visitor_visits`) + `db:seed --force`
(permissões `gatehouse:*` e papel `porteiro`) + `optimize:clear`. Worker de fila ativo
(notificação `VisitorArrived` é enfileirada). Criar usuário com papel `porteiro` para a
equipe de portaria.

## Adiado

Foto do visitante, leitura de placa/OCR, reconhecimento facial, integração com fechaduras,
e métrica/limite de plano por visitantes.
