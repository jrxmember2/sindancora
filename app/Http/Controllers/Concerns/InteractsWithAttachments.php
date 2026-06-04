<?php

namespace App\Http\Controllers\Concerns;

use App\Models\StorageObject;
use App\Services\StorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Helper para controllers que recebem upload de anexos em um formulário. Faz o upload
 * de cada arquivo reusando o StorageService (cota/validação) e os associa ao model via
 * entity_type/entity_id.
 */
trait InteractsWithAttachments
{
    /**
     * Sobe os arquivos do campo informado (ex.: "attachments[]") e os vincula ao model.
     * Em estouro de cota, lança StorageQuotaException — trate no controller se necessário.
     */
    protected function storeAttachments(
        Request $request,
        Model $model,
        string $entityType,
        string $visibility = 'tenant',
        ?string $condominiumId = null,
        string $field = 'attachments',
    ): int {
        if (! $request->hasFile($field)) {
            return 0;
        }

        $tenant = app('tenant');
        $count = 0;

        foreach ($request->file($field) as $file) {
            app(StorageService::class)->upload(
                file: $file,
                tenant: $tenant,
                entityType: $entityType,
                entityId: $model->id,
                visibility: $visibility,
                condominiumId: $condominiumId,
            );
            $count++;
        }

        return $count;
    }

    /** Regras de validação para um campo de anexos opcional (até 10 arquivos, 50 MB cada). */
    protected function attachmentRules(string $field = 'attachments'): array
    {
        return [
            $field => 'nullable|array|max:10',
            "{$field}.*" => 'file|max:51200|mimes:pdf,doc,docx,xls,xlsx,odt,ods,jpg,jpeg,png,webp,gif,zip',
        ];
    }

    /** Remove (soft delete → lixeira 30 dias) um anexo do model. */
    protected function deleteAttachment(StorageObject $object): void
    {
        app(StorageService::class)->delete($object);
    }
}
