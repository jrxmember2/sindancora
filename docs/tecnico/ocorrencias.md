# Módulo de Ocorrências / Chamados (Fase 3.2)

Registro e acompanhamento de chamados do condomínio, com ciclo de vida, atribuição a
responsável, histórico (timeline) de comentários e mudanças, e notificações in-app.

## Modelo de dados

**`occurrences`** (migration `2026_06_01_000003`):

| Coluna | Tipo | Observações |
|---|---|---|
| `id` | uuid (PK) | |
| `tenant_id` | uuid | FK `tenants` |
| `condominium_id` | uuid | FK `condominiums` |
| `unit_id` | uuid (nullable) | FK `units` (`nullOnDelete`) — local opcional |
| `created_by` | uuid (nullable) | FK `users` — quem registrou |
| `assigned_to` | uuid (nullable) | FK `users` — responsável |
| `title` | string | |
| `description` | text | |
| `category` | string(30) | `maintenance, cleaning, security, noise, infraction, financial, other` |
| `priority` | string(20) | `low, normal, high, urgent` |
| `status` | string(20) | `open, in_progress, closed` |
| `closed_at` | timestamp (nullable) | preenchido ao encerrar |
| timestamps + softDeletes | | |

**`occurrence_comments`** (migration `2026_06_01_000004`): histórico unificado.
`type` = `comment` (comentário de usuário), `status` (mudança de status, `meta={from,to}`)
ou `assignment` (atribuição, `meta={assigned_to}`). `body` para comentários, `meta` (json) para eventos.

Constantes `Occurrence::CATEGORIES`, `::PRIORITIES`, `::STATUSES` definem os rótulos.

## Regras de negócio (App\Services\OccurrenceService)

Centraliza as ações que geram histórico + notificação:
- `addComment()` — registra comentário e notifica participantes.
- `changeStatus()` — altera status (seta/limpa `closed_at`), registra evento `status`, notifica.
- `assign()` — define/remove responsável, registra evento `assignment`, notifica.

`notifyParticipants()` notifica o **autor** e o **responsável** ativos, **exceto quem executa a ação**,
via `App\Notifications\OccurrenceUpdated` (canal database — reusa a base de notificações, ver
`notificacoes.md`).

## RBAC

Prefixo `/ocorrencias`, gates: `occurrences:read` (index/show), `:create`, `:update`
(edit/update/status/assign/comentários), `:delete`. **Encerrar** (`status=closed`) exige adicionalmente
`occurrences:close` — verificado no controller via `$user->hasPermission()` (subsíndico pode mover para
"Em Andamento" mas não encerrar). Permissões já semeadas — **não exige re-seed**.

Atribuição de responsável: tanto no `store`/`update` quanto no dropdown da tela de detalhe a mudança
de `assigned_to` é roteada pelo `OccurrenceService::assign()` (nunca por update em massa), garantindo
histórico e notificação consistentes.

## Frontend

`resources/js/Pages/Occurrences/{Index,Create,Edit,Show}.tsx` + `OccurrenceForm.tsx`.
A tela **Show** traz a timeline do histórico, botões de transição de status, seletor de responsável
e o formulário de comentário. As unidades no form são filtradas no cliente pelo condomínio selecionado.

## Deploy

`php artisan migrate --force && php artisan optimize:clear`. Sem `db:seed`.

## Pendências

Anexos (junto de Documentos 3.4), categorias configuráveis por condomínio, SLA por categoria,
filtros por unidade/data, e a visão restrita do morador (portal — Fase 4).
Ver `docs/produto/02-roadmap-mvp.md` §3.2.

## Nova onda — Fase A

- **Categorias customizáveis**: o select de categoria agora mescla as constantes com as categorias
  do tenant (`Category::optionsFor(...)`, tipo `occurrence`). Ver `docs/tecnico/categorias.md`.
- **Rascunho de resposta por IA**: na tela de detalhe, o botão "Sugerir resposta com IA" chama
  `POST ocorrencias/{occurrence}/sugestao-ia` (`occurrences.draft-reply`, permissão `ai:use`),
  que usa `AssistantService::draftOccurrenceReply()` (contexto da ocorrência + últimos
  acompanhamentos + RAG) e preenche a caixa de comentário. O botão só aparece com `ai:use` e IA
  configurada (`canDraftAi`). SLA/prazo das ocorrências segue para a Fase B (item B5).
