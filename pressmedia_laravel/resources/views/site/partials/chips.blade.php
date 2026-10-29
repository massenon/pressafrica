<div class="chips">@foreach ($post->sortedCategories() as $c)<a class="chip" href="{{ $c->url(app()->getLocale()) }}">{{ $c->translation()?->name }}</a>@endforeach</div>
