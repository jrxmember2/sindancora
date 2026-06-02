<?php

namespace App\Mail;

use App\Models\Charge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChargeIssuedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Charge $charge) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cobrança disponível: '.$this->charge->description,
        );
    }

    public function content(): Content
    {
        $this->charge->loadMissing('condominium');

        return new Content(
            view: 'mail.charges.issued',
            with: [
                'charge' => $this->charge,
                'condominiumName' => $this->charge->condominium?->name,
            ],
        );
    }
}
