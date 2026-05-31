# 09 — Fluxos Operacionais

> Gerado automaticamente em 31/05/2026, 11:51:45
> Fluxos documentados: **8**

---

## Cadastro de Condomínio

> `[INFERIDO]`

**Ator:** Administrador

**Pré-condições:**
- Usuário autenticado como Admin
- Plano com limite de condomínios não atingido

**Passo a Passo:**
1. Acessa menu "Condomínios"
2. Clica em "Novo Condomínio"
3. Preenche dados: nome, CNPJ, endereço, tipo
4. Configura blocos/torres (se aplicável)
5. Salva o condomínio
6. Sistema cria o tenant de condomínio e define configurações padrão

**Resultado:** Condomínio cadastrado e disponível para vincular unidades/pessoas

**Regras Aplicadas:**
- CNPJ único no tenant
- Nome obrigatório
- Endereço obrigatório

**Exceções:**
- CNPJ duplicado → erro de validação
- Limite de plano atingido → bloqueio

**Melhorias Sugeridas para o Novo Sistema:**
- 💡 Wizard step-by-step
- 💡 Importação via planilha
- 💡 Integração com API de CEP

---

## Cadastro de Unidade

> `[INFERIDO]`

**Ator:** Administrador / Síndico

**Pré-condições:**
- Condomínio cadastrado
- Blocos configurados (se aplicável)

**Passo a Passo:**
1. Acessa o condomínio desejado
2. Navega para "Unidades"
3. Clica em "Nova Unidade"
4. Informa: número, bloco, andar, tipo, área, fração
5. Salva a unidade

**Resultado:** Unidade disponível para vínculo com pessoas

**Regras Aplicadas:**
- Número único por bloco/condomínio
- Fração ideal opcional

**Exceções:**
- Número duplicado → erro

**Melhorias Sugeridas para o Novo Sistema:**
- 💡 Importação em massa via CSV
- 💡 Copiar configuração de outra unidade

---

## Vínculo de Morador/Proprietário

> `[INFERIDO]`

**Ator:** Administrador / Síndico

**Pré-condições:**
- Unidade cadastrada
- Pessoa cadastrada ou a cadastrar

**Passo a Passo:**
1. Acessa a unidade
2. Clica em "Vincular Pessoa"
3. Pesquisa por CPF ou nome
4. Se não encontrada: cadastra nova pessoa com dados básicos
5. Seleciona tipo de vínculo: Proprietário, Locatário, Morador, Dependente
6. Informa data de início e se é morador principal
7. Confirma vínculo

**Resultado:** Pessoa vinculada à unidade com papel definido

**Regras Aplicadas:**
- CPF único no sistema
- Uma unidade pode ter múltiplos moradores
- Proprietário pode ter múltiplas unidades

**Exceções:**
- CPF duplicado → vincular ao cadastro existente

**Melhorias Sugeridas para o Novo Sistema:**
- 💡 Convite por e-mail/WhatsApp para acesso ao portal do morador

---

## Envio de Comunicado

> `[INFERIDO]`

**Ator:** Administrador / Síndico

**Pré-condições:**
- Condomínio com moradores cadastrados

**Passo a Passo:**
1. Acessa "Comunicados"
2. Clica em "Novo Comunicado"
3. Preenche título e corpo (editor rico ou texto)
4. Define público-alvo: todos, bloco específico, unidade, perfil
5. Adiciona anexos (opcional)
6. Define data de expiração (opcional)
7. Publica ou agenda para publicação futura

**Resultado:** Comunicado publicado + notificações disparadas para público-alvo

**Regras Aplicadas:**
- Título obrigatório
- Corpo obrigatório
- Público-alvo obrigatório

**Exceções:**
- Sem moradores cadastrados → alerta

**Melhorias Sugeridas para o Novo Sistema:**
- 💡 Confirmação de leitura
- 💡 Agendamento de envio
- 💡 Templates
- 💡 Envio por WhatsApp

---

## Registro de Ocorrência

> `[INFERIDO]`

