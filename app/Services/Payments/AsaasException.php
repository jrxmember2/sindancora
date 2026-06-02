<?php

namespace App\Services\Payments;

use RuntimeException;

class AsaasException extends RuntimeException
{
    /** @param array<int,array{code?:string,description?:string}> $errors */
    public function __construct(string $message, public readonly array $errors = [], int $code = 0)
    {
        parent::__construct($message, $code);
    }

    /** Constrói a exceção a partir do corpo de erro padrão do Asaas (`{errors:[{description}]}`). */
    public static function fromResponse(array $body, int $status): self
    {
        $errors = $body['errors'] ?? [];
        $message = $errors[0]['description'] ?? 'Falha na comunicação com o Asaas.';

        return new self($message, $errors, $status);
    }
}
