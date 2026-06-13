<?php

namespace App\Mail\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Boas-vindas + primeiro acesso. E-mail da plataforma (sem tenantId → usa o SMTP padrão do
 * Sindâncora, não o SMTP do tenant recém-criado).
 */
class TenantWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $tenantName,
        public string $magicLink,
        public string $loginUrl,
        public string $email,
        public string $tempPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Bem-vindo ao Sindâncora — seu primeiro acesso');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.billing.welcome');
    }
}
