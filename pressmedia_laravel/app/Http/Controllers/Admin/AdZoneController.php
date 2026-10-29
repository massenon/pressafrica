<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdZone;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Admin > Ad zones: desktop and mobile configuration per zone, impressions, clicks and CTR (EX-20). */
class AdZoneController extends Controller
{
    public function index(): View
    {
        AdZone::ensureDefaults();
        $stats = DB::table('ad_stats')->where('day', '>=', now()->subDays(29)->toDateString())
            ->selectRaw('ad_zone_id, sum(impressions) as impressions, sum(clicks) as clicks')->groupBy('ad_zone_id')->get()->keyBy('ad_zone_id');

        return view('admin.ads.index', ['zones' => AdZone::orderBy('id')->get(), 'stats' => $stats]);
    }

    public function edit(AdZone $zone): View
    {
        return view('admin.ads.form', compact('zone'));
    }

    public function update(Request $request, AdZone $zone): RedirectResponse
    {
        $rules = ['name' => ['required', 'string', 'max:80'], 'placement' => ['nullable', 'string', 'max:120'], 'active' => ['nullable', 'boolean']];
        foreach (['desktop', 'mobile'] as $d) {
            $rules += [
                "$d.enabled" => ['nullable', 'boolean'],
                "$d.type" => ['required', Rule::in(AdZone::TYPES)],
                "$d.size" => ['nullable', 'string', 'max:20', 'regex:/^\d{2,4}x\d{2,4}$/'],
                "$d.media_id" => ['nullable', 'integer', 'exists:media,id'],
                "$d.url" => ['nullable', 'url:https,http', 'max:255'],
                "$d.client" => ['nullable', 'string', 'max:40', 'regex:/^ca-pub-\d{10,20}$/'],
                "$d.slot" => ['nullable', 'string', 'max:20', 'regex:/^\d{5,20}$/'],
                "$d.html" => ['nullable', 'string', 'max:10000'],
            ];
        }
        $data = $request->validate($rules);
        $zone->update(['name' => $data['name'], 'placement' => $data['placement'] ?? null, 'active' => $request->boolean('active'),
            'desktop' => ['enabled' => $request->boolean('desktop.enabled')] + array_filter($data['desktop'], fn ($v) => $v !== null),
            'mobile' => ['enabled' => $request->boolean('mobile.enabled')] + array_filter($data['mobile'], fn ($v) => $v !== null)]);
        AuditLog::record('update', 'ads', 'zone:'.$zone->key, ($zone->active ? 'active' : 'inactive').', desktop '.$zone->desktop['type'].', mobile '.$zone->mobile['type']);

        return redirect()->route('admin.ads.index')->with('status', 'Ad zone saved.');
    }
}
