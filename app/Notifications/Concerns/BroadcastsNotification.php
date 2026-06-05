<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * Faz a notificação também ser transmitida via WebSocket (Reverb), reaproveitando o mesmo payload
 * do canal database (toArray). O frontend ouve em App.Models.User.{id} e atualiza o sino na hora.
 * Basta adicionar 'broadcast' ao via() da notificação e usar este trait.
 */
trait BroadcastsNotification
{
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
