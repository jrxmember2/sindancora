<?php

namespace App\Exceptions;

use Exception;

class StorageQuotaException extends Exception
{
    public function __construct(
        public readonly float $usedMb,
        public readonly float $quotaMb,
        public readonly float $fileSizeMb,
    ) {
        parent::__construct(
            "Cota de armazenamento excedida. Usado: {$usedMb} MB / {$quotaMb} MB. " .
            "Arquivo: {$fileSizeMb} MB."
        );
    }
}
