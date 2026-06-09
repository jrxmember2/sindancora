# Relatorios consolidados multi-condominio

> Entregue em 09/06/2026 como D10 da nova onda operacional.

## Objetivo

O relatorio consolidado transforma os dados operacionais do tenant em uma visao executiva
comparavel por condominio, periodo e modulo. A rota continua sendo `/relatorios`, preservando o
modulo `reports` e os exports financeiros ja existentes.

## Acesso

- Rota: `/relatorios`
- Nome da rota: `reports.index`
- Permissao: `reports:read`
- Modulo de plano: `reports`

Cada bloco interno tambem respeita permissao e modulo de plano da fonte. Exemplos:

- sem modulo `financial`, nao mostra KPIs de cobrancas/despesas;
- sem `occurrences:read`, nao inclui ocorrencias;
- sem `works:read` ou sem modulo `works`, nao inclui obras/reformas.

## Filtros

- Periodo: `from` e `to`.
- Condominios: `condominium_ids[]`; vazio significa todos os condominios acessiveis.
- Modulos: `modules[]`; vazio significa todos os modulos disponiveis para o usuario/plano.

O escopo por condominio segue a mesma regra do cronograma e do assistente de IA:

- superadmin e usuario tenant-wide veem todos os condominios ativos do tenant;
- usuario escopado por `user_roles.condominium_id` ve apenas os condominios associados.

IDs fora do escopo sao ignorados pelo backend.

## Fontes consolidadas

| Modulo | Origem | Principais metricas |
| --- | --- | --- |
| Estrutura | `condominiums`, `units`, `person_unit_links` | condominios, unidades, ocupacao e pessoas vinculadas |
| Financeiro | `charges`, `expenses` | cobrado, recebido, contas pagas, saldo, aberto e inadimplencia |
| Ocorrencias | `occurrences` | criadas no periodo, abertas, encerradas, SLA vencido e alta prioridade |
| Reservas | `reservations` | total, pendentes, aprovadas, recusadas e canceladas |
| Manutencoes | `maintenance_plans`, `maintenance_records` | ativas, a vencer, atrasadas, execucoes e custo |
| Obras/Reformas | `works` | criadas, ativas, atrasadas, concluidas, orcado vs final |
| Documentos | `documents` | enviados, atuais, vencendo e vencidos |
| Orcamentos | `quotations`, `quotation_proposals` | criados, em cotacao, aprovados e valor aprovado |

## Saida do backend

`App\Services\Reports\ConsolidatedReportBuilder` monta:

- `summary`: KPIs gerais do escopo;
- `by_condominium`: comparativo por condominio;
- `monthly`: serie mensal para financeiro e movimentos operacionais;
- `rankings`: inadimplencia, risco operacional e contas pagas;
- `available_modules` e `available_condominiums`: filtros ja recortados por permissao/plano/escopo;
- `filters`: filtros normalizados.

## Frontend

Tela: `resources/js/Pages/Reports/Index.tsx`

Recursos:

- filtros aplicaveis em lote;
- selecao de multiplos condominios;
- selecao de modulos;
- cards executivos;
- tabela comparativa por condominio;
- serie mensal com barras simples;
- rankings laterais;
- exports financeiros existentes para PDF/XLSX quando o escopo e todos ou um unico condominio.

## Exports financeiros

Os exports existentes continuam em:

- `reports.pdf` (`/relatorios/financeiro/pdf`)
- `reports.xlsx` (`/relatorios/financeiro/xlsx`)

Eles usam o relatorio financeiro legado do `ReportController` e aceitam `from`, `to` e,
opcionalmente, `condominium_id`.

## Arquivos principais

- `app/Http/Controllers/Panel/ReportController.php`
- `app/Services/Reports/ConsolidatedReportBuilder.php`
- `resources/js/Pages/Reports/Index.tsx`
- `resources/views/reports/financial.blade.php`
- `app/Exports/FinancialReportExport.php`

## Pos-deploy

Nao ha migration nem seed novo nesta entrega. O deploy normal basta.
