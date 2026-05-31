# 00 — Leitura e Análise das Documentações de Referência

> Elaborado em: 31/05/2026
> Base: 13 documentos em `docs/mapeamento-concorrente/`
> Finalidade: referência de domínio e mercado — **proibido copiar código, telas, marca ou dados proprietários**

---

## 1. O Que Foi Observado

### 1.1 Sistema de Referência

O sistema analisado é o **Sindigest** (sindigest.com.br), que se revelou durante o mapeamento como um **white-label do Gcondo** (gcondo.com.br). As chamadas de API observadas apontam para `api.gcondo.com.br/v2/`, e o frontend apresenta assets, fontes e referências internas ao Gcondo. Isso indica que o Sindigest é uma instância revendida do SaaS Gcondo.

Essa descoberta é relevante porque confirma que o modelo de white-label funciona comercialmente nesse mercado — e o SindÂncora deve estar preparado para isso desde o início.

### 1.2 Dados Concretos do Mapeamento Automatizado

| Item | Dado |
|---|---|
| Sistema real por trás | Gcondo (gcondo.com.br) |
| API base | `api.gcondo.com.br/v2/` |
| Rotas únicas capturadas | 23 |
| Fonte tipográfica usada | Gilroy (proprietária) |
| Monitoramento de erros | Sentry (React 8.55.2) |
| Analytics | Google Tag Manager / GA4 |
| IA do concorrente | "MarIA" (botão observado no dashboard) |
| Telas mapeadas com detalhe | 0 (automação limitada) |
| Módulos identificados com detalhe | 0 (automação limitada) |

### 1.3 Dashboard — Indicadores Identificados

A tela de dashboard (overview) exibiu os seguintes cards, indicando os módulos principais:

| Card | Valor de Exemplo | Interpretação |
|---|---|---|
| Chamados | 81 Atrasados | Módulo de ocorrências/chamados |
| À vencer | 13 | Cobranças próximas do vencimento |
| Pendente sem prazo | 7 | Tarefas/manutenções sem data |

### 1.4 Rotas de API Observadas

As rotas capturadas revelam a estrutura de módulos do sistema:

| Rota | Módulo Inferido |
|---|---|
| `/overview/statistics` | Dashboard — KPIs |
| `/overview/activities_for_today` | Dashboard — Atividades |
| `/overview/recent_activities` | Dashboard — Histórico |
| `/overview/documents` | Documentos no dashboard |
| `/issue/pending/client-manager` | Ocorrências/chamados pendentes |
| `/issue/client-manager` | Listagem de ocorrências |
| `/maintenance/pending/client-manager` | Manutenções pendentes |
| `/v2/maintenance/status` | Status de manutenções |
| `/v2/issues/saved-filters` | Filtros salvos de ocorrências |
| `/service_budget` | Orçamento de serviços |

---

## 2. O Que Foi Inferido

Com base nos documentos de referência, nas rotas observadas e nos padrões do domínio condominial, foram inferidos os seguintes aspectos:

### 2.1 Hierarquia de Dados

```
Tenant (Administradora ou Síndico)
  └── Condomínio
        ├── Bloco/Torre
        │     └── Unidade
        │           └── Vínculo de Pessoa (proprietário, locatário, morador, dependente)
        ├── Comunicados
        ├── Ocorrências (chamados)
        ├── Manutenções
        ├── Reservas de Áreas Comuns
        ├── Documentos
        └── Cobranças (por unidade)
```

### 2.2 Perfis de Acesso

| Perfil | Acesso |
|---|---|
| Administrador (Administradora) | Acesso total ao tenant |
| Síndico | Acesso operacional ao condomínio |
| Subsíndico / Conselheiro | Acesso de leitura amplo, ações limitadas |
| Morador / Condômino | Portal próprio — dados, comunicados, reservas, ocorrências |
| Porteiro | Módulo de controle de acesso (futura fase) |

### 2.3 Ciclos de Vida dos Módulos Operacionais

- **Ocorrência:** `Aberta → Em Andamento → Encerrada`
- **Reserva:** `Pendente → Aprovada → Realizada` / `Cancelada`
- **Cobrança:** `Em Aberto → Pago` / `Vencido → Cancelado`
- **Manutenção:** `Solicitada → Em Andamento → Concluída`

### 2.4 Modelo de Multitenancy

