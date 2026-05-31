# 10 — APIs e Chamadas de Rede Observáveis

> Gerado automaticamente em 31/05/2026, 11:51:48
> Total de rotas únicas capturadas: **23**

> ⚠️ **Nota de segurança:** Este documento não contém tokens, cookies, senhas ou dados pessoais.
> Apenas padrões de rotas foram registrados, com IDs substituídos por `:id` e `:uuid`.

---

## 1. Rotas Capturadas

| Método | Rota | Query? |
| --- | --- | --- |
| GET | `/overview` | Não |
| GET | `/overview/` | Não |
| GET | `/scripts/google-tag.js` | Não |
| GET | `/assets/index-BpBBexAp.js` | Não |
| GET | `https://www.sindigest.com.br/:uuid` | Não |
| GET | `https://www.sindigest.com.br/:ide-21a6-4c4d-b066-60b773d033a3` | Não |
| GET | `/issue/pending/client-manager` | Não |
| GET | `/issue/client-manager` | Não |
| GET | `/maintenance/pending/client-manager` | Não |
| GET | `/overview/statistics` | Não |
| GET | `/overview/activities_for_today` | Não |
| GET | `/overview/issues_by_user` | Não |
| GET | `/overview/recent_activities` | Não |
| GET | `/overview/documents` | Não |
| GET | `/service_budget` | Não |
| GET | `/v2/issues/saved-filters` | Não |
| GET | `/v2/maintenance/status` | Não |
| GET | `/assets/fonts/Gilroy/Gilroy-Regular.ttf` | Não |
| GET | `/assets/fonts/Gilroy/Gilroy-SemiBold.ttf` | Não |
| GET | `/assets/fonts/Gilroy/Gilroy-Bold.ttf` | Não |
| GET | `/assets/fonts/Gilroy/Gilroy-RegularItalic.ttf` | Não |
| GET | `/overview/null` | Não |
| GET | `/overview/null/` | Não |



---

## 2. Endpoints Sugeridos para o Novo SaaS

> Propostos com base nos módulos identificados. Implementar com REST + OpenAPI 3.0.

### Autenticação
```
POST   /api/v1/auth/login           # Login com e-mail e senha
POST   /api/v1/auth/refresh         # Renovar access token
POST   /api/v1/auth/logout          # Revogar token
POST   /api/v1/auth/forgot-password # Solicitar redefinição de senha
POST   /api/v1/auth/reset-password  # Redefinir senha com token
```

### Tenants e Planos
```
GET    /api/v1/tenants/:id          # Dados do tenant atual
PATCH  /api/v1/tenants/:id          # Atualizar configurações
GET    /api/v1/plans                # Listar planos disponíveis
```

### Usuários e Permissões
```
GET    /api/v1/users                # Listar usuários do tenant
POST   /api/v1/users                # Criar usuário
GET    /api/v1/users/:id            # Buscar usuário
PATCH  /api/v1/users/:id            # Atualizar usuário
DELETE /api/v1/users/:id            # Desativar usuário
GET    /api/v1/roles                # Listar perfis
POST   /api/v1/roles                # Criar perfil customizado
```

### Condomínios
```
GET    /api/v1/condominiums         # Listar condomínios do tenant
POST   /api/v1/condominiums         # Criar condomínio
GET    /api/v1/condominiums/:id     # Buscar condomínio
PATCH  /api/v1/condominiums/:id     # Atualizar condomínio
```

### Unidades e Pessoas
```
GET    /api/v1/condominiums/:id/units       # Listar unidades
POST   /api/v1/condominiums/:id/units       # Criar unidade
POST   /api/v1/units/:id/persons            # Vincular pessoa à unidade
DELETE /api/v1/units/:unit_id/persons/:id   # Desvincular pessoa
GET    /api/v1/persons                      # Buscar pessoas
POST   /api/v1/persons                      # Cadastrar pessoa
```

### Operação
```
GET    /api/v1/condominiums/:id/announcements    # Comunicados
POST   /api/v1/condominiums/:id/announcements    # Criar comunicado
GET    /api/v1/condominiums/:id/occurrences      # Ocorrências
POST   /api/v1/condominiums/:id/occurrences      # Registrar ocorrência
PATCH  /api/v1/occurrences/:id                   # Atualizar status
GET    /api/v1/condominiums/:id/reservations     # Reservas
POST   /api/v1/condominiums/:id/reservations     # Criar reserva
PATCH  /api/v1/reservations/:id/approve          # Aprovar reserva
GET    /api/v1/condominiums/:id/documents        # Documentos
POST   /api/v1/condominiums/:id/documents        # Upload de documento
```

### Financeiro
```
GET    /api/v1/condominiums/:id/charges          # Listar cobranças
POST   /api/v1/condominiums/:id/charges/generate # Gerar cobranças do mês
GET    /api/v1/condominiums/:id/charges/delinquency # Relatório inadimplência
POST   /api/v1/charges/:id/mark-paid             # Marcar como pago
```

### Notificações
```
GET    /api/v1/notifications        # Listar notificações do usuário
PATCH  /api/v1/notifications/:id/read # Marcar como lida
POST   /api/v1/notifications/read-all # Marcar todas como lidas
```

---

> **Próximo passo:** Execute `npm run phase:11` para gerar a proposta do novo SaaS.
