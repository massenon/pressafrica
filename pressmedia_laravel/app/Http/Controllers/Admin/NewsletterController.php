<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Admin > Newsletter: subscribers, CSV/JSON export, CSV import, erasure. */
class NewsletterController extends Controller
{
    private const STATUSES = ['pending', 'confirmed', 'unsubscribed'];

    public function index(Request $request): View
    {
        $f = $request->validate(['status' => ['nullable', Rule::in(self::STATUSES)], 'q' => ['nullable', 'string', 'max:100']]);
        $subs = $this->query($f)->latest('id')->paginate(50)->withQueryString();
        $counts = NewsletterSubscriber::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('admin.newsletter.index', compact('subs', 'counts') + ['filters' => $f]);
    }

    public function export(Request $request, string $format): Response
    {
        abort_unless(in_array($format, ['csv', 'json'], true), 404);
        $rows = $this->query($request->only('status', 'q'))->orderBy('id')->get()
            ->map(fn ($s) => ['email' => $s->email, 'locale' => $s->locale, 'status' => $s->status, 'topics' => implode('|', $s->topics ?? []),
                'consent_at' => $s->consent_at?->toIso8601String(), 'confirmed_at' => $s->confirmed_at?->toIso8601String(), 'source' => $s->source]);
        AuditLog::record('export', 'newsletter', $format, $rows->count().' subscribers');
        $name = 'newsletter-'.now()->format('Y-m-d').'.'.$format;
        if ($format === 'json') {
            return response()->json($rows)->header('Content-Disposition', "attachment; filename=$name");
        }
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['email', 'locale', 'status', 'topics', 'consent_at', 'confirmed_at', 'source']);
        $rows->each(fn ($r) => fputcsv($out, array_values($r)));
        rewind($out);

        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=$name"]);
    }

    /** CSV with an "email" column (and optional "locale"): addresses from a previous tool, consent carried over. */
    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel']]);
        $fh = fopen($request->file('file')->getRealPath(), 'r');
        $head = array_map(fn ($h) => strtolower(trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($fh) ?: []);
        $iEmail = array_search('email', $head, true);
        $iLocale = array_search('locale', $head, true);
        [$added, $skipped] = [0, 0];
        while ($iEmail !== false && ($row = fgetcsv($fh)) !== false) {
            $email = strtolower(trim((string) ($row[$iEmail] ?? '')));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || NewsletterSubscriber::where('email', $email)->exists()) {
                $skipped++;

                continue;
            }
            NewsletterSubscriber::create(['email' => $email, 'locale' => in_array($row[$iLocale] ?? '', ['fr', 'en'], true) ? $row[$iLocale] : 'fr',
                'topics' => ['daily'], 'status' => 'confirmed', 'source' => 'import', 'consent_at' => now(), 'confirmed_at' => now()]);
            $added++;
        }
        fclose($fh);
        AuditLog::record('import', 'newsletter', 'csv', "added $added, skipped $skipped");

        return back()->with('status', $iEmail === false ? 'No "email" column found.' : "$added added, $skipped skipped (invalid or already listed).");
    }

    /** Erasure on request (GDPR): the row is deleted, not archived. */
    public function destroy(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $subscriber->delete();
        AuditLog::record('delete', 'newsletter', 'subscriber:'.$subscriber->id);

        return back()->with('status', 'Subscriber deleted.');
    }

    private function query(array $f)
    {
        return NewsletterSubscriber::query()
            ->when($f['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($f['q'] ?? null, fn ($q, $t) => $q->where('email', 'like', "%$t%"));
    }
}
