# 11 — Proposta do Novo SaaS Multitenant Condominial

> Documento estratégico — 31/05/2026, 11:51:48

---

## 1. Visão do Produto

**Nome sugerido:** CondoHub (ou escolha sua marca)
**Posicionamento:** SaaS white-label multitenant para gestão condominial completa, revendável a administradoras e síndicos.
**Diferenciais vs. mercado:**
- White-label real (logo, cores, domínio próprio por tenant)
- Módulos contratáveis — cliente paga só o que usa
- IA integrada: assistente para síndico, análise de inadimplência, auto-resposta de ocorrências
- Portal do morador moderno (web + app)
- Integração nativa com WhatsApp, PIX e bancos

---

## 2. Arquitetura do Sistema

### 2.1 Stack Recomendada

| Camada | Tecnologia | Justificativa |
| --- | --- | --- |
| Backend API | Node.js + Fastify ou NestJS | Alta performance, ecosistema rico, TypeScript nativo |
| Banco de Dados | PostgreSQL + RLS | Multitenancy seguro com Row-Level Security |
| Cache | Redis | Sessões, rate limit, jobs em fila |
| Filas | BullMQ (Redis) | E-mails, notificações, relatórios assíncronos |
| Storage | S3 / Cloudflare R2 | Documentos e fotos escaláveis e baratos |
| Frontend | Next.js + TailwindCSS | SSR, SEO, componentização moderna |
| App Mobile | React Native / Expo | Portal do morador mobile — fase 4+ |
| Auth | JWT + Refresh Tokens | Stateless, escalável, padrão de mercado |
| E-mail | Resend / SendGrid | Entrega confiável, templates HTML |
| WhatsApp | Evolution API / WPPConnect | Mensagens, notificações, boletos |
| Pagamentos | Pagar.me / Asaas / Stripe | Boleto, PIX, cartão — múltiplos gateways |
| Deploy | Docker + Kubernetes ou Railway | Escalável, multi-região |
| CI/CD | GitHub Actions | Automatização de deploys e testes |
| Monitoramento | Sentry + Grafana | Erros em produção + métricas de negócio |



### 2.2 Arquitetura de Multitenancy

```
[Internet]
    │
[CDN — Cloudflare]
    │
[Load Balancer]
    │
┌───────────────────────────────────────────────────────┐
│                    API Gateway                        │
│  (rate limit, auth, tenant resolution, logging)       │
└───────────────────────────────────────────────────────┘
    │
[Auth Service]  [Core Service]  [Notification Service]
    │                │                    │
    └────────────────┴────────────────────┘
                     │
           [PostgreSQL — RLS por tenant_id]
                     │
           [Redis]  [S3/R2]  [BullMQ]
```

### 2.3 Resolução de Tenant

```
1. Subdomínio:  sindico.meusaas.com.br → tenant "sindico"
2. Domínio próprio: app.administradora.com.br → tenant via mapeamento DNS
3. JWT claim: tenant_id no payload do token
```

---

## 3. Painéis e Perfis de Acesso

| Painel             | Quem usa          | URL             | Descrição                                     |
|--------------------|-------------------|-----------------|-----------------------------------------------|
| Super Admin        | Equipe do SaaS    | admin.saas.com  | Gerencia tenants, planos, suporte global      |
| Administradora     | Funcionários      | tenant.saas.com | Gerencia todos os condomínios do cliente      |
| Síndico            | Síndico eleito    | tenant.saas.com | Gerencia o condomínio específico              |
| Portal do Morador  | Condôminos        | morador.saas.com| Comunicados, boletos, reservas, ocorrências   |
| Portaria           | Porteiros         | portaria.saas.com| Controle de acesso, visitantes               |

---

## 4. Módulos Contratáveis

| Módulo | Incluído no Básico | Add-on Pago | Descrição |
| --- | --- | --- | --- |
| Core (Auth + Tenant + Config) | ✅ | — | Obrigatório em todos os planos |
| Condomínios + Unidades + Pessoas | ✅ | — | Base operacional |
| Comunicados | ✅ | — | Comunicados para moradores |
| Documentos | ✅ | — | Gestão de documentos |
| Ocorrências | ✅ | — | Registro e acompanhamento |
| Reservas | ✅ | — | Áreas comuns |
| Portal Morador | ✅ | — | App/web para moradores |
| Financeiro | ❌ | 💰 | Cobranças, inadimplência, relatórios |
| Boletos/PIX | ❌ | 💰 | Integração bancária real |
| Assembleias | ❌ | 💰 | Votação online, atas automáticas |
| WhatsApp | ❌ | 💰 | Notificações via WhatsApp |
| IA Assistente | ❌ | 💰 | Chat IA para síndico |
| Portaria Digital | ❌ | 💰 | Controle de acesso, visitantes, QR Code |



---

## 5. Planos de Assinatura

| Plano | Condomínios | Unidades | Usuários | Módulos | Preço/mês |
| --- | --- | --- | --- | --- | --- |
| Starter | 1 | até 50 | 5 | Core + básicos | R$ 149 |
| Profissional | 3 | até 200 | 15 | + Financeiro | R$ 349 |
| Business | 10 | até 1000 | 50 | + WhatsApp + IA | R$ 749 |
| Enterprise | Ilimitado | Ilimitado | Ilimitado | Tudo + white-label | Sob consulta |



---

## 6. White-Label

- Domínio próprio (CNAME configurado pelo cliente)
- Logo, cores primárias e secundárias configuráveis por tenant
- E-mails com identidade visual do cliente
- App mobile com nome e ícone customizados (por contrato)
- "Powered by" removível no plano Enterprise

---

## 7. LGPD e Segurança

- Consentimento na criação de conta (moradores)
- Exportação de dados do titular (DSAR — Data Subject Access Request)
- Exclusão de conta e dados (direito ao esquecimento)
- Dados de moradores anonimizados em relatórios exportados
- Logs de acesso retidos por 12 meses
- Backups diários com criptografia em repouso
- HTTPS obrigatório (HSTS)
- 2FA opcional para administradores
- Rate limiting e detecção de brute force

---

## 8. Integrações

| Categoria   | Serviço           | Finalidade                          |
|-------------|-------------------|-------------------------------------|
| Pagamento   | Asaas / Pagar.me  | Boleto, PIX, cartão                 |
| Pagamento   | Banco Inter API   | Emissão de boletos                  |
| Mensagens   | Evolution API     | WhatsApp Business                   |
| E-mail      | Resend / SendGrid | Transacional e marketing            |
| IA          | Anthropic Claude  | Assistente para síndico             |
| Automações  | n8n               | Fluxos automatizados                |
| Endereço    | ViaCEP            | Autocomplete de endereço            |
| Fiscal      | Receita Federal   | Validação de CNPJ                   |
| Storage     | Cloudflare R2     | Documentos e fotos                  |

---

## 9. Roadmap de Desenvolvimento

```
Q1 — Base do SaaS (auth, tenants, condomínios, unidades, pessoas)
Q2 — Operação (comunicados, ocorrências, reservas, documentos)
Q3 — Financeiro + portal do morador (web)
Q4 — App mobile + WhatsApp + assembleias
Q5 — IA assistente + portaria digital + relatórios avançados
```

---

> **Próximo passo:** Execute `npm run phase:12` para gerar o backlog detalhado.
