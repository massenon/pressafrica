<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\AnalyticsReport;
use App\Support\Ga4Client;
use App\Support\Settings;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Admin > Analytics (EX-15 to EX-18): first-party log by default, GA4 when the owner turns it on (Q7). */
class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        [$report, $from, $to, $error] = $this->report($request);

        return view('admin.analytics.index', compact('report', 'from', 'to', 'error') + ['ga4' => Ga4Client::configured(), 'days' => (int) $request->query('days', 30)]);
    }

    public function export(Request $request, string $format): Response
    {
        abort_unless(in_array($format, ['csv', 'json'], true), 404);
        [$report, $from, $to] = $this->report($request);
        AuditLog::record('export', 'analytics', $format, $from->toDateString().' → '.$to->toDateString());
        $name = 'analytics-'.$from->toDateString().'-'.$to->toDateString().'.'.$format;
        if ($format === 'json') {
            return response()->json(['from' => $from->toDateString(), 'to' => $to->toDateString()] + $report)->header('Content-Disposition', "attachment; filename=$name");
        }
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['block', 'label', 'views']);
        fputcsv($out, ['total', 'views', $report['totals']['views']]);
        fputcsv($out, ['total', 'visitors', $report['totals']['visitors']]);
        foreach (['daily', 'posts', 'sections', 'referrers', 'countries', 'devices', 'searches'] as $block) {
            foreach ($report[$block] as [$label, $n]) {
                fputcsv($out, [$block, $label, $n]);
            }
        }
        foreach ($report['ads'] as [$zone, $imp, $clicks, $ctr]) {
            fputcsv($out, ['ads', $zone, $imp, $clicks, $ctr]);
        }
        rewind($out);

        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=$name"]);
    }

    private function report(Request $request): array
    {
        $days = in_array((int) $request->query('days', 30), [7, 30, 90, 365], true) ? (int) $request->query('days', 30) : 30;
        [$from, $to] = [now()->subDays($days - 1)->startOfDay(), now()];
        $source = $request->query('source', Settings::get('analyticsSource'));
        $source = $source === 'ga4' && Ga4Client::configured() ? 'ga4' : 'internal';
        try {
            return [AnalyticsReport::build($source, $from, $to), $from, $to, null];
        } catch (Throwable $e) {
            report($e);   // GA4 unreachable: fall back to the first-party log rather than an empty screen

            return [AnalyticsReport::build('internal', $from, $to), $from, $to, 'GA4 could not be read ('.$e->getMessage().'); showing the first-party log.'];
        }
    }
}
