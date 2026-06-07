# 06 — Roadmap da Nova Onda (pós-MVP)

> Versão 1.0 — 06/06/2026
> Origem: estudo funcional do concorrente **Sindigest (Gcondo)** (ver `docs/mapeamento-concorrente/`
> e o relatório de engenharia reversa em `sindigestcopy/`). Conforme o CLAUDE.md, este roadmap é
> referência funcional para construir recursos **próprios e originais** — não copiamos marca, textos,
> identidade visual, assets nem código do concorrente.

## Contexto estratégico

O MVP do SindÂncora (Fases 1–6.6) já cobre o que o Sindigest **não** mostrou: financeiro completo
+ Asaas (boleto/PIX), assembleias com votação, reservas de áreas, comunicados, portaria digital,
portal do morador, IA com RAG de documentos, WhatsApp (inbox, chatbot, mídia, disparo) e plataforma
(API pública, webhooks, multitenancy/planos).

O Sindigest é forte na **operação do síndico/gestora profissional**: manutenção preventiva,
chamados com SLA, fornecedores, orçamentos, obras, contas a pagar e documentos com validade.
Esta nova onda preenche justamente esse lado operacional, posicionando o SindÂncora como
plataforma completa (financeiro + governança + **operação**).

## Priorização (valor × esforço)

Legenda esforço: 🟢 baixo · 🟡 médio · 🔴 alto. Valor: ⭐ alto diferencial.

### Fase A — Ganhos rápidos  ✅ CONCLUÍDA (06/06/2026)
- [x] **A1. Documentos com vigência/validade + alertas de vencimento + renovação** ⭐ 🟢
  - Campos `valid_from`, `valid_until`, `renewal_alert_days`; situação derivada (Vence em N / Vencido).
  - Comando diário `documents:notify-expiring` → notifica gestores. Ex.: AVCB, alvarás, contratos.
- [x] **A2. Categorias customizáveis por tenant** ⭐ 🟢
  - Tabela `categories` (tipo: ocorrência/documento), CRUD em Configurações.
  - Mescla com as categorias padrão nos formulários/filtros (não quebra dados existentes).
- [x] **A14. IA: rascunho de resposta de ocorrência** 🟡 🟢
  - Botão "Sugerir resposta com IA" na ocorrência, reusando o `AssistantService` (RAG + contexto).
- [x] **A-VEÍCULOS. Cadastro de veículos na unidade** ⭐ 🟢
  - Veículos (placa, marca/modelo, cor, tipo, vaga) no formulário da unidade, no mesmo
    padrão dos pets do roster (ver `cadastro-unidade-roster`). Útil para portaria/controle de acesso.

### Fase B — Pacote "operação do síndico" (coração do Sindigest)
- [x] **B6. Cadastro de Fornecedores/Prestadores** + avaliação/rating + histórico ⭐ 🟡 — concluída (06/06/2026)
  - Entidade `suppliers` (tenant-wide), pivô `supplier_condominium`, histórico `supplier_evaluations`
    (nota média). Categoria via Categorias customizáveis (tipo `supplier`). Ver `docs/tecnico/fornecedores.md`.
- [x] **B4. Manutenção preventiva recorrente** ⭐ 🟡 — concluída (06/06/2026)
  - `maintenance_plans` + histórico `maintenance_records` (avanço automático da próxima data),
    categoria via Categorias customizáveis (tipo `maintenance`), alerta diário `maintenance:notify-due`.
    Ver `docs/tecnico/manutencao-preventiva.md`.
- [x] **B5. SLA/prazo em ocorrências** + acompanhamentos internos vs públicos + estatísticas ⭐ 🟡 — concluída (06/06/2026)
  - `due_at` (SLA por prioridade auto, configurável), alerta diário `occurrences:notify-sla`,
    `occurrence_comments.is_internal` (nota interna/pública) e painel `/ocorrencias/painel`.
    Ver `docs/tecnico/ocorrencias.md`. **Fase B concluída.**

### Fase C — Gestão de serviços e contas
- [x] **C7. Orçamentos/Cotações** (multi-fornecedor, comparar, aprovar, prazo, anexos) ⭐ 🔴 — concluída (07/06/2026)
  - `quotations` + `quotation_proposals`, anexos por `StorageObject`, aprovação transacional,
    conversão opcional em manutenção e/ou conta a pagar. Ver `docs/tecnico/orcamentos.md`.
- [ ] **C12. Obras** (orçamento aprovado vs final, cronograma, status) 🟡 🟡
- [x] **C8. Contas a pagar** (lembrete + categoria + nota fiscal) 🟡 🟡 — concluída (07/06/2026), estendendo "Despesas".
  - Integração operacional B4+B6+C8 concluída (07/06/2026): execução de manutenção pode gerar conta
    a pagar vinculada; fornecedor mostra manutenções, execuções e contas consolidadas.

### Fase D — Pessoas, relatórios e visão consolidada
- [ ] **D9. Funcionários + controle de férias** (CTPS, admissão, alertas de férias) ⭐ 🟡
- [ ] **D10. Relatórios consolidados multi-condomínio** configuráveis (módulos + período) ⭐ 🟡
- [ ] **D11. Preferências de notificação granulares por usuário** (matriz evento × canal) 🟡 🟡
- [ ] **D13. Cronograma consolidado** (calendário de manutenções + ocorrências + reservas) 🟡 🟡

### Booster de aquisição (encaixa em qualquer fase)
- [ ] **X3. Links públicos + QR por condomínio** para auto-cadastro de morador / abrir ocorrência,
  com moderação (aprovar/reprovar). ⭐ 🟡 — reusa portaria (QR) + portal.

## Princípios de execução
- Cada item entra por fôlego (1 fatia funcional por vez), com migration/model/controller/serviço/
  tela/doc, validado por `php -l` + `route:list` + `tsc` + `npm build` antes de commitar.
- Não quebrar dados nem funcionalidades existentes (ex.: A2 mantém o `category` string atual).
- Manter o padrão multitenant (tenant_id direto/indireto) e os limites por plano.