O sistema opera por tenant (administradora ou síndico), com dados completamente isolados. A resolução de tenant se dá por contexto de login — o usuário pertence a um tenant e só visualiza dados deste tenant.

---

## 3. O Que Está Incompleto

Os documentos de mapeamento apresentam limitações significativas porque a automação via Playwright não conseguiu navegar pelas telas protegidas após login:

| Lacuna | Impacto |
|---|---|
| Formulários não capturados (doc 04 vazio) | Precisamos definir todos os campos do zero |
| Módulos sem detalhamento (doc 03 vazio) | Levantamento baseado apenas em inferência |
| Navegação não mapeada (doc 02 vazio) | Não sabemos o fluxo real de navegação |
| Regras financeiras não confirmadas | Modelo de cobranças/boletos incerto |
| Integrações bancárias desconhecidas | Não sabemos quais gateways o concorrente usa |
| Modelo de planos não confirmado | Pricing inferido, não verificado |
| Módulo de assembleias | Existência não confirmada |
| Portaria digital | Existência não confirmada |

**Conclusão:** Os documentos são suficientes para entender o **domínio do negócio** e os **padrões de mercado**, mas não revelam detalhes de implementação, modelo de banco de dados real, segredos técnicos ou código do concorrente. Isso é positivo — o SindÂncora será construído integralmente do zero.

---

## 4. Módulos que Fazem Sentido para o SindÂncora

Com base na análise e nos padrões de mercado condominial brasileiro:

### MVP — Fase 1 e 2 (indispensáveis)

| Módulo | Justificativa |
|---|---|
| Autenticação e sessão | Base de tudo |
| Tenants e planos | Essencial para SaaS |
| RBAC — perfis e permissões | Segurança e isolamento |
| Condomínios | Cadastro fundamental |
| Blocos/Torres | Necessário para condomínios grandes |
| Unidades | Core do sistema |
| Pessoas e vínculos | Proprietários, moradores, locatários |
| Dashboard com KPIs | Valor imediato |
| Comunicados | Alta demanda, fácil de implementar |
| Ocorrências/chamados | Resolve dor real dos síndicos |
| Reservas de áreas comuns | Muito solicitado |
| Documentos | Simples e valorizado |
| Notificações (in-app + e-mail) | Necessário para todas as ações |
| Auditoria básica | Segurança e LGPD |
| Controle de storage por tenant | SaaS exige desde o início |

### Fase 3 — Importante para retenção

| Módulo | Justificativa |
|---|---|
| Financeiro — cobranças | Monetização direta para cliente |
| Inadimplência | Dor real dos administradores |
| Relatórios exportáveis | Valorizado pelas administradoras |
| Portal do morador | Diferencial de experiência |

### Fases Futuras — Diferenciais de mercado

| Módulo | Justificativa |
|---|---|
| Manutenções/serviços | Observado no concorrente como módulo próprio |
| PIX/boleto via gateway | Monetização avançada |
| WhatsApp (Evolution API) | Diferencial em notificações |
| Assembleias digitais | Votação online — diferencial |
| Portaria digital | Controle de acesso e visitantes |
| IA assistente para síndico | Diferencial premium |
| API pública + webhooks | Integração com outros sistemas |
| App mobile do morador | Portal nativo iOS/Android |

---

## 5. Riscos Técnicos

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| Integração bancária complexa (boleto/PIX) | Alta | Alto | Usar Asaas ou Pagar.me como gateway; adiar para Fase 5 |
| Escalabilidade do storage com muitos tenants | Média | Alto | Cloudflare R2 desde o início; controle de quota por tenant |
| Concorrência em reservas de áreas | Média | Médio | Transações com lock otimista no PostgreSQL |
| Vazamento de dados entre tenants | Baixa | Crítico | tenant_id em todas as queries; policies no Laravel; middleware de verificação |
| Performance com muitos tenants + dados | Baixa | Alto | Índices compostos (tenant_id, campo_buscado) desde o início |
| Multitenancy com subdomínios | Média | Médio | Configuração de wildcard DNS + resolução via middleware |
| Envio massivo de notificações (e-mail/WhatsApp) | Média | Médio | Filas Redis/Laravel Horizon; rate limit por tenant |
| LGPD — dados pessoais de moradores | Alta | Alto | Arquitetura privacidade-by-design; DPO; termos de uso |
| Cálculo de taxa de armazenamento por tenant | Baixa | Médio | Tabela `storage_objects` com file_size + snapshots periódicos |

