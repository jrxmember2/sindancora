# 05 — Regras de Negócio

> Gerado automaticamente em 31/05/2026, 11:51:45

**Legenda:**
- `[OBSERVADO]` — Confirmado diretamente na tela
- `[INFERIDO]` — Deduzido pelo comportamento
- `[PENDENTE]` — Requer confirmação manual
- `[RECOMENDADO]` — Sugestão para o novo sistema

---

## 1. Regras Gerais do Sistema

- `[OBSERVADO]` O sistema requer autenticação (e-mail + senha) para acesso
- `[OBSERVADO]` Perfil administrador tem acesso a todos os módulos identificados
- `[INFERIDO]` O sistema opera por tenant (dados separados por condomínio/administradora)
- `[INFERIDO]` URLs incluem identificadores de contexto (condomínio ativo, tenant)

---

## 2. Regras por Módulo Identificado



---

## 3. Regras de Negócio Condominiais Esperadas (Padrão de Mercado)

> Estas regras são padrões do domínio condominial. Confirme cada uma na tela.

### Condomínio / Tenant
- `[INFERIDO]` Um condomínio é a unidade principal de dados (tenant)
- `[INFERIDO]` Cada condomínio possui blocos/torres e unidades vinculadas
- `[INFERIDO]` O administrador pode gerenciar múltiplos condomínios

### Unidades
- `[INFERIDO]` Unidade pertence a um condomínio e opcionalmente a um bloco/torre
- `[INFERIDO]` Unidade pode ter múltiplos vínculos: proprietário, locatário, morador
- `[INFERIDO]` Status da unidade: Ocupada / Vazia / Em Obras

### Pessoas e Vínculos
- `[INFERIDO]` Uma pessoa pode ter vínculo com múltiplas unidades (proprietário de várias)
- `[INFERIDO]` Tipo de vínculo: Proprietário / Locatário / Morador / Dependente
- `[INFERIDO]` Pessoa pode ser Síndico, Subsíndico ou Conselheiro

### Comunicados
- `[INFERIDO]` Comunicado tem título, corpo, data de publicação e público-alvo
- `[INFERIDO]` Público-alvo pode ser: todos, bloco específico, unidade específica, perfil
- `[INFERIDO]` Comunicado pode ter anexos

### Ocorrências
- `[INFERIDO]` Ocorrência tem título, descrição, data, status e unidade relacionada
- `[INFERIDO]` Status da ocorrência: Aberta / Em Andamento / Encerrada
- `[INFERIDO]` Ocorrência pode ter histórico de atualizações

### Reservas
- `[INFERIDO]` Espaço tem nome, capacidade, regras de uso e disponibilidade
- `[INFERIDO]` Reserva tem data início, data fim, unidade solicitante, status
- `[INFERIDO]` Status da reserva: Pendente / Aprovada / Cancelada / Finalizada
- `[INFERIDO]` Pode existir taxa de reserva ou caução

### Financeiro / Cobranças
- `[INFERIDO]` Cobrança vinculada à unidade, com valor, vencimento e status
- `[INFERIDO]` Status do pagamento: Em aberto / Pago / Vencido / Cancelado
- `[INFERIDO]` Pode gerar boleto ou link de pagamento
- `[INFERIDO]` Histórico de inadimplência por unidade

### Documentos
- `[INFERIDO]` Documento tem nome, categoria, data, responsável e arquivo
- `[INFERIDO]` Categorias: Ata, Regulamento, Contrato, Comprovante, Outro
- `[INFERIDO]` Documento pode ser público (todos os moradores) ou restrito (admin)

---

## 4. Regras de Permissão Observadas

- `[OBSERVADO]` Perfil Administrador vê todos os módulos no menu lateral
- `[INFERIDO]` Perfil Síndico provavelmente tem acesso limitado em relação ao Admin
- `[INFERIDO]` Perfil Morador/Condômino provavelmente acessa apenas portal do morador
- `[RECOMENDADO]` Implementar RBAC granular no novo sistema: permissão por ação em cada módulo

---

> **Próximo passo:** Execute `npm run phase:06` para gerar o modelo de dados inferido.
