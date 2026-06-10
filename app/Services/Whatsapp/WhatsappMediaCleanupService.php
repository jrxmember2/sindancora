<?php

namespace App\Services\Whatsapp;

use App\Models\StorageObject;
use App\Models\Tenant;
use App\Services\StorageService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Limpeza de mídia de WhatsApp na plataforma (entity_type=wa_media) para aliviar a cota do plano.
 * Mídia no Google Drive do tenant NÃO é tocada (é o espaço dele). Apaga via soft-delete (lixeira de
 * 30 dias do StorageService) — a cota é aliviada na hora; o storage:purge-trash remove do disco.
 */
class WhatsappMediaCleanupService
{
    public function __construct(private readonly StorageService $storage) {}

    /** Query das mídias da plataforma do tenant, mais antigas primeiro. */
    private function oldestPlatformMedia(Tenant $tenant): Builder
    {
        return StorageObject::query()
            ->where('tenant_id', $tenant->id)
            ->where('entity_type', 'wa_media')
            ->where('storage_provider', '!=', StorageService::PROVIDER_GOOGLE_DRIVE)
            ->whereNull('deleted_at')
            ->orderBy('created_at');
    }

    /**
     * Libera uma fração (0..1) do volume de mídia de WhatsApp da plataforma, apagando as mais antigas.
     * Retorna os MB liberados.
     */
    public function freeFraction(Tenant $tenant, float $fraction): int
    {
        $fraction = max(0.0, min(1.0, $fraction));
        if ($fraction <= 0) {
            return 0;
        }

        $total = (int) $this->oldestPlatformMedia($tenant)->sum('file_size_bytes');
        if ($total <= 0) {
            return 0;
        }

        $target = (int) floor($total * $fraction);
        $freed = 0;

        foreach ($this->oldestPlatformMedia($tenant)->cursor() as $object) {
            if ($freed >= $target) {
                break;
            }
            $this->storage->delete($object, immediate: false);
            $freed += (int) $object->file_size_bytes;
        }

        $this->storage->forgetUsageCache($tenant);

        return (int) ceil($freed / 1024 / 1024);
    }

    /** Apaga mídia mais antiga que N dias. Retorna MB liberados. */
    public function purgeOlderThan(Tenant $tenant, int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        $cutoff = now()->subDays($days);
        $freed = 0;

        foreach ($this->oldestPlatformMedia($tenant)->where('created_at', '<', $cutoff)->cursor() as $object) {
            $this->storage->delete($object, immediate: false);
            $freed += (int) $object->file_size_bytes;
        }

        if ($freed > 0) {
            $this->storage->forgetUsageCache($tenant);
        }

        return (int) ceil($freed / 1024 / 1024);
    }

    /**
     * Apaga as mídias mais antigas até o uso cair para `$targetPct` da cota (folga abaixo dos 85%).
     * Retorna MB liberados.
     */
    public function purgeToTarget(Tenant $tenant, float $targetPct = 80): int
    {
        $stats = $this->storage->getUsageStats($tenant);
        $quota = (int) $stats['quota_bytes'];
        if ($quota <= 0) {
            return 0;
        }

        $used = (int) $stats['used_bytes'];
        $targetBytes = (int) floor($quota * $targetPct / 100);
        if ($used <= $targetBytes) {
            return 0;
        }

        $freed = 0;
        foreach ($this->oldestPlatformMedia($tenant)->cursor() as $object) {
            if (($used - $freed) <= $targetBytes) {
                break;
            }
            $this->storage->delete($object, immediate: false);
            $freed += (int) $object->file_size_bytes;
        }

        if ($freed > 0) {
            $this->storage->forgetUsageCache($tenant);
        }

        return (int) ceil($freed / 1024 / 1024);
    }
}
