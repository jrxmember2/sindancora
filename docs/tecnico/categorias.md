# Categorias customizáveis

Permite que cada tenant crie categorias próprias para classificar **ocorrências** e **documentos**,
além das categorias padrão do sistema. (Fase A da nova onda — ver `docs/produto/06-roadmap-nova-onda.md`.)

## Modelo
- Tabela `categories` (migration `2026_06_17_000002_create_categories_table`): `tenant_id`, `type`
  (`occurrence` | `document`), `name`, `slug`, `color`, `sort_order`, `is_active`, soft deletes.
  Único por `(tenant_id, type, slug)`.
- Model `App\Models\Category` (BelongsToTenant, HasUuidKey, SoftDeletes).
  - `Category::optionsFor($tenantId, $type, $base)` — mescla as categorias padrão (constantes) com as
    customizadas ativas e retorna `slug => rótulo` para os selects.
  - `Category::makeSlug(...)` — gera slug único por tenant+tipo.

## Não-quebra de dados
O valor armazenado em `occurrences.category` / `documents.category` continua sendo um **slug string**.
As categorias padrão mantêm seus slugs originais (constantes `Occurrence::CATEGORIES` / `Document::CATEGORIES`),
então registros existentes seguem válidos. Categorias customizadas apenas **acrescentam** opções.
Na edição, o slug **não muda** (evita órfãos); remover uma categoria preserva os registros já classificados.

## Painel
- `Panel\CategoryController` (CRUD) em `/configuracoes/categorias`, permissão **`categories:manage`**
  (admin + síndico). Tela `Settings/Categories.tsx`, menu "Categorias".
- `OccurrenceController` e `DocumentController` agora montam o payload `categories` via
  `Category::optionsFor(...)` (index/create/edit/show) e validam `category` contra as chaves mescladas.

## Deploy
`migrate --force` (cria `categories`) + `db:seed --force` (permissão `categories:manage`) + `optimize:clear`.
