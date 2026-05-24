<?php

namespace App\Mail;

use App\Models\Session;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GalleryShareMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Session $session,
        public readonly string $shareUrl,
    ) {}

    public function envelope(): Envelope
    {
        $eventName = $this->session->event->name ?? 'SnapBooth';

        return new Envelope(
            subject: "Foto booth kamu dari {$eventName} sudah siap! 📸",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.gallery-share',
        );
    }
}
