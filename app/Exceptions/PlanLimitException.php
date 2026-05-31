<?php

namespace App\Exceptions;

use Exception;

class PlanLimitException extends Exception
{
    public function __construct(
        public readonly string $resource,
        public readonly int $current,
        public readonly int $limit,
        public readonly string $planName = '',
    ) {
        parent::__construct("Limite do plano atingido para '{$resource}': {$current}/{$limit}.");
    }
}
