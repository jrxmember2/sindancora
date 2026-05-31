# 08 — Consultas, Filtros e Relatórios

> Gerado automaticamente em 31/05/2026, 11:51:45
> Total de listagens/relatórios identificados: **0**

---


## 1. Listagens e Relatórios Encontrados

_Nenhum dado registrado._


## 2. Detalhamento por Listagem


## 3. Endpoints de API Sugeridos para o Novo Sistema

| Método | Rota | Finalidade | Parâmetros |
| --- | --- | --- | --- |
| GET | `/api/v1/condominiums` | Listar condomínios do tenant | tenant_id, status, page, limit |
| GET | `/api/v1/condominiums/:id/units` | Listar unidades do condomínio | block_id, status, type, page |
| GET | `/api/v1/condominiums/:id/persons` | Listar pessoas do condomínio | type, unit_id, page |
| GET | `/api/v1/condominiums/:id/announcements` | Listar comunicados | audience, date_start, date_end |
| GET | `/api/v1/condominiums/:id/occurrences` | Listar ocorrências | status, category, unit_id, page |
| GET | `/api/v1/condominiums/:id/reservations` | Listar reservas | area_id, status, date_start, date_end |
| GET | `/api/v1/condominiums/:id/documents` | Listar documentos | category, is_public, page |
| GET | `/api/v1/condominiums/:id/charges` | Listar cobranças | unit_id, status, month, year |
| GET | `/api/v1/condominiums/:id/reports/delinquency` | Relatório de inadimplência | month, year |
| GET | `/api/v1/condominiums/:id/reports/financial` | Relatório financeiro | month, year |
| GET | `/api/v1/condominiums/:id/reports/units` | Relatório de ocupação | status |
| POST | `/api/v1/condominiums/:id/reports/export` | Exportar relatório | format: pdf|xlsx |


> **Próximo passo:** Execute `npm run phase:09` para mapear os fluxos operacionais.
