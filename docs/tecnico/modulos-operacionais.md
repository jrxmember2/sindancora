# Modulos operacionais - Encomendas, Enquetes, Achados & Perdidos, Regimento e Mural

> Implementado em 11/06/2026. Ciclo "Modulos operacionais" (E1-E5).

Modulos leves que ampliam o uso diario, reaproveitando os padroes do projeto: escopo por
condominio, portal do morador, notificacoes com preferencias, anexos via `HasAttachments` e
gating por permissao + modulo de plano.

## E1 - Encomendas / Correspondencias

Montado sobre o dominio **gatehouse**. Porteiro acessa `/portaria`, nao o painel. Reusa o modulo
`gatehouse` e as permissoes `gatehouse:read|manage`, sem modulo novo.

- Tabela `parcels`; model `App\Models\Parcel` (`HasAttachments`, `ATTACHMENT_ENTITY='parcel'`,
  `markPickedUp()`).
- **Porteiro** (`/portaria/encomendas`, `Portaria\ParcelController`): registra chegada
  (condominio/unidade/descricao/transportadora/foto), notifica o morador e da baixa.
- **Gestor** (`/encomendas`, `Panel\ParcelController`): acompanha por condominio/status e da baixa.
- **Morador** (`/portal/encomendas`, `Portal\ParcelController`): ve encomendas da unidade e confirma
  retirada.
- Notificacao `ParcelArrived` (database/broadcast/whatsapp) aos usuarios das pessoas da unidade;
  evento `parcel_arrived`.

## E2 - Enquetes rapidas

Consulta leve aos moradores, com 1 voto por pessoa. Modulo novo `polls` (`polls:read|manage`),
habilitado em todos os planos.

- Tabelas `polls`, `poll_options`, `poll_votes` (unico `poll_id+person_id`).
- Models `Poll`, `PollOption`, `PollVote` e `App\Services\PollService`.
- **Gestor** (`/enquetes`, `Panel\PollController`): cria, abre, encerra, remove e consulta resultados.
- **Morador** (`/portal/enquetes`, `Portal\PollController`): lista, vota e ve resultado.
- Notificacao `PollOpened`; evento `poll_opened`.
- Estados: `draft -> open -> closed`.

## E3 - Achados & Perdidos

Modulo novo `lost_found` (`lost_found:read|manage`), habilitado em todos os planos.

- Tabela `lost_found_items`; model `LostFoundItem` (`HasAttachments`,
  `ATTACHMENT_ENTITY='lost_found'`).
- Tipos `found|lost`; status `open|resolved`.
- **Gestor** (`/achados-perdidos`, `Panel\LostFoundController`): CRUD + resolver, com foto.
- **Morador** (`/portal/achados-perdidos`, `Portal\LostFoundController`): ve itens dos seus
  condominios e reporta item.

## E4 - Multas e advertencias regimentais

Modulo novo `disciplinary` (`disciplinary:read|manage`), habilitado em todos os planos. Registra
advertencias e multas por unidade, preservando historico regimental e ciencia do morador.

- Tabela `disciplinary_records`; model `DisciplinaryRecord` (`HasAttachments`,
  `ATTACHMENT_ENTITY='disciplinary_record'`).
- Tipos `warning|fine`; status `issued|acknowledged|cancelled`.
- **Gestor** (`/multas-advertencias`, `Panel\DisciplinaryRecordController`): lista, emite, consulta,
  cancela e, em multas, pode gerar cobranca vinculada quando o plano tem `financial` e o usuario tem
  `charges:create`.
- **Morador** (`/portal/multas-advertencias`, `Portal\DisciplinaryRecordController`): consulta
  registros das suas unidades e registra ciencia.
- Notificacao `DisciplinaryRecordIssued` (database/broadcast/whatsapp); evento
  `disciplinary_record_issued`.
- Anexos/evidencias passam pelo `AttachmentController` e respeitam o escopo da unidade.

## E5 - Mural e classificados

Modulo novo `community_board` (`community_board:read|manage`), habilitado em todos os planos. Une
publicacoes de mural feitas pela gestao e classificados enviados por moradores com moderacao.

- Tabela `community_posts`; model `CommunityPost` (`HasAttachments`,
  `ATTACHMENT_ENTITY='community_post'`).
- Tipos `notice|classified`; status `pending|published|rejected|archived`.
- **Gestor** (`/mural`, `Panel\CommunityPostController`): publica diretamente, aprova/rejeita
  classificados pendentes, arquiva e remove publicacoes.
- **Morador** (`/portal/mural`, `Portal\CommunityPostController`): ve publicacoes publicadas dos seus
  condominios e envia classificados para moderacao.
- Notificacao `CommunityPostApproved` ao autor quando um classificado e publicado; evento
  `community_post_approved`.
- Publicacoes publicadas respeitam `expires_at`; expiradas deixam de aparecer no portal.

## Permissoes / modulos / papeis

Cada modulo novo e registrado por migration no padrao de `register_*_permissions_and_module`: insere
permissoes, vincula aos papeis padrao e habilita o modulo em todos os planos. Regra atual:

- `admin`, `sindico` e `subsindico`: `read` + `manage`.
- `conselheiro`: `read`.
- `morador`: acessa pelo portal via modulo do plano, sem permissao de painel.

O cadastro de planos no super admin tambem conhece `public_links`, `polls`, `lost_found`,
`disciplinary` e `community_board`, evitando perda desses modulos ao editar planos.

## Deploy

```bash
php artisan migrate --force
php artisan optimize:clear
```

`php artisan db:seed --force` e opcional em ambientes existentes; as migrations ja refletem os novos
modulos em planos e papeis padrao. Em rebuild/reset, os seeders tambem contem as permissoes e modulos.

## Verificacao

- `php -l` nos PHP alterados.
- `php artisan route:list --name=parcels --except-vendor`
- `php artisan route:list --name=polls --except-vendor`
- `php artisan route:list --name=lost-found --except-vendor`
- `php artisan route:list --name=disciplinary --except-vendor`
- `php artisan route:list --name=community-board --except-vendor`
- `npm run build`

Fluxos principais:

- E1: porteiro registra -> morador notificado e ve em `/portal/encomendas` -> baixa nos dois lados.
- E2: gestor abre enquete -> morador vota -> segundo voto da mesma pessoa bloqueado -> resultado.
- E3: gestor registra/resolver item; morador reporta item perdido pelo portal.
- E4: gestor emite advertencia/multa -> morador consulta e registra ciencia; multa pode gerar cobranca.
- E5: morador envia classificado -> gestor aprova/rejeita -> classificado publicado aparece no portal.
