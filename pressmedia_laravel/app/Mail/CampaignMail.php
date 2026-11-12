<?php

namespace App\Mail;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/** One campaign e-mail, personal unsubscribe link and List-Unsubscribe header (one-click in mail clients). */
class CampaignMail extends Mailable
{
    use Queueable;

    public function __construct(public NewsletterCampaign $campaign, public ?NewsletterSubscriber $subscriber = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->campaign->subject);
    }

    public function headers(): Headers
    {
        return new Headers(text: $this->subscriber ? ['List-Unsubscribe' => '<'.$this->subscriber->unsubscribeUrl().'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'] : []);
    }

    public function content(): Content
    {
        return new Content(html: 'mail.campaign', text: 'mail.campaign-text', with: [
            'lang' => $this->subscriber?->locale ?? ($this->campaign->locale === 'en' ? 'en' : 'fr'),
            'unsubscribe' => $this->subscriber?->unsubscribeUrl(),
        ]);
    }
}
