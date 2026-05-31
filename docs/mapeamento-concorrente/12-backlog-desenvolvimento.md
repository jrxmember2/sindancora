# 12 — Backlog de Desenvolvimento

> Gerado automaticamente em 31/05/2026, 11:51:48

**Complexidade:** S=horas | M=1-3 dias | L=1 semana | XL=2+ semanas
**Prioridade:** P0=bloqueante | P1=alta | P2=média | P3=baixa

---

## Fase 1 — Base do SaaS

### Autenticação

| Feature | Prioridade | Complexidade | Dependências | Critério de Aceite |
| --- | --- | --- | --- | --- |
| Login com e-mail e senha | P0 | M | — | Login funcional com JWT + refresh token |
| Recuperação de senha por e-mail | P0 | S | Login | Token de reset enviado por e-mail |
| 2FA opcional (TOTP) | P2 | M | Login | QR code gerado e validado via app autenticador |
| Logout e revogação de sessão | P0 | S | Login | Token invalidado no servidor |

### Tenants e Planos

| Feature | Prioridade | Complexidade | Dependências | Critério de Aceite |
| --- | --- | --- | --- | --- |
| Cadastro de tenant (onboarding) | P0 | M | — | Tenant criado com subdomínio funcional |
| Configurações do tenant (logo, cores) | P1 | M | Tenant | Painel de configurações white-label |
| Gerenciamento de planos (super admin) | P0 | M | Tenant | CRUD de planos com limites |
| Verificação de limites por plano | P0 | S | Planos | Erro ao ultrapassar limite |

### Usuários e Permissões (RBAC)

| Feature | Prioridade | Complexidade | Dependências | Critério de Aceite |
| --- | --- | --- | --- | --- |
| CRUD de usuários por tenant | P0 | M | Tenant | Admin cria/edita/desativa usuários |
| Roles predefinidos (admin, síndico, morador) | P0 | M | Usuários | Roles criadas no seed do sistema |
| Atribuição de role a usuário | P0 | S | Roles | Usuário com role e acesso correspondente |
| Roles customizadas por tenant | P2 | L | RBAC base | Admin cria role com permissões granulares |

### Condomínios

| Feature | Prioridade | Complexidade | Dependências | Critério de Aceite |
| --- | --- | --- | --- | --- |
| CRUD de condomínios | P0 | M | Tenant | Admin cria/edita condomínio com todos os dados |
| Gestão de blocos/torres | P1 | S | Condomínio | Blocos vinculados ao condomínio |
| Configurações por condomínio | P1 | M | Condomínio | Configurações independentes por condomínio |

### Unidades

| Feature | Prioridade | Complexidade | Dependências | Critério de Aceite |
| --- | --- | --- | --- | --- |
| CRUD de unidades | P0 | M | Condomínio | Unidade criada com número, andar, tipo |
| Importação de unidades via CSV | P1 | M | Unidades | Arquivo CSV importado com validação |
| Status da unidade (ocupada/vazia/obras) | P1 | S | Unidades | Status atualizado manualmente ou automático |

### Pessoas e Vínculos

| Feature | Prioridade | Complexidade | Dependências | Critério de Aceite |
| --- | --- | --- | --- | --- |
| Cadastro de pessoas (CPF único) | P0 | M | Unidades | Pessoa criada sem duplicidade de CPF |
| Vínculo pessoa ↔ unidade com tipo | P0 | M | Pessoas | Vínculo criado com tipo e datas |
| Histórico de moradores por unidade | P1 | S | Vínculos | Listagem de vínculos ativos e passados |
| Convite para portal do morador | P1 | M | Pessoas | E-mail de convite com link de cadastro |

### Dashboard

| Feature | Prioridade | Complexidade | Dependências | Critério de Aceite |
| --- | --- | --- | --- | --- |
| Dashboard do admin com KPIs | P1 | M | Condomínios, Unidades | Cards: condomínios, unidades, moradores, inadimplência |
| Dashboard do síndico | P1 | M | Dashboard admin | KPIs do condomínio específico |

## Fase 2 — Operação Condominial

### Comunicados

| Feature | Prioridade | Complexidade | Dependências | Critério de Aceite |
| --- | --- | --- | --- | --- |
| CRUD de comunicados | P0 | M | — | Comunicado criado e publicado |
| Segmentação de público-alvo | P0 | M | Comunicados | Envio para todos/bloco/unidade/perfil |
| Agendamento de publicação | P1 | S | Comunicados | Comunicado publicado na data/hora definida |
| Confirmação de leitura | P2 | M | Comunicados | Indicador de % que leu |

### Ocorrências

| Feature | Prioridade | Complexidade | Dependências | Critério de Aceite |
| --- | --- | --- | --- | --- |
| Registro de ocorrência (morador/admin) | P0 | M | — | Ocorrência criada com categoria e status |
| Histórico de atualizações | P0 | S | Ocorrências | Comentários registrados com data/usuário |
| Notificação de atualização de status | P1 | S | Ocorrências, Notificações | Morador notificado ao mudar status |
| SLA automático com alertas | P2 | M | Ocorrências | Alerta ao ultrapassar prazo configurado |

### Reservas

