# Módulo de Comunicados (Fase 3.1)

Gestão de comunicados do condomínio, com editor de texto rico, publicação imediata ou agendada,
expiração automática e e-mail aos moradores na publicação.

## Modelo de dados

Tabela `announcements` (migration `2026_06_01_000001_create_announcements_table`):

| Coluna | Tipo | Observações |
|---|---|---|
| `id` | uuid (PK) | |
| `tenant_id` | uuid | FK `tenants`, isolamento multitenant |
| `condominium_id` | uuid | FK `condominiums`, condomínio-alvo |
| `created_by` | uuid (nullable) | FK `users` (`nullOnDelete`) |
| `title` | string(200) | |
| `body` | longText | HTML produzido pelo editor TipTap |
| `category` | string(30) | `general, maintenance, financial, assembly, event, security` |
| `urgency` | string(20) | `low, normal, high` |
| `status` | string(20) | `draft`, `published` |
| `published_at` | timestamp (nullable) | preenchido quando efetivamente publicado |
| `publish_at` | timestamp (nullable) | agendamento (publicar a partir de) |
| `expires_at` | timestamp (nullable) | expiração automática |
| timestamps + softDeletes | | |

Constantes `Announcement::CATEGORIES` e `Announcement::URGENCIES` definem os rótulos exibidos.

## Estados

- **Rascunho**: `status=draft`, `publish_at` nulo. Só visível no painel.
- **Agendado**: `status=draft`, `publish_at` no futuro. Publicado automaticamente pelo scheduler.
- **Publicado**: `status=published`, `published_at` preenchido.
- **Expirado** (derivado, só na UI): publicado com `expires_at` no passado. O scope `Announcement::visible()`
  já exclui expirados das consultas de exibição (usado a partir da Fase 4 no portal).

## Publicação e notificação

`App\Services\AnnouncementService::publish()` é o ponto único de publicação (idempotente):
marca `status=published` + `published_at=now()` e enfileira um `AnnouncementPublishedMail`
(`ShouldQueue`, fila Redis/Horizon) para cada `Person` **com e-mail** vinculada ativamente
a uma unidade do condomínio do comunicado.

O comando `announcements:publish-scheduled` (agendado a cada minuto em `routes/console.php`)
publica os comunicados agendados cuja data chegou. Ele roda **sem contexto de tenant**, então o
global scope `BelongsToTenant` não filtra e ele varre todos os tenants (comportamento desejado).

> Se o SMTP não estiver configurado, a publicação não quebra: o job de e-mail apenas falha na fila.

## Rotas e RBAC

Prefixo `/comunicados` (`routes/web.php`), gates por ação:
`announcements:read` (index/show), `:create` (create/store), `:update` (edit/update),
`:publish` (POST `/comunicados/{id}/publicar`), `:delete` (destroy).
Rotas estáticas (`create`) registradas antes da dinâmica (`{announcement}`), como no padrão do projeto.
As permissões já estavam semeadas em `PermissionSeeder` e atribuídas em `RoleSeeder` — **não exige re-seed**.

## Frontend

- `resources/js/Components/RichTextEditor.tsx` — editor TipTap (StarterKit), emite HTML.
- Estilo `.rich-content` em `resources/css/app.css` para o conteúdo (sem depender do plugin typography).
- `resources/js/Pages/Announcements/{Index,Create,Edit,Show}.tsx` + `AnnouncementForm.tsx`.
- O e-mail usa a view HTML própria `resources/views/mail/announcements/published.blade.php`.

## Deploy

Apenas `php artisan migrate --force && php artisan optimize:clear`. **Não precisa de `db:seed`**
(as permissões já existem). Os assets do front já vão buildados e commitados em `public/build`.

## Pendências (próximas iterações)

Segmentação fina de público, anexos (junto do módulo Documentos 3.4), confirmação de leitura por
morador e templates reutilizáveis — ver `docs/produto/02-roadmap-mvp.md` §3.1.
