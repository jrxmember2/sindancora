<?php

namespace App\Exceptions;

use RuntimeException;

class ReservationConflictException extends RuntimeException
{
    public function __construct(string $message = 'Já existe uma reserva aprovada que conflita com este horário.')
    {
        parent::__construct($message);
    }
}
