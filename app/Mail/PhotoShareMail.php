<?php

namespace App\Mail;

use App\Models\Photo;
use App\Models\Session;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PhotoShareMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Photo $photo,
        public readonly Session $session,
        public readonly string $galleryUrl,
    ) {}

    public function envelope(): Envelope
    {
        $eventName = $this->session->event->name ?? 'SnapBooth';
        return new Envelope(
            subject: "Your photos from {$eventName} are ready!",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.photo-share',
        );
    }
}