---

## 6. Riscos Jurídicos e Comerciais

| Risco | Descrição | Mitigação |
|---|---|---|
| LGPD | Sistema processa dados pessoais de moradores (nome, CPF, e-mail, endereço, boletos) | Implementar consentimento, política de privacidade, exportação e exclusão de dados; indicar DPO |
| Propriedade intelectual | Não copiar código, telas, marca, fontes ou identidade do concorrente | Construir tudo do zero; uso de fontes livres (Inter, Geist, etc.) |
| Responsabilidade sobre dados do cliente | O SindÂncora armazena dados de terceiros (moradores dos clientes) | Contrato de processamento de dados (DPA) com cada tenant |
| Lock-in de dados | Clientes podem exigir portabilidade ao migrar | Exportação de dados em CSV/JSON desde o início |
| SLA e disponibilidade | Clientes condominiais têm operação crítica (portaria, cobranças) | Definir SLA claro; healthcheck; backup automático |
| Cobrança recorrente | Modelos de assinatura exigem clareza nos termos | Evitar renovação automática sem aviso; política de cancelamento clara |
| Concorrência desleal | O mercado tem players grandes (Superlogica, Habitissimo, Condomínio21) | Focar em diferenciais reais; não prometer o que não entrega |

---

## 7. Melhorias para Diferenciar o SindÂncora

Com base nas lacunas e oportunidades observadas no concorrente:

| Diferencial | Descrição | Impacto |
|---|---|---|
| **White-label real** | Logo, cores, domínio próprio configurável por tenant no painel | Permite revenda B2B para administradoras |
| **Módulos contratáveis** | Cliente paga apenas os módulos que usa | Ticket menor para entrar, maior para crescer |
| **UX moderna e responsiva** | Interface construída com React + design system consistente | Experiência muito superior ao legado do mercado |
| **Portal do morador impecável** | Web PWA e futuro app nativo | Reduz ligações para a administradora |
| **Controle de storage transparente** | Dashboard de uso de armazenamento por tenant | Confiança e modelo de cobrança adicional |
| **API pública desde o início** | Integrações com outros sistemas via API Key por tenant | Abertura para ecossistema e integrações |
| **WhatsApp nativo (Fase 3+)** | Notificações e segunda via por WhatsApp | Diferencial forte no mercado brasileiro |
| **PIX automático (Fase 5)** | QR Code PIX por cobrança, conciliação automática | Fluxo financeiro moderno e sem fricção |
| **IA para o síndico (Fase 6)** | Assistente que responde dúvidas sobre documentos e inadimplência | Diferencial premium com Claude API |
| **Calendário visual de reservas** | Interface com drag-and-drop e visualização mensal/semanal | UX muito superior ao modelo de lista |
| **Importação fácil de dados** | CSV/XLSX para unidades e pessoas; wizard de migração | Reduz barreira de entrada |
| **Convite por e-mail/WhatsApp** | Morador recebe convite para ativar acesso ao portal | Ativação de moradores sem fricção |
| **Auditoria completa** | Log de todas as ações com quem fez, quando e o que mudou | Compliance, LGPD e confiança |
| **Manutenções como módulo separado** | Orçamentos, prestadores, status de manutenções | Observado no concorrente como diferencial |
| **Assembleias digitais (Fase 4+)** | Votação online com validação por unidade | Diferencial em condomínios com assembleias frequentes |

---

## 8. Conclusão da Análise

O mapeamento confirma que o mercado de gestão condominial SaaS no Brasil é **ativo, com demanda real e modelos de negócio validados**. O sistema de referência (Gcondo/Sindigest) atende às necessidades básicas do mercado, mas apresenta oportunidades claras:

1. A interface provavelmente não é moderna (padrão do mercado condominial ainda é muito legado);
2. A experiência do morador é limitada;
3. Não há evidência de white-label real acessível;
4. Integrações com WhatsApp, PIX e IA são diferenciais ainda não commoditizados no segmento.

O **SindÂncora** deve ser construído como um produto superior em UX, com arquitetura moderna, white-label desde o início, e com um roadmap claro para adicionar os diferenciais de IA, WhatsApp e PIX nas fases seguintes.

---

*Documento interno de produto. Não contém código, dados, telas ou informações proprietárias do concorrente.*
