<?php

namespace App\Services\AI;

interface AiProviderClient
{
    /**
     * @param  array<int,array{role:string,content:string}>  $messages
     */
    public function complete(string $system, array $messages, int $maxTokens = 4096): string;
}
