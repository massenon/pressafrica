{{-- Cover image as the prototype prints it: srcset when the widths exist, alt + title (EX-35), lazy unless eager. --}}
@if ($media)
<img class="{{ $class ?? '' }}" src="{{ $media->url() }}" @if ($media->srcset()) srcset="{{ $media->srcset() }}" sizes="{{ $sizes ?? '100vw' }}" @endif @if ($media->width) width="{{ $media->width }}" height="{{ $media->height }}" @endif alt="{{ $alt }}" title="{{ $title }}" @if ($eager ?? false) loading="eager" fetchpriority="high" @else loading="lazy" @endif decoding="async">
@else
<span class="img-ph"></span>
@endif
