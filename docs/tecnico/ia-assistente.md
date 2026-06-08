# Assistente de IA (Fase 6.4)

> Status: implementado e em evolucao. O assistente do sindico usa configuracao global em
> `Admin > IA`, com provedores Claude/Anthropic, OpenAI e Gemini por clientes HTTP finos, sem SDKs
> externos. O RAG continua baseado em documentos indexados por full-text do PostgreSQL.

## Configuracao

Configuracao principal: `Admin > IA` no painel de superadmin. A plataforma salva provedor, modelo,
URL base, status ativo e chave global em `ai_settings`; a chave usa cast `encrypted` e nunca e
exposta ao tenant.

Fallbacks tecnicos por ambiente continuam disponiveis:

- `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`, `ANTHROPIC_BASE_URL`
- `OPENAI_API_KEY`, `OPENAI_MODEL`, `OPENAI_BASE_URL`
- `GEMINI_API_KEY`, `GEMINI_MODEL`, `GEMINI_BASE_URL`

Sem configuracao ativa, o assistente aparece desabilitado sem quebrar. Permissao: **`ai:use`**.

## Provedores

- `App\Services\AI\AiProviderClient`: contrato comum dos provedores.
- `App\Services\AI\AiProviderManager`: escolhe o cliente a partir de `AiSettingsManager`.
- `ClaudeClient`: usa Anthropic Messages API.
- `OpenAiClient`: usa OpenAI Responses API (`/responses`).
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
- `App\Services\AI\DocumentSearch::search(tenantId, query, limit)`: `plainto_tsquery` + `ts_rank`,
  com fallback ILIKE fora do Postgres.

## Base legal global

- Tabelas `ai_legal_documents` e `ai_legal_document_chunks`.
- Categorias: Codigo Civil, Codigo Penal, Lei condominial, Jurisprudencia, Orientacao da plataforma,
  Material de referencia e Outro.
- Upload e gestao em `Admin > IA`, sem cota de tenant e sem expor a base para administradores de tenant.
- Arquivos sao armazenados no disco padrao em `global/ai/legal/...`.
- `App\Services\AI\LegalDocumentIndexer` reutiliza `DocumentTextExtractor` para extrair PDF/txt/md/csv e
  gravar chunks globais.
- Disparo: `App\Jobs\IndexAiLegalDocument` no upload, ativacao e reindexacao manual.
- Backfill: `php artisan ai-legal-documents:index` (`--force`).
- `App\Services\AI\LegalDocumentSearch` consulta somente documentos ativos e combina os trechos com
  os documentos atuais/liberados do tenant no contexto do assistente.

## Servico do assistente

`App\Services\AI\AssistantService`:

- `chat(conversation, tenant, texto)`: historico + contexto estruturado do tenant + trechos RAG na
  ultima mensagem; persiste apenas usuario/assistente, sem o contexto injetado.
- `analyzeDelinquency(tenant)`: diagnostico com plano de acao a partir de cobrancas vencidas reais.
- `draftAnnouncement(tenant, prompt)`: redige titulo e corpo em JSON, pronto para revisar/publicar.
- `draftOccurrenceReply(tenant, occurrence)`: sugere resposta cordial a ocorrencia.

Persistencia: `ai_conversations` + `ai_messages`.

## Painel

- Superadmin: `/admin/ia`, configuracao global de provedor/modelo/chave/teste.
- Superadmin: `/admin/ia`, base legal global com upload, ativacao/desativacao, download,
  reindexacao e remocao de documentos legais.
- Superadmin: no perfil do tenant (`Admin > Tenants > detalhe`), define override de
  `ai_interactions_monthly` por tenant ou volta a herdar o limite do plano.
- Tenant: `/assistente`, lista de conversas, chat, acoes rapidas de inadimplencia e rascunho de
  comunicado. Tudo escopado ao tenant.

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

Rodar migrations (`ai_settings`, `document_chunks`, `ai_conversations`, `ai_legal_documents`,
`ai_legal_document_chunks`) e manter worker de fila ativo para indexacao. A configuracao normal da IA
deve ser feita em `Admin > IA`; variaveis `.env` sao fallback operacional.

## Fora de escopo desta etapa

Dropdown de condominio, citacoes de fontes e guardrails finais de parecer juridico.
