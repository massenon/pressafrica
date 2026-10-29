<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CampaignMail;
use App\Models\AuditLog;
use App\Models\NewsletterCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** Admin > Newsletter > Campaigns: write, test, schedule (sent by the cron in batches), cancel. */
class CampaignController extends Controller
{
    public function index(): View
    {
        return view('admin.campaigns.index', ['campaigns' => NewsletterCampaign::latest('id')->paginate(30)]);
    }

    public function create(): View
    {
        return view('admin.campaigns.form', ['c' => new NewsletterCampaign(['locale' => 'all', 'status' => 'draft'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $c = NewsletterCampaign::create($this->data($request) + ['status' => 'draft', 'created_by' => $request->user()->id]);
        AuditLog::record('create', 'newsletter', 'campaign:'.$c->id, $c->subject);

        return redirect()->route('admin.campaigns.edit', $c)->with('status', 'Draft saved.');
    }

    public function edit(NewsletterCampaign $campaign): View
    {
        return view('admin.campaigns.form', ['c' => $campaign]);
    }

    public function update(Request $request, NewsletterCampaign $campaign): RedirectResponse
    {
        abort_unless(in_array($campaign->status, ['draft', 'cancelled'], true), 422, 'Only drafts can be edited.');
        $campaign->update($this->data($request));
        AuditLog::record('update', 'newsletter', 'campaign:'.$campaign->id);

        return back()->with('status', 'Draft saved.');
    }

    /** Test e-mail to the signed-in staff member only (no unsubscribe link, nothing recorded as delivered). */
    public function test(Request $request, NewsletterCampaign $campaign): RedirectResponse
    {
        try {
            Mail::to($request->user()->email)->send(new CampaignMail($campaign));
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['subject' => 'The test e-mail could not be sent: check the mail settings of the host.']);
        }

        return back()->with('status', 'Test sent to '.$request->user()->email.'.');
    }

    public function schedule(Request $request, NewsletterCampaign $campaign): RedirectResponse
    {
        abort_unless(in_array($campaign->status, ['draft', 'cancelled'], true), 422);
        $at = $request->validate(['scheduled_at' => ['nullable', 'date', 'after_or_equal:now']])['scheduled_at'] ?? null;
        $campaign->update(['status' => 'scheduled', 'scheduled_at' => $at ?? now()]);
        $n = $campaign->pendingRecipients()->count();
        AuditLog::record('schedule', 'newsletter', 'campaign:'.$campaign->id, "$n recipients, ".$campaign->scheduled_at->format('Y-m-d H:i'));

        return redirect()->route('admin.campaigns.index')->with('status', "Scheduled for $n subscribers; the cron sends it in batches.");
    }

    public function cancel(NewsletterCampaign $campaign): RedirectResponse
    {
        abort_unless(in_array($campaign->status, ['scheduled', 'sending'], true), 422);
        $campaign->update(['status' => 'cancelled']);
        AuditLog::record('cancel', 'newsletter', 'campaign:'.$campaign->id, "{$campaign->sent_count} already sent");

        return back()->with('status', 'Campaign stopped.');
    }

    private function data(Request $request): array
    {
        $d = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:200000'],
            'locale' => ['required', Rule::in(['all', 'fr', 'en'])],
            'topics' => ['nullable', 'array'],
            'topics.*' => [Rule::in(['daily', 'alerts', 'full'])],
        ]);

        return ['subject' => $d['subject'], 'body' => clean($d['body']), 'locale' => $d['locale'], 'topics' => $d['topics'] ?? null];
    }
}
