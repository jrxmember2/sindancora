<?php

namespace App\Notifications\Channels;

use App\Models\PersonUnitLink;
use App\Models\WhatsappConnection;
use App\Services\Whatsapp\EvolutionManager;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Canal de notificação por WhatsApp (Evolution API). Reusa as notificações existentes: uma
 * notificação só sai por aqui se implementar toWhatsapp() e o tenant tiver uma CONEXÃO conectada.
 * Sem conexão ou sem telefone, o canal simplesmente não envia (degradação graciosa). Quando há
 * várias conexões, prefere a que atende um condomínio do destinatário.
 */
class WhatsAppChannel
{
    public function __construct(private readonly EvolutionManager $evolution) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsapp')) {
            return;
        }

        $message = $notification->toWhatsapp($notifiable);
        $number = $notifiable->routeNotificationFor('whatsapp', $notification);
        $tenantId = $notifiable->tenant_id ?? null;

        if (blank($message) || blank($number) || ! $tenantId) {
            return;
        }

        $connection = $this->resolveConnection($notifiable, $tenantId);
        if (! $connection) {
            return;
        }

        try {
            $this->evolution->sendText($connection, $number, $message);
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar WhatsApp', ['tenant' => $tenantId, 'connection' => $connection->id, 'error' => $e->getMessage()]);
        }
    }

    /** Escolhe uma conexão conectada do tenant, preferindo a que atende o condomínio do destinatário. */
    private function resolveConnection(object $notifiable, string $tenantId): ?WhatsappConnection
    {
        $connected = WhatsappConnection::where('tenant_id', $tenantId)
            ->where('status', 'connected')
            ->with('condominiums:id')
            ->get();

        if ($connected->isEmpty()) {
            return null;
        }

        $condoIds = $this->notifiableCondominiumIds($notifiable);

        if (! empty($condoIds)) {
            foreach ($connected as $connection) {
                if ($connection->condominiums->pluck('id')->intersect($condoIds)->isNotEmpty()) {
                    return $connection;
                }
            }
        }

        return $connected->first();
    }

    /** Condomínios do destinatário (via vínculos da pessoa), quando determinável. */
    private function notifiableCondominiumIds(object $notifiable): array
    {
        $personId = $notifiable->person_id ?? null;
        if (! $personId) {
            return [];
        }

        return PersonUnitLink::where('person_id', $personId)
            ->whereNull('end_date')
            ->join('units', 'units.id', '=', 'person_unit_links.unit_id')
            ->distinct()
            ->pluck('units.condominium_id')
            ->all();
    }
}
