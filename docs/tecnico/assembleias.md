# Assembleias Digitais (Fase 6.5)

> Status: implementado. Assembleias com pauta, **votação online por unidade** (morador vota pelo
> portal), presença digital e **ata redigida pela IA** (fallback para modelo determinístico).

## Modelo de dados

- `assemblies`: condominium_id, title, description, scheduled_at, status (`draft`/`open`/`closed`),
  `minutes` (ata) + `minutes_generated_at`. Soft delete.
- `assembly_agenda_items`: itens da pauta (title, description, position).
- `assembly_options`: opções de voto por item (**múltipla escolha configurável**).
- `assembly_votes`: voto por unidade — **unique(agenda_item_id, unit_id)** (1 voto/unidade/item).
- `assembly_attendances`: presença por unidade — unique(assembly_id, unit_id).

Models: `Assembly`, `AssemblyAgendaItem`, `AssemblyOption`, `AssemblyVote`, `AssemblyAttendance`.
Permissões `assemblies:create|read|update|delete` (admin + síndico no seed).

## Serviço

`App\Services\AssemblyService`:
- `castVote(assembly, item, optionId, unitIds, personId)`: só com votação aberta; `updateOrCreate`
  por unidade (regrava se já votou); registra presença implícita.
- `registerAttendance(assembly, unitIds, personId)`: idempotente.
- `results(assembly)`: por item, contagem/percentual por opção + vencedor; presença vs total de
  unidades do condomínio.
- `generateMinutes(assembly)`: monta o resumo dos resultados e, se a IA estiver configurada
  (`ClaudeClient` da 6.4), pede a ata em prosa; senão usa modelo determinístico. Persiste em
  `assemblies.minutes`.

## Painel (admin/síndico)

`Panel\AssemblyController` (rotas `/assembleias`): CRUD; em **rascunho** gerencia a pauta (itens +
opções dinâmicas); **abrir** votação (trava a pauta) → **encerrar**; apuração ao vivo (barras);
**gerar ata** (IA) + **baixar ata em PDF** (dompdf, view `assemblies/minutes.blade.php`). Páginas
`Assemblies/{Index,Create,Edit,Show}` + `AssemblyForm`. Item "Assembleias" no menu.

## Portal (morador)

`Portal\AssemblyController` (rotas `portal.assemblies.*`): lista assembleias `open`/`closed` dos
condomínios do morador; **registrar presença**; **votar** por item (o voto vale para todas as
unidades ativas do morador — 1 por unidade). Vê o próprio voto e, quando encerrada, a ata. Páginas
`Portal/Assemblies/{Index,Show}` + item no `PortalLayout`. Escopo garantido por
`InteractsWithResident` (condomínios/unidades do morador).

## Deploy

Migration `2026_06_07_000001_create_assemblies_table` → `migrate --force` + `db:seed --force`
(permissões `assemblies:*`) + `optimize:clear`. A geração de ata por IA usa a `ANTHROPIC_API_KEY`
(6.4); sem ela, a ata sai pelo modelo determinístico.

## Fora de escopo (adiado)

Quórum/peso por fração ideal; procuração; voto secreto; edição manual da ata; notificação de
abertura por e-mail/WhatsApp (dá para plugar via notificações existentes depois).