**Ator:** Morador / Administrador / Síndico

**Pré-condições:**
- Usuário autenticado
- Unidade vinculada

**Passo a Passo:**
1. Acessa "Ocorrências"
2. Clica em "Nova Ocorrência"
3. Preenche título, categoria, descrição
4. Adiciona fotos/anexos (opcional)
5. Submete a ocorrência
6. Sistema notifica síndico/admin
7. Admin atualiza status e adiciona comentários
8. Morador é notificado das atualizações
9. Ocorrência encerrada com resolução documentada

**Resultado:** Ocorrência registrada, acompanhada e encerrada

**Regras Aplicadas:**
- Status: Aberta → Em Andamento → Encerrada
- Morador só vê suas próprias ocorrências (portal)

**Exceções:**
- Ocorrência duplicada → sistema deve alertar

**Melhorias Sugeridas para o Novo Sistema:**
- 💡 SLA automático
- 💡 Escalonamento automático por prazo
- 💡 Avaliação do atendimento

---

## Reserva de Área Comum

> `[INFERIDO]`

**Ator:** Morador / Administrador

**Pré-condições:**
- Área comum cadastrada
- Usuário com unidade vinculada

**Passo a Passo:**
1. Acessa "Reservas"
2. Seleciona área comum desejada
3. Visualiza disponibilidade no calendário
4. Seleciona data/horário
5. Confirma reserva
6. Se aprovação necessária: aguarda aprovação do síndico
7. Notificação de confirmação enviada

**Resultado:** Reserva registrada com status Pendente ou Aprovada

**Regras Aplicadas:**
- Sem sobreposição de horário por área
- Pode exigir taxa ou aprovação
- Regras de antecedência mínima

**Exceções:**
- Conflito de horário → erro
- Inadimplente → bloqueio
- Pendência anterior → alerta

**Melhorias Sugeridas para o Novo Sistema:**
- 💡 Pagamento de taxa online
- 💡 Calendário visual
- 💡 Regras automáticas de aprovação

---

## Gestão de Documentos

> `[INFERIDO]`

**Ator:** Administrador / Síndico

**Pré-condições:**
- Condomínio ativo

**Passo a Passo:**
1. Acessa "Documentos"
2. Clica em "Enviar Documento"
3. Preenche nome, categoria, descrição
4. Faz upload do arquivo
5. Define visibilidade: público ou restrito
6. Salva o documento

**Resultado:** Documento armazenado e disponível conforme visibilidade

**Regras Aplicadas:**
- Categorias: Ata, Regulamento, Contrato, Comprovante, Outro
- Arquivos públicos visíveis no portal do morador

**Exceções:**
- Tamanho máximo por arquivo
- Tipo de arquivo permitido

**Melhorias Sugeridas para o Novo Sistema:**
- 💡 Assinatura digital
- 💡 Controle de versão
- 💡 Pasta compartilhada com conselho

---

## Geração de Cobrança/Boleto

> `[INFERIDO]`

**Ator:** Administrador

**Pré-condições:**
- Unidades cadastradas
- Integração bancária configurada

**Passo a Passo:**
1. Acessa "Financeiro" → "Cobranças"
2. Define mês de referência
3. Configura valores: taxa condominial, extras, descontos
4. Gera cobranças para todas as unidades ou selecionadas
5. Sistema cria boleto/link de pagamento por unidade
6. Cobranças enviadas por e-mail/WhatsApp ao morador
7. Confirmação de pagamento via retorno bancário ou manual

**Resultado:** Cobranças geradas e enviadas; pagamentos registrados

**Regras Aplicadas:**
- Vencimento configurável
- Multa e juros por atraso automáticos
- Histórico de pagamentos por unidade

**Exceções:**
- Banco offline → ficar em fila de envio
- Erro na geração → log de erros

**Melhorias Sugeridas para o Novo Sistema:**
- 💡 PIX automático
- 💡 Remessa bancária CNAB
- 💡 Conciliação automática

---

> **Próximo passo:** Execute `npm run phase:10` para mapear APIs observadas.
