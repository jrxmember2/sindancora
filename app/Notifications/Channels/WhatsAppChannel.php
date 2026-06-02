<?php

namespace App\Notifications\Channels;

use App\Models\TenantWhatsappSetting;
use App\Services\Whatsapp\WhatsAppClient;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Canal de notificação por WhatsApp (Evolution API). Reusa as notificações existentes:
 * uma notificação só sai por aqui se implementar toWhatsapp() e o tenant tiver a integração
 * ligada. Sem config ou sem telefone, o canal simplesmente não envia (degradação graciosa).
 */
class WhatsAppChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsapp')) {
            return;
        }

        $message = $notification->toWhatsapp($notifiable);
        $number = $notifiable->routeNotificationFor('whatsapp', $notification);

        if (blank($message) || blank($number)) {
            return;
        }

        $tenantId = $notifiable->tenant_id ?? null;
        if (! $tenantId) {
            return;
        }

        $setting = TenantWhatsappSetting::where('tenant_id', $tenantId)->first();
        if (! $setting || ! $setting->isUsable()) {
            return;
        }

        try {
            (new WhatsAppClient($setting))->sendText($number, $message);
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar WhatsApp', ['tenant' => $tenantId, 'error' => $e->getMessage()]);
        }
    }
}
