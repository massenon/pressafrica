{{-- Ad zone $key: the configured ad per device (desktop/mobile), else the prototype's reserved placeholder. --}}
@php
    $zone = \App\Models\AdZone::where('key', $key)->first();
    $served = $zone ? array_filter(['desktop' => $zone->config('desktop'), 'mobile' => $zone->config('mobile')]) : [];
    foreach (array_keys($served) as $dev) { $zone->track($dev, 'impressions'); }
@endphp
@if (! $served)
<div class="ad-box" role="img" aria-label="{{ __('site.ad_reserved_label') }}" style="aspect-ratio:300/250"><span>{{ __('site.ad_reserved') }}</span></div>
@else
@foreach ($served as $dev => $c)
<div class="ad-slot {{ count($served) > 1 ? ($dev === 'mobile' ? 'show-mobile' : 'mobile-hide') : '' }}" data-device="{{ $dev }}">
@if ($c['type'] === 'banner' && ($img = \App\Models\Media::find($c['media_id'])))
<a href="{{ route('ads.click', [$zone, $dev]) }}" target="_blank" rel="sponsored noopener"><img src="{{ $img->url() }}" alt="{{ $img->alt_fr ?: __('site.ad') }}" width="{{ $img->width }}" height="{{ $img->height }}" loading="lazy" decoding="async" style="max-width:100%;height:auto"></a>
@elseif ($c['type'] === 'adsense')
{{-- AdSense only after consent (the prototype's consent mechanism loads data-consent-cat scripts). --}}
<ins class="adsbygoogle" style="display:block" data-ad-client="{{ $c['client'] }}" data-ad-slot="{{ $c['slot'] }}" data-ad-format="auto" data-full-width-responsive="true"></ins>
<script type="text/plain" data-consent-cat="analytics" data-src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client={{ $c['client'] }}"></script>
<script>document.addEventListener('agp:consent',function(e){if(e.detail&&e.detail.analytics){try{(window.adsbygoogle=window.adsbygoogle||[]).push({})}catch(x){}}},{once:true});</script>
@elseif ($c['type'] === 'html')
{{-- Sponsor code typed by staff holding ads.edit; printed as is on purpose (it is the advertiser's tag). --}}
{!! $c['html'] !!}
@endif
</div>
@endforeach
@endif
