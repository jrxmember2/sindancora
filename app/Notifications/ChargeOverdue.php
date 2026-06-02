<?php

namespace App\Notifications;

use App\Models\Charge;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChargeOverdue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Charge $charge) {}

    /** @return array<int, string|class-string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', WhatsAppChannel::class];
    }

    public function toWhatsapp(object $notifiable): string
    {
        $value = number_format($this->charge->currentAmount(), 2, ',', '.');
        $due = $this->charge->due_date?->format('d/m/Y');

        return "*Cobrança vencida*\n{$this->charge->description}\nVenceu em {$due} — valor atualizado R$ {$value}.";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $value = number_format($this->charge->currentAmount(), 2, ',', '.');
        $due = $this->charge->due_date?->format('d/m/Y');

        return (new MailMessage)
            ->subject('Cobrança vencida — '.$this->charge->description)
            ->greeting('Olá!')
            ->line("A cobrança \"{$this->charge->description}\" venceu em {$due}.")
            ->line("Valor atualizado: R$ {$value}.")
            ->action('Ver minhas cobranças', route('portal.charges.index'))
            ->line('Regularize para evitar acréscimos adicionais.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Cobrança vencida',
            'message' => $this->charge->description.' — venceu em '.$this->charge->due_date?->format('d/m/Y'),
            'url' => route('portal.charges.index'),
            'icon' => 'wallet',
        ];
    }
}
