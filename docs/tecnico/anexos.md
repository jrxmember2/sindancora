# Anexos & Fotos (pendência da Fase 3)

Upload de múltiplos arquivos em Comunicados, Ocorrências e Áreas comuns, **reusando a
camada de storage existente** (`StorageService` + `StorageObject`) — sem tabela nova.

## Como funciona

`StorageObject` já possui os campos polimórficos `entity_type` + `entity_id`. Um anexo é
um `StorageObject` com `entity_type` = o tipo do dono (`announcement`, `occurrence`,
`common_area`) e `entity_id` = o id do registro. Isso permite N anexos por registro,
contabilizados na cota de armazenamento do tenant como qualquer outro arquivo.

## Fundação reutilizável

- **`App\Traits\HasAttachments`** (no model dono): define `const ATTACHMENT_ENTITY` e
  expõe `attachments()` (HasMany de StorageObject ativos) + `attachmentsPayload()`
  (serialização: id, nome, tamanho, mime, is_image).
- **`App\Http\Controllers\Concerns\InteractsWithAttachments`** (no controller):
  `storeAttachments($request, $model, $entityType, $visibility, $condominiumId, $field)`
  sobe cada arquivo via `StorageService::upload` (valida tipo/tamanho e cota);
  `attachmentRules($field)` para validação; `deleteAttachment($object)` (lixeira 30 dias).
- **`App\Http\Controllers\AttachmentController`** — rotas genéricas
  `GET /anexos/{object}/download` e `DELETE /anexos/{object}` (fora do grupo `panel`,
  acessíveis a painel e portal). O controle de acesso é resolvido por `entity_type`:
  - `announcement`: vê quem tem `announcements:read` ou o morador do condomínio (comunicado publicado); remove com `announcements:update`.
  - `occurrence`: vê quem tem `occurrences:read` ou o autor (morador); remove com `occurrences:update` ou o autor.
  - `common_area`: visível a qualquer usuário do tenant; remove com `reservations:approve`.

## Frontend

- **`Components/AttachmentInput.tsx`** — seletor múltiplo de arquivos (usado nos formulários).
- **`Components/AttachmentList.tsx`** — lista com download (e remover, quando permitido).
- Imagens podem ser exibidas como `<img src={route('attachments.download', id)}>` (a rota
  faz 302 para a URL assinada). Usado na galeria de fotos da área na tela de reserva do portal.

## Onde está plugado

| Módulo | Upload | Listagem/Download | Visibilidade do arquivo |
|--------|--------|-------------------|--------------------------|
| Comunicados | Create/Edit (painel) | Show painel (com remover) + Show portal | `public_to_residents` |
| Ocorrências | Create (painel e portal) | Show painel (com remover) + Show portal | `tenant` |
| Áreas comuns | Create/Edit (painel) | Edit painel (com remover) + galeria na reserva do portal | `public_to_residents` |

## Limites

10 arquivos por envio, 50 MB cada; tipos: pdf, doc(x), xls(x), odt, ods, imagens
(jpg/png/webp/gif), zip (mesma allowlist do `StorageService`). Estouro de cota →
`StorageQuotaException` tratada no controller (volta com erro no campo). Remoção é soft
(lixeira de 30 dias do storage).

## Deploy

Apenas código — **sem migration**. `optimize:clear` basta. Os arquivos contam na cota de
storage do plano do tenant.
