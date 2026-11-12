<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Copy of a contact-page message for the publisher support inbox; "Reply" goes straight to the visitor. */
class ContactReceived extends Mailable
{
    use Queueable;

    public function __construct(public ContactMessage $msg) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[Contact] '.$this->msg->name(), replyTo: [new Address($this->msg->email, $this->msg->name())]);
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact-received');
    }
}
