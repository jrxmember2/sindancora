# Armazenamento externo — Google Drive para mídia de WhatsApp

> Implementado em 10/06/2026.

Permite que cada tenant conecte a **própria conta Google** (OAuth, escopo `drive.file`) para gravar
a **mídia da inbox de WhatsApp** no Drive dele, em vez do storage da plataforma. Objetivo: aliviar a
cota do plano (mídia é pesada). Os arquivos ficam no Drive do tenant e são de responsabilidade dele.

## Decisões

- **Auth:** OAuth na conta do tenant, escopo `drive.file` (não-sensível → sem verificação do Google;
  o app só enxerga arquivos que ele mesmo cria).
- **Escopo:** só `entity_type = 'wa_media'` (inbox: recebidas + enviadas). Disparos (`wa_campaign`) e
  demais anexos continuam na plataforma.
- **Migração:** só mídia nova a partir da conexão.
- **Cota:** objetos no Drive **não** contam na cota do plano.

## Arquitetura

- `storage_objects.storage_provider = 'google_drive'` discrimina os objetos no Drive;
  `storage_path` = file id do Drive; `storage_bucket` = folder id raiz.
- Sem `google/apiclient`: tudo via `Illuminate\Support\Facades\Http` (estilo do `EvolutionManager`).

### Credenciais e callback

- `config/services.php → google_drive` (`GOOGLE_DRIVE_CLIENT_ID/SECRET/REDIRECT_URI`). App global da
  plataforma. O `redirect_uri` é **único e central** (registrado no Google Cloud Console).
- Como cada tenant tem domínio próprio, o fluxo carrega o tenant num **`state` assinado**
  (`Crypt::encryptString`). O callback `GET /oauth/google-drive/callback`
  (`App\Http\Controllers\OAuth\GoogleDriveCallbackController`) está na lista de bypass do
  `ResolveTenant` e identifica o tenant pelo state, devolvendo o usuário ao domínio dele com
  `?drive_status=connected|error`.

### Dados

- Tabela `tenant_drive_settings` (única por tenant): `provider`, `account_email`,
  `refresh_token` (cast `encrypted`), `root_folder_id`, `status` (connected|disconnected|error),
  `connected_by`, `connected_at`, `last_error`. Model `App\Models\TenantDriveSetting` (`isActive()`).
- `Tenant::driveSetting()` + `Tenant::hasActiveDrive()`.

### Serviço

`App\Services\Google\GoogleDriveService`: `authUrl`, `exchangeCode`, `ensureRootFolder`, `upload`
(multipart/related atômico), `download`, `delete`, `about` (cota/uso), `accessTokenFor` (refresh do
token; em falha marca `status='error'`).

### Integração no StorageService

- `storeRaw`: se `entity_type === 'wa_media'` e `tenant->hasActiveDrive()` → `storeRawOnDrive`
  (não chama `checkQuota` nem incrementa `storage_mb`). **Fallback:** qualquer falha do Drive cai em
  `storeRawOnDisk` (a mídia nunca é perdida — crítico no webhook de entrada).
- `delete(immediate)`: provider `google_drive` → `deleteFromDrive` (best-effort) + remove o registro.
- `getContents(StorageObject)`: lê os bytes independente do provider (Drive por download).
- `getUsedBytes`: exclui `storage_provider = 'google_drive'`.
- `PurgeTrashedStorage`: apaga do Drive no expurgo e não mexe no contador para esses objetos.

### Servir mídia

`Panel\InboxController::media`: para `google_drive`, serve por **proxy autenticado** (StreamedResponse
inline via `StorageService::getContents`) — arquivo privado do Drive não tem URL pública. Escopo por
tenant/setor já validado antes.

## Pontos de gravação afetados

- `WaInboxService::storeMedia` (mídia recebida) e `Panel\InboxController::sendMedia` (mídia enviada
  pelo atendente). `CampaignController` (disparos) **não** é afetado.

## Deploy (EasyPanel)

1. `php artisan migrate --force` (cria `tenant_drive_settings`); `php artisan optimize:clear`.
2. Google Cloud Console: criar OAuth Client (tipo Web), escopo `drive.file`, com o `redirect_uri`
   central; setar `GOOGLE_DRIVE_CLIENT_ID/SECRET/REDIRECT_URI` no env.
3. Sem novo seed. Worker de fila e webhook da Evolution já ativos.

## Limpeza de mídia do WhatsApp (10/06/2026)

Complementa o Drive: para quem **não** conecta o Drive, a mídia enche a cota. Mecanismos (só mídia na
plataforma — `storage_provider != google_drive`; a do Drive fica intacta):

- **Alerta aos 85%:** `HandleInertiaRequests` compartilha `tenant.storage` (`percentage_used`,
  `is_near_limit`) via `StorageService::cachedUsageStats` (cache de 5 min). O `AppLayout` mostra um
  banner vermelho + botão **Liberar espaço** → `Components/FreeSpaceModal` (25%/50%/100% da mídia mais
  antiga; avisa que não apaga do celular) → `POST settings.storage.free`.
- **Política do gestor** (em `tenants.settings.whatsapp_media_cleanup` = `{mode, retention_days}`,
  helper `Tenant::whatsappCleanupPolicy()`): `off` (só avisa), `date` (apaga > N dias) ou `quota`
  (apaga ao atingir 85%). Editável em `/configuracoes/armazenamento` (`PUT settings.storage.cleanup`).
- **Serviço** `WhatsappMediaCleanupService` (`freeFraction`, `purgeOlderThan`, `purgeToTarget`) usa
  `StorageService::delete(.., immediate:false)` → lixeira de 30 dias; a cota é aliviada na hora
  (`getUsedBytes` ignora `deleted_at`) e o `storage:purge-trash` remove do disco depois.
- **Job** `whatsapp:cleanup-media` (agendado 03:15, antes do purge-trash) aplica a política por tenant.

## Verificação manual

Conectar em `/configuracoes/armazenamento` → consentir → voltar `connected`; receber/enviar mídia na
inbox → conferir `storage_provider='google_drive'` no `storage_objects` e o arquivo na pasta do Drive;
abrir a mídia no painel (proxy) → renderiza; conferir que a cota do plano **não** subiu. Forçar token
inválido → upload cai no disco padrão e loga.
