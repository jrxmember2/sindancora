<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResidentInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public string $tenantName,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Seu acesso ao portal do {$this->tenantName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.invitations.resident',
            with: [
                'userName' => $this->userName,
                'tenantName' => $this->tenantName,
                'url' => $this->url,
            ],
        );
    }
}
