<!doctype html>
<html lang="{{ $lang }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $campaign->subject }}</title></head>
<body style="margin:0;background:#f5f5f5;font-family:Georgia,serif;color:#141414">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px">
<tr><td style="background:#013079;color:#ffffff;padding:18px 24px;font-size:22px;font-weight:bold;border-radius:8px 8px 0 0">Africa Global Press</td></tr>
<tr><td style="padding:24px;font-size:17px;line-height:1.55">{!! $campaign->body !!}</td></tr>
<tr><td style="padding:16px 24px;font-size:12px;color:#5c5c5c;border-top:1px solid #e8e8e8">
{{ $lang === 'en' ? 'You receive this newsletter because you confirmed your subscription.' : 'Vous recevez cette newsletter parce que vous avez confirmé votre inscription.' }}
@if ($unsubscribe)<br><a href="{{ $unsubscribe }}" style="color:#013079">{{ $lang === 'en' ? 'Unsubscribe' : 'Se désinscrire' }}</a>@endif
</td></tr></table></td></tr></table>
</body></html>
