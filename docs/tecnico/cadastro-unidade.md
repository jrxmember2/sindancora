# Cadastro completo da unidade (moradores + pets)

O formulário de **criar/editar unidade** passou a incluir, na mesma tela, o cadastro completo de
pessoas e pets da unidade.

## Estrutura

- **Proprietários, Inquilinos e Familiares** são todos `Person` vinculados à unidade via
  `person_unit_links.type` (`owner` / `tenant` / `dependent`). O 1º proprietário fica `is_primary`.
- **Proprietários e Inquilinos** aceitam **Pessoa Física** ou **Pessoa Jurídica** no cadastro da
  unidade. Para PJ, o campo principal muda para razão social + CNPJ; familiares continuam como PF.
- **Pessoa** ganhou múltiplos contatos: colunas JSON `phones` e `emails` em `persons`. O **1º item de
  cada lista espelha** em `phone` / `email` (campos principais já usados por WhatsApp, cobranças e
  notificações) — telefones são guardados em **dígitos** (ex.: `11999998888`); a normalização para o
  WhatsApp prefixa o `55` automaticamente.
- **Pets**: tabela `pets` (por unidade) com `name`, `species` (dog/cat/bird/fish/rodent/reptile/other),
  `breed`, `notes`.

## Sincronização (`App\Services\UnitRosterService`)

`sync(Unit, $data)` roda em transação e:
1. Para cada grupo (owners/tenants/family) faz **upsert da pessoa**: se houver **CPF/CNPJ**,
   reaproveita a pessoa existente (respeita o unique `tenant+cpf`, **não duplica**); sem documento,
   usa o `id` (edição) ou cria nova. O campo legado `persons.cpf` guarda os dígitos do CPF ou CNPJ,
   com `persons.person_type` indicando PF/PJ. Grava `phones`/`emails` (limpos: dígitos/lowercase,
   sem vazios/duplicados) + principal.
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
**Proprietários / Inquilinos / Familiares**. Proprietários e inquilinos têm dropdown PF/PJ; PF usa
nome + CPF + nascimento e PJ usa razão social + CNPJ. Todos mantêm listas de
**telefones**/**e-mails** com botões +/−, além das seções **Pets** e **Veículos**. Máscaras em
`resources/js/lib/masks.ts` (`maskCpf`, `maskCnpj`, `maskDate`, `maskPhone`, `isoToBrDate`). No `Edit`,
os valores do servidor são mascarados na inicialização; no envio vão mascarados e o backend normaliza.

## Deploy

`migrate --force` (colunas `persons.phones/emails`, `persons.person_type` + tabela `pets`) + `optimize:clear`. Sem
`db:seed`, sem env nova.

## Limitação conhecida

A tela antiga de **Pessoas** (`PersonForm`) ainda edita só `phone`/`email` simples; ao editar por lá,
as listas JSON `phones`/`emails` não são atualizadas. O fluxo rico é o da unidade. Unificar a tela de
Pessoas com múltiplos contatos fica como follow-up.

## Veículos (Fase A da nova onda)

Além dos pets, o formulário da unidade agora cadastra **veículos** (tabela `vehicles`, migration
`2026_06_17_000003_create_vehicles_table`): `type` (carro/moto/caminhão/bicicleta/outro), `plate`,
`brand_model`, `color`, `parking_spot` (vaga), `notes`. Model `App\Models\Vehicle` (com `$table`
explícito, igual ao Pet) e relação `Unit::vehicles()`. A persistência segue o mesmo padrão dos pets em
`UnitRosterService::syncVehicles()` (upsert por `id`, ignora linha sem placa nem marca/modelo, remove
os que não vieram, placa em maiúsculas). Útil para portaria/controle de acesso.
