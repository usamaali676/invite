<?php

namespace App\Mail;

use App\Models\GbpClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OnboardingLinkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public GbpClient $client, public string $url, public bool $manual = false) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->manual
                ? 'Please approve '.config('gbp.agency_name').' on your Google Business Profile'
                : 'Connect your Google Business Profile to '.config('gbp.agency_name'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.onboarding-link');
    }
}
