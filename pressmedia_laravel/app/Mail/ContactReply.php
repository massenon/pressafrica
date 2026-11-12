<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Answer written in Admin > Messages, sent to the visitor with their message quoted. */
class ContactReply extends Mailable
{
    use Queueable;

    public function __construct(public ContactMessage $msg, public string $body, public string $staff) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Re: Africa Global Press');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact-reply');
    }
}
