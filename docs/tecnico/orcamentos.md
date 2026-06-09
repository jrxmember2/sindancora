# Orçamentos / Cotações (Fase C - C7)

Módulo operacional para registrar cotações multi-fornecedor, comparar propostas, aprovar uma opção e
converter o orçamento aprovado em manutenção e/ou conta a pagar.

## Modelo de dados

| Tabela | Descrição |
| --- | --- |
| `quotations` | Orçamento/cotação do tenant por condomínio. Campos: `category`, `title`, `description`, `status` (`draft/collecting/approved/rejected/cancelled`), `response_deadline`, `approved_proposal_id`, `approved_at`, `approved_by`, `created_by`, `notes`. |
| `quotation_proposals` | Propostas de fornecedores. Campos: `quotation_id`, `supplier_id`, snapshot `supplier_name`, `amount`, `execution_days`, `valid_until`, `status` (`received/approved/rejected`), `submitted_at`, `notes`. |

Anexos de proposta usam `storage_objects` com `entity_type = quotation_proposal`; download/remoção
passam pelo `AttachmentController` e respeitam `quotations:read` / `quotations:update`.

Integrações de trilha:

- `expenses.quotation_proposal_id` quando uma proposta aprovada gera conta a pagar.
- `maintenance_plans.quotation_proposal_id` quando uma proposta aprovada gera manutenção.
- `works.quotation_proposal_id` quando uma proposta aprovada gera obra/reforma.

## Regras principais

- Um orçamento aberto (`draft` ou `collecting`) aceita múltiplas propostas de fornecedores ativos.
- A aprovação de uma proposta roda em transação:
  - proposta escolhida vira `approved`;
  - demais propostas do orçamento viram `rejected`;
  - orçamento vira `approved`, com `approved_proposal_id`, `approved_at` e `approved_by`.
- Ao aprovar, o usuário pode opcionalmente gerar **manutenção**, **obra/reforma** e/ou **conta a pagar**.
- Gerar conta a pagar exige `expenses:create` e módulo `financial` ativo.
- Gerar manutenção exige `maintenance:create` e módulo `maintenance` ativo.
- Gerar obra/reforma exige `works:create` e módulo `works` ativo.
- Orçamento aprovado não pode ser editado/removido.

## Permissões e planos

Novo módulo de plano: `quotations`.

Permissões: `quotations:create`, `quotations:read`, `quotations:update`, `quotations:approve`,
`quotations:delete`.

`CheckPermission` mapeia permissões `quotations:*` para o módulo de plano `quotations`. O menu
**Orçamentos** aparece apenas com `quotations:read` e módulo ativo.

## Rotas

| Método | URI | Nome | Permissão |
| --- | --- | --- | --- |
| GET | `/orcamentos` | `quotations.index` | `quotations:read` |
| GET | `/orcamentos/{quotation}` | `quotations.show` | `quotations:read` |
| GET | `/orcamentos/criar` | `quotations.create` | `quotations:create` |
| POST | `/orcamentos` | `quotations.store` | `quotations:create` |
| GET | `/orcamentos/{quotation}/editar` | `quotations.edit` | `quotations:update` |
| PUT/PATCH | `/orcamentos/{quotation}` | `quotations.update` | `quotations:update` |
| POST | `/orcamentos/{quotation}/propostas` | `quotations.proposals.store` | `quotations:update` |
| DELETE | `/orcamentos/propostas/{proposal}` | `quotations.proposals.destroy` | `quotations:update` |
| POST | `/orcamentos/propostas/{proposal}/aprovar` | `quotations.proposals.approve` | `quotations:approve` |
| POST | `/orcamentos/{quotation}/reprovar` | `quotations.reject` | `quotations:approve` |
| DELETE | `/orcamentos/{quotation}` | `quotations.destroy` | `quotations:delete` |

## Front

Páginas em `resources/js/Pages/Quotations/`:

- `Index`: KPIs, filtros por status/categoria/condomínio/busca e ações.
- `Create` / `Edit`: dados do orçamento.
- `Show`: escopo, propostas, anexos, comparação, aprovação e geração opcional de manutenção/obra/conta.

Integrações visuais:

- Fornecedor mostra contagem e lista recente de propostas.
- Conta a pagar mostra origem em orçamento quando houver `quotation_proposal_id`.
- Manutenção mostra origem em orçamento quando houver `quotation_proposal_id`.
- Obra/Reforma mostra origem em orçamento quando houver `quotation_proposal_id`.

## Deploy

Rodar:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
```

`db:seed --force` é necessário porque há novas permissões, novo módulo de plano e novos vínculos nos
perfis padrão.
