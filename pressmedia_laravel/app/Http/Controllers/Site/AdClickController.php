<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\AdZone;
use Illuminate\Http\RedirectResponse;

/** Counts a banner click, then sends the reader to the advertiser (EX-20). */
class AdClickController extends Controller
{
    public function __invoke(AdZone $zone, string $device): RedirectResponse
    {
        $c = in_array($device, ['desktop', 'mobile'], true) ? $zone->config($device) : null;
        abort_unless($c && $c['type'] === 'banner' && preg_match('#^https?://#', $c['url']), 404);
        $zone->track($device, 'clicks');

        return redirect()->away($c['url']);
    }
}
