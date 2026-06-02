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
| `category` | string(30) | `minutes, regulation, contract, receipt, other` |
| `visibility` | string(20) | `residents` (moradores) ou `restricted` (administração) |
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

Se estourar a cota (`StorageQuotaException`), o documento recém-criado é removido (`forceDelete`)
e o usuário recebe o erro no campo do arquivo (Inertia não dispara o handler 402, que é só para JSON).

## Download

`DocumentController::download()` tenta `temporaryUrl()` (URL assinada com expiração de 10 min —
funciona em R2/S3/MinIO) e **redireciona** para ela; em disco local (sem suporte a URL temporária)
faz **streaming** via `Storage::download()`. Gate `documents:download`.

## Exclusão / lixeira

`destroy()` faz soft delete do documento e chama `StorageService::delete($object)` (modo não imediato):
marca `deleted_at` + `permanent_delete_at = now()+30 dias`. A remoção definitiva do arquivo do bucket
após 30 dias deve ser feita por uma rotina de limpeza (a agendar — ainda não implementada).

## RBAC

Prefixo `/documentos`, gates: `documents:read` (index), `documents:upload` (create/store/edit/update —
**não há permissão `documents:update`** no seed; edição de metadados usa `upload`), `documents:download`,
`documents:delete`. Permissões já semeadas — **não exige re-seed**. Rotas estáticas (`enviar`) antes das
dinâmicas (`{document}`).

> A visibilidade `residents` × `restricted` é apenas armazenada por ora; o enforcement (morador só vê
> os públicos) acontece no Portal do Morador (Fase 4). No painel, quem tem `documents:read` vê todos.

## Deploy

`php artisan migrate --force && php artisan optimize:clear`. Sem `db:seed`. Exige o disco de storage
configurado em produção (R2/MinIO) — ver `docs/produto/04-planos-limites-e-storage.md`.

## Pendências

Filtro por data, substituição de arquivo na edição, rotina de expurgo da lixeira (30 dias), e o uso do
mesmo `StorageService` para **anexos** de Comunicados (3.1) e Ocorrências (3.2), que ficaram adiados.
