# Roadmap do Assistente IA Condominial

## Objetivo

Criar um assistente de IA global da plataforma, contratado por tenant pelo modulo
`ai_assistant`, para apoiar sindicos e administradores em duvidas operacionais do
dia a dia condominial. O assistente deve consultar documentos do condominio e a
base legal mantida pela plataforma, responder com foco no universo condominial e
evitar parecer juridico.

## Premissas

- A chave de IA e global da plataforma e fica no Painel de Administracao, nunca no
  painel do tenant.
- O superadmin pode configurar Claude/Anthropic, OpenAI ou Gemini.
- Cada tenant so usa IA se o modulo `ai_assistant` estiver contratado e se houver
  limite mensal disponivel.
- O assistente sempre trata o usuario pelo nome da sessao.
- Quando a pergunta exigir interpretacao juridica, estrategia processual,
  notificacao formal sensivel ou risco de litigo, o assistente orienta procurar
  juridico.
- O assistente nao deve inventar artigos, clausulas, atas ou regras internas. Se
  nao encontrar base no contexto, deve dizer isso explicitamente.

## Fases

### 1. Admin > IA: configuracao global

Status: implementado e validado em 09/06/2026.

- Criar tela `Admin > IA` para o superadmin.
- Salvar provedor, modelo, URL base opcional, chave criptografada e status ativo.
- Modelo em dropdown dependente do provedor selecionado: OpenAI lista apenas modelos OpenAI,
  Gemini lista apenas modelos Gemini e Claude/Anthropic lista apenas modelos Claude.
- Remover dependencia operacional exclusiva de `ANTHROPIC_API_KEY` no painel.
- Manter fallback por `.env` apenas como compatibilidade tecnica.
- Exibir status de configuracao e teste de conexao.

### 2. Provedores de IA

Status: implementado.

- Criar abstracao comum para clientes de IA.
- Implementar Claude/Anthropic, OpenAI e Gemini mantendo a mesma interface interna.
- Permitir troca do provedor global sem alterar o fluxo do assistente.

### 3. Limites mensais por tenant

Status: implementado.

- Adicionar recurso `ai_interactions_monthly` aos planos.
- Permitir override no perfil do tenant pelo superadmin.
- Contabilizar cada interacao enviada ao modelo.
- Renovar o contador no ciclo da assinatura do tenant.
- Bloquear novas perguntas quando o limite mensal acabar, com mensagem clara.

### 4. Documentos atuais do condominio

Status: implementado.

- Expandir cadastro/gestao de documentos do condominio.
- Tipos principais: convencao, regimento interno, ata, contrato, circular e outros.
- Adicionar checkbox `Atual` e controle `Consultar pela IA`.
- Consultar por padrao apenas documentos atuais e liberados para IA.
- Reindexar documentos quando o status de atualidade/consulta mudar.

### 5. Base legal global

Status: implementado.

- Criar area em `Admin > IA` para documentos legais da plataforma.
- Permitir upload de Codigo Civil, Codigo Penal, leis condominiais e materiais de
  referencia.
- Indexar a base global separadamente dos documentos do tenant.
- Combinar base legal global com documentos do condominio selecionado.

### 6. Fluxo do assistente para o sindico

Status: implementado.

- Se o usuario tiver um condominio, selecionar automaticamente.
- Se tiver mais de um, exibir dropdown obrigatorio de condominio.
- Escopar conversas, busca documental e respostas ao condominio selecionado.
- Responder citando as fontes consultadas quando houver documentos/base legal relevantes.
- Reforcar que a base legal e apoio informativo e que temas juridicos sensiveis exigem apoio
  profissional.

## Ordem de implementacao

1. Configuracao global em `Admin > IA`.
2. Abstracao de provedores e clientes Claude/OpenAI/Gemini.
3. Limites mensais por plano/tenant.
4. Documentos atuais por condominio.
5. Base legal global.
6. Dropdown de condominio, fontes e guardrails finais no assistente.
