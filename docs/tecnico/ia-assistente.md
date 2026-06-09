# Assistente de IA (Fase 6.4)

> Status: implementado e em evolucao. O assistente do sindico usa configuracao global em
> `Admin > IA`, com provedores Claude/Anthropic, OpenAI e Gemini por clientes HTTP finos, sem SDKs
> externos. O RAG continua baseado em documentos indexados por full-text do PostgreSQL.

## Configuracao

Configuracao principal: `Admin > IA` no painel de superadmin. A plataforma salva provedor, modelo,
URL base, status ativo e chave global em `ai_settings`; a chave usa cast `encrypted` e nunca e
exposta ao tenant.

O campo de modelo usa `App\Services\AI\AiModelCatalog` para exibir um dropdown por provedor com
modelos de texto/chat compativeis com os clientes atuais do Assistente. A lista e filtrada pelo
provedor selecionado e validada no backend: OpenAI mostra apenas modelos OpenAI, Claude/Anthropic
apenas modelos Claude e Gemini apenas modelos Gemini. Modelos de imagem, audio, video, embeddings,
moderacao, robotics, deep research, live/realtime ou outros endpoints especializados ficam fora
desse dropdown porque nao funcionam no cliente de conversa usado pelo Assistente e exigem
clientes/endpoints proprios.

Fontes revisadas para o catalogo em 2026-06-08:

- OpenAI: `developers.openai.com/api/docs/models` e `developers.openai.com/api/docs/models/all`.
- Anthropic/Claude: `platform.claude.com/docs/en/about-claude/models/overview` e Models API.
- Gemini: `ai.google.dev/gemini-api/docs/models`.

Fallbacks tecnicos por ambiente continuam disponiveis:

- `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`, `ANTHROPIC_BASE_URL`
- `OPENAI_API_KEY`, `OPENAI_MODEL`, `OPENAI_BASE_URL`
- `GEMINI_API_KEY`, `GEMINI_MODEL`, `GEMINI_BASE_URL`

Sem configuracao ativa, o assistente aparece desabilitado sem quebrar. Permissao: **`ai:use`**.

## Provedores

- `App\Services\AI\AiProviderClient`: contrato comum dos provedores.
- `App\Services\AI\AiProviderManager`: escolhe o cliente a partir de `AiSettingsManager`.
- `ClaudeClient`: usa Anthropic Messages API.
- `OpenAiClient`: usa OpenAI Responses API (`/responses`) e adiciona `reasoning.effort` baixo para
  modelos GPT-5/o-series quando aplicavel; modelos GPT-5 Pro usam o esforco permitido por cada
  modelo (`high` em `gpt-5-pro`, `medium` nos Pro mais novos).
- `GeminiClient`: usa Gemini `generateContent`.

`AssistantService` e `AssemblyService` dependem de `AiProviderManager`, entao a troca de provedor no
admin nao exige alteracao nos fluxos consumidores.

## RAG por full-text

- Tabela `document_chunks` com texto extraido dos documentos e indice GIN `to_tsvector('portuguese')`.
- `App\Services\AI\DocumentIndexer`: baixa o arquivo, extrai texto de PDF/txt/md/csv, divide em
  trechos e grava chunks.
- Disparo: `App\Jobs\IndexDocument` no upload de documentos.
- O indexador e a busca consideram apenas documentos com `is_current = true` e
  `is_ai_searchable = true`.
- Backfill: `php artisan documents:index` (`--tenant=`, `--force`).
- `App\Services\AI\DocumentSearch::search(tenantId, query, limit, condominiumId)`: `plainto_tsquery`
  + `ts_rank`, com fallback ILIKE fora do Postgres e filtro opcional pelo condominio selecionado.

## Base legal global

- Tabelas `ai_legal_documents` e `ai_legal_document_chunks`.
- Categorias: Constituicao Federal, Codigo Civil, Codigo Penal, Lei condominial, Lei estadual,
  Lei municipal, Jurisprudencia, Orientacao da plataforma, Material de referencia e Outro.
- Upload e gestao em `Admin > IA`, com categoria, abrangencia juridica, UF/municipio quando aplicavel,
  sem cota de tenant e sem expor a base para administradores de tenant.
- Abrangencias: `general`/`federal` aplicam a todos; `state` exige UF; `municipal` exige UF + municipio.
- Arquivos sao armazenados no disco padrao em `global/ai/legal/...`.
- `App\Services\AI\LegalDocumentIndexer` reutiliza `DocumentTextExtractor` para extrair PDF/txt/md/csv e
  gravar chunks globais.
- Disparo: `App\Jobs\IndexAiLegalDocument` no upload, ativacao e reindexacao manual.
- Backfill: `php artisan ai-legal-documents:index` (`--force`).
- `App\Services\AI\LegalDocumentSearch` consulta somente documentos ativos, filtra leis estaduais e
  municipais pela localidade do condominio selecionado e combina os trechos com os documentos
  atuais/liberados do tenant no contexto do assistente.

## Fluxo por condominio e fontes

- Conversas em `ai_conversations` agora podem guardar `condominium_id`.
- Se o usuario tem apenas um condominio acessivel, a tela seleciona automaticamente.
- Se o usuario tem mais de um, o dropdown de condominio e obrigatorio antes de enviar mensagens ou
  acoes rapidas.