| Feature | Prioridade | Complexidade | Dependências | Critério de Aceite |
| --- | --- | --- | --- | --- |
| Cadastro de áreas comuns | P0 | M | — | Área criada com capacidade e regras |
| Calendário de disponibilidade | P0 | L | Áreas | Calendário visual sem conflitos |
| Solicitação e aprovação de reserva | P0 | M | Calendário | Fluxo de aprovação funcional |
| Cobrança de taxa de reserva | P2 | M | Reservas, Financeiro | Taxa cobrada ao aprovar reserva |

### Documentos

| Feature | Prioridade | Complexidade | Dependências | Critério de Aceite |
| --- | --- | --- | --- | --- |
| Upload e gestão de documentos | P0 | M | — | Upload funcional com S3 |
| Categorização e busca | P1 | S | Documentos | Filtro por categoria e busca por nome |
| Controle de acesso por documento | P1 | S | Documentos | Documento público ou restrito |

### Notificações

| Feature | Prioridade | Complexidade | Dependências | Critério de Aceite |
| --- | --- | --- | --- | --- |
| Notificações in-app (sino) | P0 | M | — | Lista de notificações com leitura |
| Notificações por e-mail | P0 | M | — | E-mail disparado nos eventos principais |
| Notificações push (PWA/app) | P2 | L | App | Push recebido no mobile |

## Fase 3 — Financeiro

### Cobranças

| Feature | Prioridade | Complexidade | Dependências | Critério de Aceite |
| --- | --- | --- | --- | --- |
| Geração de cobranças mensais | P0 | L | Unidades | Cobranças geradas para todas as unidades |
| Registro manual de pagamento | P0 | S | Cobranças | Pagamento marcado como pago manualmente |
| Multa e juros automáticos | P1 | M | Cobranças | Cálculo correto conforme regras configuradas |
| Emissão de boleto bancário | P1 | XL | Integração bancária | Boleto gerado e enviado ao morador |
| Pagamento via PIX | P1 | L | Integração bancária | QR Code PIX gerado por cobrança |

### Relatórios Financeiros

| Feature | Prioridade | Complexidade | Dependências | Critério de Aceite |
| --- | --- | --- | --- | --- |
| Relatório de inadimplência | P0 | M | Cobranças | Lista de unidades em débito com valores |
| Prestação de contas mensal | P1 | L | Cobranças | Relatório de receitas e despesas |
| Exportação PDF/Excel | P1 | M | Relatórios | Download gerado corretamente |

## Fase 4 — Portal do Morador

### Portal Web/App

| Feature | Prioridade | Complexidade | Dependências | Critério de Aceite |
| --- | --- | --- | --- | --- |
| Login do morador | P0 | S | Auth | Morador loga e vê painel próprio |
| Visualizar comunicados | P0 | S | — | Comunicados do condomínio exibidos |
| Fazer reserva de área comum | P0 | M | Reservas | Morador reserva pelo portal |
| Registrar ocorrência | P0 | M | Ocorrências | Morador abre ocorrência pelo portal |
| Acessar documentos públicos | P0 | S | Documentos | Morador baixa documentos |
| Ver cobranças e segunda via | P1 | M | Financeiro | Morador vê boletos e paga online |

## Fase 5 — IA e Automações

### IA Assistente

| Feature | Prioridade | Complexidade | Dependências | Critério de Aceite |
| --- | --- | --- | --- | --- |
| Chat IA para síndico (Claude API) | P1 | L | Fase 1+2 | Síndico conversa com IA sobre o condomínio |
| Consulta em documentos (RAG) | P2 | XL | Documentos, IA | IA responde com base nos documentos do condo |
| Análise automática de inadimplência | P2 | L | Financeiro, IA | IA sugere ações para reduzir inadimplência |

### Automações (n8n)

| Feature | Prioridade | Complexidade | Dependências | Critério de Aceite |
| --- | --- | --- | --- | --- |
| Lembrete de vencimento via WhatsApp | P1 | M | WhatsApp, Cobranças | Mensagem enviada 3 dias antes do vencimento |
| Alerta de ocorrência aberta há X dias | P2 | S | Ocorrências | Notificação automática ao síndico |
| Boas-vindas ao novo morador | P2 | S | Vínculos | Mensagem de boas-vindas ao vincular morador |

---

## Checklist do MVP (Fase 1 + 2 básico)

- [ ] Auth: login, logout, recuperação de senha
- [ ] Tenant: cadastro, configurações básicas
- [ ] Planos: 2 planos (Starter e Pro)
- [ ] Usuários: CRUD com roles básicos
- [ ] Condomínio: CRUD
- [ ] Blocos/Torres: CRUD
- [ ] Unidades: CRUD + importação CSV
- [ ] Pessoas: CRUD + vínculo com unidade
- [ ] Dashboard: KPIs básicos
- [ ] Comunicados: criação e publicação
- [ ] Ocorrências: registro e atualização de status
- [ ] Reservas: CRUD de áreas + solicitação
- [ ] Documentos: upload e download
- [ ] Notificações: in-app + e-mail
- [ ] Portal do morador: login + comunicados + ocorrências
- [ ] Deploy: ambiente de produção estável

**Estimativa total MVP:** 3-4 meses com time de 2-3 devs

---

> **Próximo passo:** Execute `npm run phase:13` para gerar o resumo executivo final.
