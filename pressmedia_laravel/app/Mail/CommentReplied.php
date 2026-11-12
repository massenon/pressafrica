<?php

namespace App\Mail;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent to a reader who asked to be told when the newsroom answers their comment (owner decision S4). */
class CommentReplied extends Mailable
{
    use Queueable;

    public function __construct(public Comment $question, public Comment $reply) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('site.mail_reply_subject', [], $this->question->locale));
    }

    public function content(): Content
    {
        $lang = $this->question->locale;

        return new Content(text: 'mail.comment-reply', with: [
            'lang' => $lang,
            'title' => $this->question->post->translation($lang)?->title,
            'url' => $this->question->post->url($lang).'#comment-'.$this->question->id,
        ]);
    }
}