- Usuarios com papel escopado por `user_roles.condominium_id` so listam/abrem conversas desse escopo;
  conversas antigas sem escopo so ficam acessiveis ao proprio usuario ou a perfis tenant-wide.
- A busca documental do RAG consulta apenas documentos atuais/liberados do condominio selecionado.
- A base legal global continua disponivel como apoio comum da plataforma. Leis estaduais e municipais
  entram no RAG apenas quando batem com UF/cidade do condominio selecionado.
- Respostas que usam RAG salvam `ai_messages.sources` com marcadores como `[D1]` (documento do
  condominio) e `[L1]` (base legal global), exibidos na tela do assistente.

## Servico do assistente

`App\Services\AI\AssistantService`:

- `chat(conversation, tenant, texto, condominium)`: historico + contexto estruturado escopado +
  trechos RAG na ultima mensagem; persiste apenas usuario/assistente, sem o contexto injetado, e
  grava fontes na mensagem do assistente.
- `analyzeDelinquency(tenant, condominium)`: diagnostico com plano de acao a partir de cobrancas
  vencidas reais do condominio selecionado.
- `draftAnnouncement(tenant, prompt, condominium)`: redige titulo e corpo em JSON, pronto para
  revisar/publicar, mantendo o condominio selecionado no prompt.
- `draftOccurrenceReply(tenant, occurrence)`: sugere resposta cordial a ocorrencia.

Persistencia: `ai_conversations` + `ai_messages` (`sources` JSON para citacoes consultadas).

## Painel

- Superadmin: `/admin/ia`, configuracao global de provedor/modelo/chave/teste.
- Superadmin: `/admin/ia`, dropdown de modelos filtrado e validado por provedor.
- Superadmin: `/admin/ia`, base legal global com upload, ativacao/desativacao, download,
  reindexacao e remocao de documentos legais.
- Superadmin: no perfil do tenant (`Admin > Tenants > detalhe`), define override de
  `ai_interactions_monthly` por tenant ou volta a herdar o limite do plano.
- Tenant: `/assistente`, lista de conversas, dropdown de condominio, chat, fontes consultadas,
  acoes rapidas de inadimplencia e rascunho de comunicado. Tudo escopado ao condominio selecionado.

## Limites mensais

O recurso limitavel e `ai_interactions_monthly`.

- Planos exibem o recurso em `Admin > Planos`.
- Overrides por tenant ficam em `tenant_limits`.
- Consumo mensal fica em `tenant_usage_counters`.
- `PlanLimitService` renova counters mensais quando `reset_at` passa.
- O proximo `reset_at` e calculado a partir de `tenant_plan_subscriptions.starts_at`; se a assinatura
  comecou no dia 31, meses menores usam o ultimo dia do mes.
- O Assistente verifica saldo antes de chamar o provedor e incrementa o contador apos uma resposta
  bem-sucedida.

## Deploy

Rodar migrations (`ai_settings`, `document_chunks`, `ai_conversations`, `ai_messages.sources`,
`ai_legal_documents`, `ai_legal_document_chunks`) e manter worker de fila ativo para indexacao. A
configuracao normal da IA deve ser feita em `Admin > IA`; variaveis `.env` sao fallback operacional.

## Guardrails

O prompt do assistente exige uso do contexto fornecido, citacao dos marcadores `[D#]`/`[L#]`
quando houver documentos/base legal e aviso claro quando a informacao nao estiver disponivel. A base
legal global e apoio informativo e nao substitui analise juridica profissional.

## LemeIA (rename) + qualidade do RAG (09/06/2026)

- O assistente do tenant passou a se chamar **LemeIA** (icone de leme `ShipWheel`). Rotas
  (`/assistente`) e a config global (`Admin > IA`) seguem iguais; mudou a identidade visual, o nome
  no `systemPrompt` e os textos das telas.
- **Recuperacao (`DocumentSearch`):** deixou de usar `plainto_tsquery` (AND estrito, que perdia
  trechos quando a pergunta usava palavras nao co-ocorrentes no mesmo trecho) e passou a usar
  **full-text com semantica OR rankeada** (`replace(plainto_tsquery::text,'&','|')::tsquery` +
  `ts_rank`), com fallback ILIKE por termo. O `AssistantService` recupera ate **10 trechos** (era 5).
- **Prompt:** instruido a CONECTAR trechos relacionados (ex.: "horario de obras" ⇄ horarios de
  entrega de material/mudancas) e so dizer que nao ha informacao quando realmente nao houver.

## Ajustes de geracao (temperatura / top_p / max_tokens)

- `ai_settings` ganhou `temperature` (default 0.30), `top_p` (nullable) e `max_tokens` (default 2048).
- `Admin > IA` tem **sliders** para esses parametros, pre-configurados por provedor
  (`AiSetting::tuningDefaults`). Recomendado: ajustar pela temperatura e deixar top_p desligado.
- `AiProviderManager::complete($system,$messages,?int $maxTokens=null)` usa o `max_tokens`
  configurado quando o chamador nao especifica (o chat do LemeIA usa o valor configurado).
- Os clients aplicam `temperature`/`top_p`: Claude e Gemini sempre; OpenAI apenas em modelos
  **nao-reasoning** (gpt-5/o-series rejeitam temperature na Responses API).
