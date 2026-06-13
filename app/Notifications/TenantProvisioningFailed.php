<?php

namespace App\Notifications;

use App\Models\PendingSignup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alerta aos super admins quando o provisionamento automático de um tenant falha após os retries.
 * O provisionamento nunca pode falhar silenciosamente.
 */
class TenantProvisioningFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PendingSignup $signup, public string $error) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject('[Sindâncora] Falha no provisionamento de tenant')
            ->greeting('Atenção!')
            ->line("O provisionamento automático do cadastro \"{$this->signup->company_name}\" falhou após as tentativas.")
            ->line("E-mail do comprador: {$this->signup->email}")
            ->line("Erro: {$this->error}")
            ->action('Abrir Financeiro', url('/admin/financeiro'))
            ->line('Verifique o pagamento no Asaas e provisione manualmente se necessário.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Falha no provisionamento de tenant',
            'message' => $this->signup->company_name.' — '.$this->error,
            'url' => '/admin/financeiro',
            'icon' => 'alert-triangle',
        ];
    }
}
