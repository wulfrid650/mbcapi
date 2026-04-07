<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginTwoFactorCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $code,
        public string $expiresAt,
        public ?string $ip = null,
        public ?string $browser = null,
        public ?string $system = null,
        public string $location = 'Douala'
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre code de connexion MBC',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.two_factor_code',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
