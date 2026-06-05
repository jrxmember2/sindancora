# Cadastro completo da unidade (moradores + pets)

O formulário de **criar/editar unidade** passou a incluir, na mesma tela, o cadastro completo de
pessoas e pets da unidade.

## Estrutura

- **Proprietários, Inquilinos e Familiares** são todos `Person` vinculados à unidade via
  `person_unit_links.type` (`owner` / `tenant` / `dependent`). O 1º proprietário fica `is_primary`.
- **Pessoa** ganhou múltiplos contatos: colunas JSON `phones` e `emails` em `persons`. O **1º item de
  cada lista espelha** em `phone` / `email` (campos principais já usados por WhatsApp, cobranças e
  notificações) — telefones são guardados em **dígitos** (ex.: `11999998888`); a normalização para o
  WhatsApp prefixa o `55` automaticamente.
- **Pets**: tabela `pets` (por unidade) com `name`, `species` (dog/cat/bird/fish/rodent/reptile/other),
  `breed`, `notes`.

## Sincronização (`App\Services\UnitRosterService`)

`sync(Unit, $data)` roda em transação e:
1. Para cada grupo (owners/tenants/family) faz **upsert da pessoa**: se houver **CPF**, reaproveita a
   pessoa existente (respeita o unique `tenant+cpf`, **não duplica**); sem CPF, usa o `id` (edição) ou
   cria nova. Grava `phones`/`emails` (limpos: dígitos/lowercase, sem vazios/duplicados) + principal.
2. Cria/atualiza o **vínculo** (`person_unit_links`) por tipo e remove os vínculos da unidade que não
   vieram no envio.
3. Faz o mesmo para **pets** (upsert por `id`, remove os ausentes).

Linhas em branco (sem nome) são ignoradas.

## Backend

`UnitController` (`store`/`update`) valida os atributos da unidade + os arrays `owners`, `tenants`,
`family`, `pets` (validação propositalmente leve; o serviço normaliza). `edit` carrega
`activeLinks.person` + `pets` e serializa por tipo (com fallback dos contatos legados `phone/phone2`
/`email` quando o JSON ainda não existe). Limite de plano continua só em **unidades** (pessoas não
contam).

## Frontend

`Units/Form.tsx` é o formulário único: seção **Dados da unidade** + seções repetíveis
**Proprietários / Inquilinos / Familiares** (cada pessoa com nome, CPF e nascimento mascarados, e
listas de **telefones**/**e-mails** com botões +/−) + seção **Pets**. Máscaras em
`resources/js/lib/masks.ts` (`maskCpf`, `maskDate`, `maskPhone`, `isoToBrDate`). No `Edit`, os valores
do servidor são mascarados na inicialização; no envio vão mascarados e o backend normaliza.

## Deploy

`migrate --force` (colunas `persons.phones/emails` + tabela `pets`) + `optimize:clear`. Sem
`db:seed`, sem env nova.

## Limitação conhecida

A tela antiga de **Pessoas** (`PersonForm`) ainda edita só `phone`/`email` simples; ao editar por lá,
as listas JSON `phones`/`emails` não são atualizadas. O fluxo rico é o da unidade. Unificar a tela de
Pessoas com múltiplos contatos fica como follow-up.
