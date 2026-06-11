# Módulos operacionais — Encomendas, Enquetes, Achados & Perdidos

> Implementado em 11/06/2026. Ciclo "Módulos operacionais" (E1–E3).

Três módulos leves que ampliam o uso diário, reaproveitando os padrões do projeto (escopo por
condomínio, portal do morador, notificações com preferências, anexos via `HasAttachments`,
gating por permissão+módulo de plano).

## E1 — Encomendas / Correspondências

Montado sobre o domínio **gatehouse** (porteiro só acessa `/portaria`, não o painel), reusando o
módulo `gatehouse` e as permissões `gatehouse:read|manage` — sem módulo novo.

- Tabela `parcels`; model `App\Models\Parcel` (`HasAttachments`, `ATTACHMENT_ENTITY='parcel'`,
  `markPickedUp()`).
- **Porteiro** (`/portaria/encomendas`, `Portaria\ParcelController`): registra a chegada (condomínio/
  unidade/descrição/transportadora/foto), notifica o morador e dá baixa. Item na nav da Portaria.
- **Gestor** (`/encomendas`, `Panel\ParcelController`): acompanha por condomínio/status e dá baixa
  (`module:gatehouse` + `permission:gatehouse:read|manage`).
- **Morador** (`/portal/encomendas`, `Portal\ParcelController`): vê as encomendas da unidade e confirma
  retirada.
- Notificação `ParcelArrived` (database/broadcast/whatsapp) aos `User` dos `Person` da unidade; evento
  `parcel_arrived`. Foto servida pelo `AttachmentController` (caso `parcel`).

## E2 — Enquetes rápidas

Consulta leve aos moradores, **1 voto por pessoa** (diferente da Assembleia, que é por unidade).
Módulo novo `polls` (permissões `polls:read|manage`), habilitado em todos os planos.

- Tabelas `polls`, `poll_options`, `poll_votes` (único `poll_id+person_id`); models
  `Poll`/`PollOption`/`PollVote`. `App\Services\PollService` (`castVote`, `results`) — versão
  simplificada do `AssemblyService`.
- **Gestor** (`/enquetes`, `Panel\PollController`): CRUD + abrir/encerrar + resultados.
- **Morador** (`/portal/enquetes`, `Portal\PollController`): lista, vota e vê o resultado em %.
- Notificação `PollOpened` aos moradores do condomínio ao abrir; evento `poll_opened`.
- Estados: `draft → open → closed`; opção de enquete anônima e data de encerramento.

## E3 — Achados & Perdidos

Módulo novo `lost_found` (permissões `lost_found:read|manage`), todos os planos.

- Tabela `lost_found_items`; model `LostFoundItem` (`HasAttachments`, `ATTACHMENT_ENTITY='lost_found'`,
  tipos `found|lost`, status `open|resolved`).
- **Gestor** (`/achados-perdidos`, `Panel\LostFoundController`): CRUD + resolver, com foto.
- **Morador** (`/portal/achados-perdidos`, `Portal\LostFoundController`): vê os itens dos seus
  condomínios e **reporta** um item (entra em aberto para curadoria do gestor).
- Foto servida pelo `AttachmentController` (caso `lost_found`; morador vê itens do seu condomínio).

## Permissões / módulos / papéis

Cada módulo é registrado por uma migration no padrão de
`..._register_public_links_permissions_and_module.php`: insere as permissões, vincula aos papéis
padrão (admin/síndico/subsíndico = manage; conselheiro = read) e habilita o módulo em todos os planos.
Encomendas reusa `gatehouse` (sem migration de permissão).

## Deploy

`php artisan migrate --force` (cria tabelas + registra permissões/módulos `polls` e `lost_found`) +
`php artisan optimize:clear`. Sem env nova; worker/scheduler já ativos (notificações em fila). Em
ambientes já existentes, `db:seed --force` é opcional (as migrations já refletem em planos/papéis).

## Verificação

- `php -l` em todos os PHP; `php artisan route:list --name=parcels|polls|lost-found` (painel+portal);
  `npm run build` (tsc+vite) verde.
- E1: porteiro registra → morador notificado e vê em `/portal/encomendas` → baixa nos dois lados.
- E2: criar/abrir enquete → votar pelo portal (bloqueia 2º voto) → encerrar → resultado %.
- E3: registrar item com foto, resolver; morador reporta item perdido pelo portal.
- Plano sem o módulo (Admin > Planos) esconde menu e bloqueia rota (`module:`).
