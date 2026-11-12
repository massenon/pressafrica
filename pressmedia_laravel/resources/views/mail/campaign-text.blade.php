{{ trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '</h2>', '</h3>', '</li>'], "\n", $campaign->body)))) }}

--
Africa Global Press
@if ($unsubscribe){{ $lang === 'en' ? 'Unsubscribe' : 'Se désinscrire' }} : {{ $unsubscribe }}@endif
