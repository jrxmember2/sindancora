<?php

use Illuminate\Support\Facades\Broadcast;

// IDs são UUID (string) — comparar como string, não como int.
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

// Inbox de WhatsApp por tenant: atualizações de conversas/mensagens em tempo real.
Broadcast::channel('tenant.{tenantId}.inbox', function ($user, $tenantId) {
    return (string) $user->tenant_id === (string) $tenantId && $user->hasPermission('inbox:use');
});
