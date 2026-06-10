<?php

namespace App\Services;

use App\Exceptions\StorageQuotaException;
use App\Models\StorageObject;
use App\Models\Tenant;
use App\Models\TenantDriveSetting;
use App\Models\TenantStorageAddon;
use App\Services\Google\GoogleDriveService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class StorageService
{
    public const PROVIDER_GOOGLE_DRIVE = 'google_drive';

    private const ALLOWED_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
        'application/zip',
        'application/x-rar-compressed',
    ];

    private const MAX_FILE_SIZE_BYTES = 52428800; // 50 MB

    public function checkQuota(Tenant $tenant, int $fileSizeBytes): void
    {
        $quotaBytes = $this->getTotalQuotaBytes($tenant);
        $usedBytes = $this->getUsedBytes($tenant);

        if (($usedBytes + $fileSizeBytes) > $quotaBytes) {
            throw new StorageQuotaException(
                usedMb: round($usedBytes / 1024 / 1024, 1),
                quotaMb: round($quotaBytes / 1024 / 1024, 1),
                fileSizeMb: round($fileSizeBytes / 1024 / 1024, 1),
            );
        }
    }

    public function upload(
        UploadedFile $file,
        Tenant $tenant,
        string $entityType,
        string $entityId,
        string $visibility = 'tenant',
        ?string $condominiumId = null,
    ): StorageObject {
        $this->validateFile($file);
        $this->checkQuota($tenant, $file->getSize());

        $uuid = (string) Str::uuid();
        $year = now()->year;
        $month = now()->format('m');
        $ext = $file->getClientOriginalExtension();
        $condFolder = $condominiumId ?? 'general';
        $path = "{$tenant->id}/{$condFolder}/{$entityType}/{$year}/{$month}/{$uuid}.{$ext}";

        $disk = config('filesystems.default');
        Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));

        $storageObject = StorageObject::create([
            'tenant_id' => $tenant->id,
            'condominium_id' => $condominiumId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'storage_provider' => $disk,
            'storage_bucket' => config("filesystems.disks.{$disk}.bucket"),
            'storage_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size_bytes' => $file->getSize(),
            'checksum_sha256' => hash_file('sha256', $file->getRealPath()),
            'visibility' => $visibility,
            'uploaded_by' => auth()->id(),
        ]);

        // Incrementa contador de storage em MB
        app(PlanLimitService::class)->increment(
            $tenant,
            'storage_mb',
            (int) ceil($file->getSize() / 1024 / 1024)
        );

        return $storageObject;
    }

    /**
     * Armazena conteúdo bruto (bytes) — usado para mídia recebida/enviada pelo WhatsApp, onde não há
     * um UploadedFile. Respeita a cota do tenant e um limite de tamanho opcional (bytes). Lança
     * StorageQuotaException se estourar a cota. Não valida MIME (mídia de chat é heterogênea).
     */
    public function storeRaw(
        Tenant $tenant,
        string $entityType,
        string $entityId,
        string $contents,
        string $filename,
        ?string $mimeType,
        string $visibility = 'tenant',
        ?string $condominiumId = null,
        ?int $maxBytes = null,
    ): StorageObject {
        $size = strlen($contents);

        if ($maxBytes !== null && $size > $maxBytes) {
            abort(422, 'Arquivo de mídia acima do limite permitido.');
        }

        // Mídia de WhatsApp vai para o Google Drive do tenant quando conectado (não conta cota).
        if ($entityType === 'wa_media' && $tenant->hasActiveDrive()) {
            return $this->storeRawOnDrive($tenant, $entityType, $entityId, $contents, $filename, $mimeType, $visibility, $condominiumId, $size);
        }

        return $this->storeRawOnDisk($tenant, $entityType, $entityId, $contents, $filename, $mimeType, $visibility, $condominiumId, $size);
    }

    /** Armazena bytes no disco da plataforma (respeitando cota e contador). */
    private function storeRawOnDisk(
        Tenant $tenant,
        string $entityType,
        string $entityId,
        string $contents,
        string $filename,
        ?string $mimeType,
        string $visibility,
        ?string $condominiumId,
        int $size,
    ): StorageObject {
        $this->checkQuota($tenant, $size);

        $uuid = (string) Str::uuid();
        $year = now()->year;
        $month = now()->format('m');
        $ext = pathinfo($filename, PATHINFO_EXTENSION) ?: 'bin';
        $condFolder = $condominiumId ?? 'general';
        $path = "{$tenant->id}/{$condFolder}/{$entityType}/{$year}/{$month}/{$uuid}.{$ext}";

        $disk = config('filesystems.default');
        Storage::disk($disk)->put($path, $contents);

        $object = StorageObject::create([
            'tenant_id' => $tenant->id,
            'condominium_id' => $condominiumId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'storage_provider' => $disk,
            'storage_bucket' => config("filesystems.disks.{$disk}.bucket"),
            'storage_path' => $path,
            'original_filename' => $filename,
            'mime_type' => $mimeType,
            'file_size_bytes' => $size,
            'checksum_sha256' => hash('sha256', $contents),
            'visibility' => $visibility,
            'uploaded_by' => auth()->id(),
        ]);

        app(PlanLimitService::class)->increment($tenant, 'storage_mb', (int) ceil($size / 1024 / 1024));

        return $object;
    }

    /**
     * Armazena bytes no Google Drive do tenant. NÃO consome cota do plano nem incrementa contador.
     * Em qualquer falha do Drive cai para o disco da plataforma — a mídia nunca é perdida.
     */
    private function storeRawOnDrive(
        Tenant $tenant,
        string $entityType,
        string $entityId,
        string $contents,
        string $filename,
        ?string $mimeType,
        string $visibility,
        ?string $condominiumId,
        int $size,
    ): StorageObject {
        $setting = $tenant->driveSetting;

        try {
            $fileId = app(GoogleDriveService::class)->upload($setting, $contents, $filename, $mimeType);
        } catch (\Throwable $e) {
            Log::warning('Drive externo indisponível; usando storage da plataforma', [
                'tenant' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return $this->storeRawOnDisk($tenant, $entityType, $entityId, $contents, $filename, $mimeType, $visibility, $condominiumId, $size);
        }

        return StorageObject::create([
            'tenant_id' => $tenant->id,
            'condominium_id' => $condominiumId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'storage_provider' => self::PROVIDER_GOOGLE_DRIVE,
            'storage_bucket' => $setting->root_folder_id,
            'storage_path' => $fileId,
            'original_filename' => $filename,
            'mime_type' => $mimeType,
            'file_size_bytes' => $size,
            'checksum_sha256' => hash('sha256', $contents),
            'visibility' => $visibility,
            'uploaded_by' => auth()->id(),
        ]);
    }

    public function delete(StorageObject $object, bool $immediate = false): void
    {
        if ($immediate) {
            if ($object->storage_provider === self::PROVIDER_GOOGLE_DRIVE) {
                $this->deleteFromDrive($object);
            } else {
                Storage::disk($object->storage_provider)->delete($object->storage_path);
            }
            $object->delete();
        } else {
            $object->update([
                'deleted_at' => now(),
                'permanent_delete_at' => now()->addDays(30),
            ]);
        }
    }

    /** Apaga o arquivo no Google Drive do tenant dono do objeto. Best-effort. */
    public function deleteFromDrive(StorageObject $object): void
    {
        $setting = TenantDriveSetting::forTenant($object->tenant_id)->first();
        if ($setting && $setting->isActive()) {
            app(GoogleDriveService::class)->delete($setting, $object->storage_path);
        }
    }

    /**
     * Retorna o conteúdo bruto de um objeto, independente do provider. Usado para servir mídia do
     * Drive por proxy (não há URL pública para arquivo privado do Drive).
     */
    public function getContents(StorageObject $object): string
    {
        if ($object->storage_provider === self::PROVIDER_GOOGLE_DRIVE) {
            $setting = TenantDriveSetting::forTenant($object->tenant_id)->first();
            if (! $setting || ! $setting->isActive()) {
                throw new RuntimeException('Google Drive não está conectado para este tenant.');
            }

            return app(GoogleDriveService::class)->download($setting, $object->storage_path);
        }

        return Storage::disk($object->storage_provider)->get($object->storage_path);
    }

    public function getSignedUrl(StorageObject $object, int $expiresInMinutes = 60): string
    {
        $disk = $object->storage_provider;

        try {
            $url = Storage::disk($disk)->temporaryUrl($object->storage_path, now()->addMinutes($expiresInMinutes));

            return $this->usesLocalSignedRoute($disk) ? $this->toRelativeUrl($url) : $url;
        } catch (\Exception) {
            return Storage::disk($disk)->url($object->storage_path);
        }
    }

    public function getTotalQuotaBytes(Tenant $tenant): int
    {
        $planGb = $this->getPlanStorageGb($tenant);

        $addonGb = TenantStorageAddon::where('tenant_id', $tenant->id)
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->sum('size_gb');

        return (int) (($planGb + $addonGb) * 1024 * 1024 * 1024);
    }

    public function getUsedBytes(Tenant $tenant): int
    {
        // Mídia no Google Drive do tenant não consome a cota do plano (é o Drive dele).
        return (int) StorageObject::where('tenant_id', $tenant->id)
            ->where('storage_provider', '!=', self::PROVIDER_GOOGLE_DRIVE)
            ->whereNull('deleted_at')
            ->sum('file_size_bytes');
    }

    public function getUsageStats(Tenant $tenant): array
    {
        $used = $this->getUsedBytes($tenant);
        $quota = $this->getTotalQuotaBytes($tenant);

        return [
            'used_bytes' => $used,
            'quota_bytes' => $quota,
            'used_mb' => round($used / 1024 / 1024, 1),
            'quota_mb' => round($quota / 1024 / 1024, 1),
            'used_gb' => round($used / 1024 / 1024 / 1024, 2),
            'quota_gb' => round($quota / 1024 / 1024 / 1024, 2),
            'percentage_used' => $quota > 0 ? round(($used / $quota) * 100, 1) : 0,
            'is_near_limit' => $quota > 0 && ($used / $quota) > 0.85,
            'is_at_limit' => $quota > 0 && ($used / $quota) >= 1.0,
        ];
    }

    /** Uso de armazenamento cacheado por 5 min — evita o SUM em todo request (banner de cota). */
    public function cachedUsageStats(Tenant $tenant): array
    {
        return Cache::remember(
            "tenant:{$tenant->id}:storage-usage",
            300,
            fn () => $this->getUsageStats($tenant),
        );
    }

    /** Invalida o cache de uso (após liberar espaço / expurgar mídia). */
    public function forgetUsageCache(Tenant $tenant): void
    {
        Cache::forget("tenant:{$tenant->id}:storage-usage");
    }

    private function getPlanStorageGb(Tenant $tenant): int
    {
        $plan = $tenant->activePlan();
        if (! $plan) {
            return 1; // 1 GB mínimo
        }

        $limitMb = $plan->getLimit('storage_mb');
        return $limitMb === -1 ? 999 : (int) ($limitMb / 1024);
    }

    private function validateFile(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE_BYTES) {
            abort(422, 'Arquivo muito grande. Máximo permitido: 50 MB.');
        }

        if (! in_array($file->getMimeType(), self::ALLOWED_MIMES)) {
            abort(422, 'Tipo de arquivo não permitido.');
        }
    }

    private function usesLocalSignedRoute(string $disk): bool
    {
        $config = config("filesystems.disks.{$disk}", []);

        return ($config['driver'] ?? null) === 'local' && (bool) ($config['serve'] ?? false);
    }

    private function toRelativeUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return $url;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);

        return $path
            .(is_string($query) && $query !== '' ? "?{$query}" : '')
            .(is_string($fragment) && $fragment !== '' ? "#{$fragment}" : '');
    }
}
