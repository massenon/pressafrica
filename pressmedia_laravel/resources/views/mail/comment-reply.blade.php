{{ __('site.mail_reply_intro', ['name' => $question->author], $lang) }}

{{ __('site.mail_reply_line', ['title' => $title], $lang) }}

« {{ $reply->body }} »
— {{ $reply->staff?->name }}, {{ __('site.cm_staff', [], $lang) }}

{{ __('site.mail_reply_cta', [], $lang) }} : {{ $url }}

{{ __('site.mail_reply_why', [], $lang) }}
Africa Global Press
