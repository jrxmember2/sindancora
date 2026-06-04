<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\StorageObject;
use App\Models\Tenant;
use App\Services\PlanLimitService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Expurga a lixeira de arquivos: remove definitivamente do disco os StorageObjects cujo
 * prazo de retenção (30 dias) expirou e apaga os registros de domínio soft-deletados.
 * Roda sem contexto de tenant (varre todos), agendado diariamente.
 */
class PurgeTrashedStorage extends Command
{
    protected $signature = 'storage:purge-trash {--dry-run : Apenas mostra o que seria removido, sem apagar}';

    protected $description = 'Remove definitivamente os arquivos na lixeira cujo prazo de 30 dias expirou e os registros soft-deletados.';

    public function handle(PlanLimitService $limits): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $prefix = $dryRun ? '[dry-run] ' : '';

        // 1) Registros de domínio soft-deletados há mais de 30 dias: remoção definitiva.
        //    (Os chunks de IA já foram apagados no destroy; o arquivo é tratado no passo 2.)
        //    Feito ANTES dos StorageObjects para não esbarrar na FK documents.storage_object_id.
        $trashedDocs = Document::onlyTrashed()->where('deleted_at', '<=', now()->subDays(30));
        $docCount = (clone $trashedDocs)->count();
        if (! $dryRun && $docCount > 0) {
            $trashedDocs->forceDelete();
        }

        // 2) Arquivos na lixeira cujo prazo de retenção expirou: apaga do disco + remove o registro.
        $objects = StorageObject::whereNotNull('deleted_at')
            ->whereNotNull('permanent_delete_at')
            ->where('permanent_delete_at', '<=', now())
            ->get();

        $purged = 0;
        $freedMbByTenant = [];

        foreach ($objects as $object) {
            if ($dryRun) {
                $purged++;
                continue;
            }

            try {
                Storage::disk($object->storage_provider)->delete($object->storage_path);
            } catch (\Throwable $e) {
                $this->warn("Falha ao apagar o arquivo {$object->storage_path}: {$e->getMessage()}");
            }

            $mb = (int) ceil($object->file_size_bytes / 1024 / 1024);
            $freedMbByTenant[$object->tenant_id] = ($freedMbByTenant[$object->tenant_id] ?? 0) + $mb;

            $object->delete();
            $purged++;
        }

        // Ajusta o contador de uso de storage por tenant (a remoção definitiva libera espaço).
        if (! $dryRun) {
            foreach ($freedMbByTenant as $tenantId => $mb) {
                $tenant = Tenant::find($tenantId);
                if ($tenant && $mb > 0) {
                    $limits->decrement($tenant, 'storage_mb', $mb);
                }
            }
        }

        $this->info("{$prefix}Registros expurgados: {$docCount}. Arquivos removidos da lixeira: {$purged}.");

        return self::SUCCESS;
    }
}
