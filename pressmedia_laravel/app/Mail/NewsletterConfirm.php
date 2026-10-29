<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Double opt-in: the address is only subscribed once its owner follows this link. */
class NewsletterConfirm extends Mailable
{
    use Queueable;

    public function __construct(public NewsletterSubscriber $subscriber) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('site.nl_mail_subject', [], $this->subscriber->locale));
    }

    public function content(): Content
    {
        return new Content(text: 'mail.newsletter-confirm', with: ['lang' => $this->subscriber->locale]);
    }
}
