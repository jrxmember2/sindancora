<?php

namespace App\Services;

use App\Exceptions\StorageQuotaException;
use App\Models\StorageObject;
use App\Models\Tenant;
use App\Models\TenantStorageAddon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorageService
{
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

    public function delete(StorageObject $object, bool $immediate = false): void
    {
        if ($immediate) {
            Storage::disk($object->storage_provider)->delete($object->storage_path);
            $object->delete();
        } else {
            $object->update([
                'deleted_at' => now(),
                'permanent_delete_at' => now()->addDays(30),
            ]);
        }
    }

    public function getSignedUrl(StorageObject $object, int $expiresInMinutes = 60): string
    {
        $disk = $object->storage_provider;

        try {
            return Storage::disk($disk)->temporaryUrl($object->storage_path, now()->addMinutes($expiresInMinutes));
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
        return (int) StorageObject::where('tenant_id', $tenant->id)
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
}
