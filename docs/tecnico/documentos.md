# Módulo de Documentos (Fase 3.4)

Repositório de documentos do condomínio (atas, regulamentos, contratos, comprovantes), com
upload para storage S3-compatível, categorias, visibilidade, download por URL assinada e
lixeira temporária. Camada de domínio sobre o `StorageService`/`StorageObject` já existentes.

## Modelo de dados

**`documents`** (migration `2026_06_01_000005`) guarda os metadados de domínio e referencia o arquivo:

| Coluna | Tipo | Observações |
|---|---|---|
| `id` | uuid (PK) | |
| `tenant_id` | uuid | FK `tenants` |
| `condominium_id` | uuid | FK `condominiums` |
| `storage_object_id` | uuid (nullable) | FK `storage_objects` (`nullOnDelete`) — o arquivo físico |
| `uploaded_by` | uuid (nullable) | FK `users` |
| `title` | string | |
| `description` | text (nullable) | |
| `category` | string(30) | `convention, regulation, minutes, contract, circular, receipt, other` |
| `visibility` | string(20) | `residents` (moradores) ou `restricted` (administração) |
| `is_current` | boolean | documento vigente/atual para consulta operacional |
| `is_ai_searchable` | boolean | libera o documento como fonte do assistente de IA |
| timestamps + softDeletes | | |

O arquivo em si (tamanho, path, mime, **hash sha256**, bucket, provider) fica no `storage_objects`,
gerenciado pelo `StorageService`. `Document::STORAGE_VISIBILITY` mapeia a visibilidade do documento
para a do `StorageObject` (`residents` → `public_to_residents`, `restricted` → `tenant`).

## Fluxo de upload

`DocumentController::store()`:
1. Valida metadados + arquivo (`file|max:51200|mimes:pdf,doc,docx,xls,xlsx,odt,ods,jpg,jpeg,png,webp,gif,zip`).
2. Cria o `Document` (sem arquivo).
3. `StorageService::upload(file, tenant, 'document', document->id, $visibilidadeMapeada, condominium_id)`
   — valida mime/tamanho (backstop), **checa a cota do plano**, grava no disco
   (`config('filesystems.default')`), cria o `StorageObject` e **incrementa o contador `storage_mb`** do plano.
4. Grava `storage_object_id` no documento.
5. Se `is_current` e `is_ai_searchable` estiverem ativos, envia `IndexDocument` para gerar os
   trechos usados pelo assistente de IA.

Se estourar a cota (`StorageQuotaException`), o documento recém-criado é removido (`forceDelete`)
e o usuário recebe o erro no campo do arquivo (Inertia não dispara o handler 402, que é só para JSON).

## Download

`DocumentController::download()` tenta `temporaryUrl()` (URL assinada com expiração de 10 min —
funciona em R2/S3/MinIO) e **redireciona** para ela; em disco local (sem suporte a URL temporária)
faz **streaming** via `Storage::download()`. Gate `documents:download`.

## Exclusão / lixeira

`destroy()` faz soft delete do documento e chama `StorageService::delete($object)` (modo não imediato):
marca `deleted_at` + `permanent_delete_at = now()+30 dias`. A remoção definitiva é feita pela rotina
`storage:purge-trash` (comando `App\Console\Commands\PurgeTrashedStorage`), agendada **diariamente às
03:30** em `routes/console.php`. Ela: (1) `forceDelete` dos registros soft-deletados há mais de 30 dias
(antes dos arquivos, por causa da FK `documents.storage_object_id`); (2) apaga do bucket e remove os
`StorageObject` cujo `permanent_delete_at` já passou, decrementando o contador `storage_mb` do tenant
(o arquivo só "libera" cota na remoção definitiva). Suporta `--dry-run`. Roda sem contexto de tenant
(varre todos). Vale para qualquer anexo na lixeira, não só documentos.

## RBAC

Prefixo `/documentos`, gates: `documents:read` (index), `documents:upload` (create/store/edit/update —
**não há permissão `documents:update`** no seed; edição de metadados usa `upload`), `documents:download`,
`documents:delete`. Permissões já semeadas — **não exige re-seed**. Rotas estáticas (`enviar`) antes das
dinâmicas (`{document}`).

> A visibilidade `residents` × `restricted` é apenas armazenada por ora; o enforcement (morador só vê
> os públicos) acontece no Portal do Morador (Fase 4). No painel, quem tem `documents:read` vê todos.

## Documentos atuais e consulta pela IA

Migration `2026_06_25_000001_add_ai_controls_to_documents_table` adiciona:

- `is_current` (default `true`): marca se o documento continua vigente.
- `is_ai_searchable` (default `true`): define se o documento pode ser usado pelo assistente.
- Indice `documents_ai_search_idx` em `(tenant_id, is_current, is_ai_searchable)`.

O cadastro/edicao no painel exibe os controles **Atual** e **Consultar pela IA**. A listagem tambem
tem filtros por atualidade e uso pela IA.

`DocumentIndexer::index()` apaga os chunks quando o documento nao estiver atual ou liberado para IA.
`DocumentSearch` filtra por `documents.is_current = true` e `documents.is_ai_searchable = true`, entao
chunks antigos de documentos desativados nao entram no RAG. Ao editar `condominium_id`, `is_current`
ou `is_ai_searchable`, o controller atualiza o `StorageObject`, sincroniza `document_chunks.condominium_id`
quando necessario e reindexa ou remove os chunks.

## Deploy

`php artisan migrate --force && php artisan optimize:clear`. Sem `db:seed`. Exige o disco de storage
configurado em produção (R2/MinIO) — ver `docs/produto/04-planos-limites-e-storage.md`.

## Pendências

Filtro por data e substituição de arquivo na edição. (Anexos de Comunicados/Ocorrências/Áreas e a rotina
de expurgo da lixeira já foram implementados — ver `docs/tecnico/anexos.md` e `storage:purge-trash`.)

## Vigência/validade (Fase A da nova onda)

Documentos podem ter validade: colunas `valid_from`, `valid_until`, `renewal_alert_days` e
`expiry_notified_at` (migration `2026_06_17_000001_add_validity_to_documents_table`). O model
`Document` expõe os accessors `expiry_status` (`valid` | `expiring` | `expired`) e `days_until_expiry`,
mostrados como badge na listagem. O comando agendado **`documents:notify-expiring`** (diário às 07:00)
varre os documentos vencendo dentro da janela de alerta (`scopeDueForExpiryAlert`) e notifica os
gestores (papéis de painel) via `App\Notifications\DocumentExpiring` (database + mail + broadcast),
marcando `expiry_notified_at` para não repetir. Editar a validade reabre o alerta. Útil para AVCB,
alvarás e contratos. As categorias agora são mescladas com as customizadas do tenant (ver `categorias.md`).
