# 00 — Resumo Executivo

> Documento consolidado — 31/05/2026, 11:51:48
> Análise de: **Sindigest** (sindigest.com.br)
> Perfil analisado: **Administrador**

---

## 1. Resumo do Sistema Analisado

O Sindigest é um sistema SaaS voltado à gestão condominial, acessado via web com perfis diferenciados (administrador, síndico, morador). O sistema possui interface web tradicional com autenticação por e-mail e senha.

**Dados do Mapeamento:**

| Métrica | Valor |
|---|---|
| Telas mapeadas | 0 |
| Módulos identificados | 0 |
| Formulários mapeados | 0 |
| APIs capturadas | 23 |
| Screenshots gerados | Ver `docs/screenshots/` |

---

## 2. Principais Módulos Encontrados

> Execute as fases de automação para preencher esta seção automaticamente.

Módulos esperados para o domínio:
1. **Dashboard**
2. **Condomínios**
3. **Unidades**
4. **Moradores / Proprietários / Locatários**
5. **Comunicados**
6. **Ocorrências**
7. **Reservas de Áreas Comuns**
8. **Documentos**
9. **Financeiro / Cobranças**
10. **Relatórios**
11. **Usuários e Permissões**
12. **Configurações**

---

## 3. Principais Regras de Negócio

- Hierarquia: Administradora → Condomínio → Bloco → Unidade → Pessoa
- Pessoa pode ter vínculo com múltiplas unidades e múltiplos tipos (proprietário, morador, locatário)
- Módulos de operação (comunicados, ocorrências, reservas) operam por condomínio
- Sistema multitenant: dados completamente isolados por administradora/tenant
- Perfil Administrador tem acesso total; outros perfis têm acesso restrito
- Comunicados podem segmentar por bloco, unidade ou perfil
- Ocorrências têm ciclo de vida: Aberta → Em Andamento → Encerrada
- Reservas requerem aprovação quando configurado com taxa ou capacidade limitada

---

## 4. Principais Entidades Identificadas

```
Tenant (Administradora)
  ├── Users (com Roles)
  ├── Condominiums
  │     ├── Blocks
  │     ├── Units
  │     │     └── UnitPersons → Persons
  │     ├── Announcements
  │     ├── Occurrences
  │     ├── CommonAreas → Reservations
  │     ├── Documents
  │     └── Charges
  └── AuditLogs
```

---

## 5. Oportunidades de Melhoria

1. **UX moderna** — React/Next.js com design system consistente
2. **App mobile** — portal do morador nativo (iOS + Android)
3. **WhatsApp nativo** — notificações e segunda via por WhatsApp
4. **PIX automático** — geração de QR Code por cobrança
5. **IA para síndico** — assistente que responde dúvidas sobre o condomínio
6. **Calendário de reservas visual** — interface drag-and-drop
7. **Assembleias online** — votação digital com validação de voto
8. **Portaria digital** — controle de visitantes com QR Code
9. **Relatórios avançados** — dashboards analíticos com gráficos
10. **Importação de dados** — migração fácil de outros sistemas

---

## 6. Proposta de Arquitetura do Novo SaaS

```
Backend:  Node.js + NestJS + PostgreSQL (RLS) + Redis + BullMQ
Frontend: Next.js + TailwindCSS + shadcn/ui
Mobile:   React Native + Expo (Fase 4)
Auth:     JWT + Refresh Tokens + 2FA opcional
Storage:  Cloudflare R2 (documentos e fotos)
Infra:    Docker + Railway/Render (MVP) → Kubernetes (escala)
CI/CD:    GitHub Actions
Monitor:  Sentry + Grafana
```

---

## 7. Módulos Prioritários para MVP

| Prioridade | Módulo | Justificativa |
|---|---|---|
| P0 | Auth + Tenants + Usuários | Base de tudo |
| P0 | Condomínios + Unidades + Pessoas | Cadastro fundamental |
| P0 | Dashboard básico | Valor imediato ao usuário |
| P1 | Comunicados | Alta demanda e fácil de implementar |
| P1 | Ocorrências | Resolve dor real dos síndicos |
| P1 | Reservas | Muito solicitado pelos condomínios |
| P1 | Documentos | Simples e muito valorizado |
| P1 | Portal do Morador (web) | Diferencial de produto |
| P2 | Financeiro básico (cobranças) | Monetização |

---

## 8. Riscos Técnicos

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| Integração bancária complexa | Alta | Alto | Usar Asaas/Pagar.me como gateway |
| Escalabilidade do storage | Média | Alto | Cloudflare R2 desde o início |
| Concorrência em reservas | Média | Médio | Locks otimistas no banco |
| Performance com muitos tenants | Baixa | Alto | RLS + índices compostos desde o início |
| Segurança de dados (LGPD) | Alta | Alto | DPO, privacidade by design |

---

## 9. Riscos Jurídicos e Comerciais

- **Concorrência:** O mercado de gestão condominial é competitivo (Superlogica, Habitissimo, etc.)
- **LGPD:** Dados de moradores são dados pessoais — exige DPO, política de privacidade, consentimento
- **Uso da análise:** Este mapeamento foi feito com acesso autorizado para fins de referência funcional — não usa código, marca ou dados do sistema original
- **Contratos:** Definir claramente SLA, responsabilidade sobre dados, backup e disaster recovery para clientes
- **Cobrança:** Modelos de cobrança devem ser transparentes; evitar lock-in de dados (portabilidade)

---

## 10. Próximos Passos Recomendados

1. ✅ Revisar os 12 documentos gerados e completar lacunas manualmente
2. 🔍 Navegar manualmente com `HEADLESS=false` para confirmar módulos e regras
3. 📐 Validar modelo de dados com DBA antes de iniciar implementação
4. 🏗️ Iniciar projeto com auth + tenant + usuários (Fase 1 do backlog)
5. 🎨 Contratar design system antes de construir frontend
6. 📱 Planejar app mobile desde o início (compartilhamento de tipos e API)
7. 💰 Definir pricing e validar com potenciais clientes (síndicos/administradoras)
8. 🔒 Engajar advogado para LGPD e termos de serviço

---

## Árvore de Módulos

```
SaaS Condominial
├── 🔐 Auth & Segurança
│   ├── Login / Logout / 2FA
│   └── Recuperação de Senha
├── 🏢 Gestão de Tenants
│   ├── Onboarding
│   ├── White-label
│   └── Planos e Limites
├── 👥 Usuários & RBAC
│   ├── Cadastro de Usuários
│   ├── Perfis e Roles
│   └── Permissões Granulares
├── 🏘️ Condomínios
│   ├── Cadastro e Configurações
│   ├── Blocos/Torres
│   ├── Unidades
│   └── Pessoas & Vínculos
├── 📢 Operação
│   ├── Comunicados
│   ├── Ocorrências
│   ├── Reservas de Áreas
│   └── Documentos
├── 💰 Financeiro
│   ├── Cobranças
│   ├── Boleto/PIX
│   ├── Inadimplência
│   └── Relatórios
├── 📱 Portal do Morador
│   ├── Web
│   └── App Mobile (futuro)
├── 🔔 Notificações
│   ├── In-app
│   ├── E-mail
│   └── WhatsApp (add-on)
└── 🤖 IA & Automações (futuro)
    ├── Assistente para Síndico
    ├── Consulta em Documentos
    └── Automações n8n
```

---

*Documentação gerada por mapeamento automatizado via Playwright.*
*Todos os dados pessoais de moradores foram omitidos conforme boas práticas de LGPD.*
