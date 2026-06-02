<?php

namespace App\Mail;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnnouncementPublishedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Announcement $announcement)
    {
    }

    public function envelope(): Envelope
    {
        $prefix = $this->announcement->urgency === 'high' ? '[URGENTE] ' : '';

        return new Envelope(
            subject: $prefix.'Comunicado: '.$this->announcement->title,
        );
    }

    public function content(): Content
    {
        $this->announcement->loadMissing('condominium');

        return new Content(
            view: 'mail.announcements.published',
            with: [
                'announcement' => $this->announcement,
                'condominiumName' => $this->announcement->condominium?->name,
                'categoryLabel' => Announcement::CATEGORIES[$this->announcement->category] ?? $this->announcement->category,
            ],
        );
    }
}
