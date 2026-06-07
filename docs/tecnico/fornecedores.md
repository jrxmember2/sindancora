# Fornecedores / Prestadores (Fase B — B6)

Cadastro de fornecedores/prestadores de serviço do tenant, com avaliação (histórico) e
categorização. É a entidade-base do pacote "operação do síndico" (será reusada por
B4 manutenção preventiva, C7 orçamentos e C8 contas a pagar).

## Modelo de dados

| Tabela | Descrição |
| --- | --- |
| `suppliers` | Fornecedor do tenant (compartilhado entre condomínios). Campos: `category` (slug), `name`, `document` (CPF/CNPJ só dígitos), contato (`contact_name`/`phone`/`email`/`website`), endereço, `notes`, `is_active`. Soft delete. |
| `supplier_condominium` | Pivô N:N opcional — a quais condomínios o fornecedor atende. Vazio = disponível a todos. |
| `supplier_evaluations` | Histórico de avaliações: `score` (1–5), `comment`, `user_id` (autor). A nota exibida é a **média** (`withAvg`). |

Migrations: `2026_06_18_000001..3`. Todas com `tenant_id` direto (multitenant) e FKs `cascadeOnDelete`.

## Escopo e regras

- **Fornecedor é do tenant**, não de um condomínio (decisão de produto): a administradora/síndico
  mantém uma lista única. O vínculo a condomínios é opcional, só para organização/filtro.
- **Categoria** reusa o sistema de **Categorias customizáveis** (Fase A): o tipo `supplier` foi
  adicionado a `Category::TYPES`. O select mescla a lista-base `Supplier::CATEGORIES`
  (Limpeza, Elétrica, Hidráulica, Elevador, Jardinagem, Segurança, Dedetização, Pintura,
  Manutenção geral, Outros) com as categorias criadas pelo tenant em **Configurações → Categorias**.
  O valor armazenado é sempre o slug (string), compatível com novas categorias.
- **Documento** é validado por `App\Rules\CpfCnpj` e normalizado para dígitos antes de salvar.

## Permissões

Módulo `suppliers` com `read` / `create` / `update` / `delete`.
- `admin` e `sindico`: todas.
- `subsindico` e `conselheiro`: apenas `read`.
- Registrar avaliação exige `suppliers:update`; remover avaliação exige `suppliers:delete`.

## Backend

- `App\Models\Supplier` (`condominiums()` N:N, `evaluations()` hasMany latest, const `CATEGORIES`).
- `App\Models\SupplierEvaluation` (`supplier()`, `author()` → `User`).
- `App\Http\Controllers\Panel\SupplierController`: CRUD + `storeEvaluation`/`destroyEvaluation`.
  A Index usa `withCount('evaluations')` + `withAvg('evaluations','score')` e filtros
  (categoria, condomínio, busca por nome/documento).

## Rotas (painel)

| Método | URI | Nome | Permissão |
| --- | --- | --- | --- |
| GET | `/fornecedores` | `suppliers.index` | `suppliers:read` |
| GET | `/fornecedores/{supplier}` | `suppliers.show` | `suppliers:read` |
| GET | `/fornecedores/criar` | `suppliers.create` | `suppliers:create` |
| POST | `/fornecedores` | `suppliers.store` | `suppliers:create` |
| GET | `/fornecedores/{supplier}/editar` | `suppliers.edit` | `suppliers:update` |
| PUT/PATCH | `/fornecedores/{supplier}` | `suppliers.update` | `suppliers:update` |
| POST | `/fornecedores/{supplier}/avaliacoes` | `suppliers.evaluations.store` | `suppliers:update` |
| DELETE | `/fornecedores/avaliacoes/{evaluation}` | `suppliers.evaluations.destroy` | `suppliers:delete` |
| DELETE | `/fornecedores/{supplier}` | `suppliers.destroy` | `suppliers:delete` |

As estáticas (`criar`, `avaliacoes/{evaluation}`) são registradas antes da dinâmica `{supplier}`.

## Front

Páginas em `resources/js/Pages/Suppliers/`: `Index`, `Create`, `Edit`, `Show`, `SupplierForm`.
Item de menu **"Fornecedores"** (ícone `Truck`) no `AppLayout`, gated por `suppliers:read`.
Reusa `maskCpfCnpj`/`maskPhone` de `lib/masks.ts` e o autocomplete ViaCEP no endereço.

## Integração operacional

- `Supplier` agora expõe `maintenancePlans()`, `maintenanceRecords()` e `expenses()` para consolidar
  uso do prestador na operação.
- A listagem de fornecedores mostra manutenções ativas e total em aberto em contas a pagar.
- O detalhe do fornecedor mostra cards de manutenção/financeiro, manutenções vinculadas, execuções
  recentes e contas do fornecedor. Os links respeitam permissão e módulos ativos do plano.
- Essa visão cruza B6 (fornecedores), B4 (manutenção) e C8 (contas a pagar) sem duplicar dados.

## Deploy

`migrate --force` (3 tabelas) + `db:seed --force` (permissões `suppliers:*`) + `optimize:clear`
+ rebuild do front. Sem variáveis de ambiente novas.
