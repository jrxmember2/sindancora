# Obras/Reformas (Fase C - C12)

Modulo operacional para acompanhar obras e reformas por condominio, com vinculo opcional a uma
proposta aprovada de orcamento, fornecedor, anexos, linha do tempo e contas a pagar.

## Modelo de dados

| Tabela | Descricao |
| --- | --- |
| `works` | Obra/reforma do tenant por condominio. Campos: `type`, `status`, `priority`, `title`, `description`, `supplier_id`, `quotation_id`, `quotation_proposal_id`, datas previstas, `completed_at`, `budget_amount`, `final_amount`, `progress_percent`, `responsible_name`, `notes`. Soft delete. |
| `work_updates` | Linha do tempo da obra. Campos: `work_id`, `user_id`, `title`, `description`, `status`, `progress_percent`, `occurred_at`. |

Integracoes de trilha:

- `works.quotation_proposal_id` quando a obra nasce de proposta aprovada.
- `works.quotation_id` para voltar ao orcamento original.
- `expenses.work_id` quando uma conta a pagar pertence a uma obra/reforma.
- Anexos usam `storage_objects` com `entity_type = work`.

## Fluxos principais

- Cadastro manual em `/obras/criar`, com fornecedor, condominio, tipo, status, cronograma, orcamento
  previsto, custo final e anexos.
- Edicao permite adicionar novos anexos e ajustar status/progresso.
- Tela de detalhe mostra escopo, anexos, origem em orcamento, fornecedor, progresso, linha do tempo e
  contas vinculadas.
- Andamentos atualizam opcionalmente o status e o progresso da obra.
- Conta a pagar pode ser gerada pela tela da obra; exige `expenses:create` e modulo `financial`.
- Na aprovacao de proposta em Orcamentos, o usuario pode gerar obra/reforma automaticamente. Se tambem
  gerar conta a pagar no mesmo ato, a conta fica com `quotation_proposal_id` e `work_id`.

## Permissoes e planos

Modulo de plano: `works`.

Permissoes:

- `works:create`
- `works:read`
- `works:update`
- `works:delete`

`CheckPermission` mapeia `works:*` para o modulo `works`. O menu **Obras** aparece apenas com
`works:read` e modulo ativo.

Planos padrao:

- Starter: sem `works`.
- Profissional, Business e Enterprise: com `works`.

## Rotas

| Metodo | URI | Nome | Permissao |
| --- | --- | --- | --- |
| GET | `/obras` | `works.index` | `works:read` |
| GET | `/obras/{work}` | `works.show` | `works:read` |
| GET | `/obras/criar` | `works.create` | `works:create` |
| POST | `/obras` | `works.store` | `works:create` |
| GET | `/obras/{work}/editar` | `works.edit` | `works:update` |
| PUT/PATCH | `/obras/{work}` | `works.update` | `works:update` |
| POST | `/obras/{work}/andamentos` | `works.updates.store` | `works:update` |
| POST | `/obras/{work}/contas-a-pagar` | `works.expenses.store` | `works:update` + `expenses:create` |
| DELETE | `/obras/{work}` | `works.destroy` | `works:delete` |

## Front

Paginas em `resources/js/Pages/Works/`:

- `Index`: KPIs, filtros por status/tipo/condominio/fornecedor/busca e acoes.
- `Create` / `Edit`: cadastro e manutencao dos dados da obra.
- `Show`: escopo, origem, anexos, contas vinculadas e linha do tempo.

Integracoes visuais:

- `resources/js/Pages/Quotations/Show.tsx` pode gerar obra na aprovacao de proposta e mostra o link
  da obra criada.
- `resources/js/Pages/Expenses/Index.tsx` e `Edit.tsx` mostram origem em obra quando houver `work_id`.

## Deploy

Rodar:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
```

`db:seed --force` e necessario porque C12 adiciona novo modulo de plano e novas permissoes.
