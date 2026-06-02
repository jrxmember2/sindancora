# Assistente de IA (Fase 6.4)

> Status: implementado. Assistente de IA para o síndico via **Claude API** (Messages API por HTTP,
> sem SDK PHP), com **RAG sobre documentos** por **full-text do PostgreSQL** (a Claude API não tem
> embeddings; usamos tsvector nativo, sem provedor externo nem pgvector) + injeção de dados
> estruturados do tenant. Três capacidades: chat livre, análise de inadimplência e rascunho de
> comunicado.

## Configuração

`config/services.php` → bloco `anthropic`: `key` (env **`ANTHROPIC_API_KEY`** — chave da plataforma,
custo da IA é da plataforma), `model` (env `ANTHROPIC_MODEL`, default `claude-opus-4-8`), `base_url`,
`version`. Sem chave, o assistente aparece desabilitado (degrada sem quebrar). Permissão **`ai:use`**
(admin e síndico no seed).

## RAG por full-text

- Tabela `document_chunks` (texto extraído dos documentos, índice **GIN `to_tsvector('portuguese')`**).
- `App\Services\AI\DocumentIndexer`: baixa o arquivo (StorageService/disk), extrai texto
  (**PDF** via `smalot/pdfparser`; **texto** txt/md/csv), divide em trechos (~1200 chars, overlap
  150) e grava. Reindexação idempotente (apaga trechos antigos).
- Disparo: `App\Jobs\IndexDocument` (fila) no upload (`DocumentController@store`); remoção dos
  trechos no `destroy` (soft delete não dispara o cascade). Backfill: `php artisan documents:index`
  (`--tenant=`, `--force`).
- `App\Services\AI\DocumentSearch::search(tenantId, query, limit)`: `plainto_tsquery` +
  `ts_rank` (fallback ILIKE fora do Postgres). Docx/xlsx ficam fora desta fatia.

## Serviço do assistente

`App\Services\AI\ClaudeClient` (Messages API; **prompt de sistema estável marcado com
`cache_control: ephemeral`** → cacheado; contexto volátil vai na mensagem do usuário). Sem thinking
exposto (responde direto). `App\Services\AI\AssistantService`:
- `chat(conversation, tenant, texto)`: histórico + **contexto** (resumo estruturado: inadimplência,
  ocorrências abertas, reservas pendentes, comunicados recentes + trechos RAG da pergunta) na última
  mensagem; persiste o par usuário/assistente (texto puro, sem o contexto).
- `analyzeDelinquency(tenant)`: diagnóstico com plano de ação a partir das cobranças vencidas reais.
- `draftAnnouncement(tenant, prompt)`: redige título+corpo (JSON), pronto para revisar/publicar.

Persistência: `ai_conversations` + `ai_messages`.

## Painel

`/assistente` (`Panel\AssistantController`, permissão `ai:use`): lista de conversas, chat, ações
rápidas (inadimplência, rascunho de comunicado) e botão "Criar comunicado" a partir do rascunho.
Tela `IA/Assistant.tsx`. Menu: item "Assistente IA". Tudo escopado ao tenant.

## Dependências / deploy

`composer require smalot/pdfparser` (puro PHP). Migrations novas:
`2026_06_06_000001_create_document_chunks_table` (+ índice GIN), `..._000002_create_ai_conversations_table`.
Deploy: `migrate --force` + `db:seed --force` (permissão `ai:use`) + `optimize:clear`. Definir
`ANTHROPIC_API_KEY` (e opcional `ANTHROPIC_MODEL`). Rodar `php artisan documents:index` uma vez para
indexar documentos já existentes. Worker de fila ativo (indexação roda em fila).

## Custo / modelo

A plataforma paga a IA (chave global). Modelo configurável por `ANTHROPIC_MODEL` (default
`claude-opus-4-8`; trocar para `claude-sonnet-4-6`/`claude-haiku-4-5` reduz custo). Prompt caching no
sistema reduz custo em chamadas repetidas.

## Fora de escopo (adiado)

Embeddings/busca vetorial; extração de docx/xlsx; streaming de resposta; pré-preenchimento dos
campos do Comunicado a partir do rascunho (hoje o botão abre o formulário em branco — copiar/colar);
assistente no portal do morador.
