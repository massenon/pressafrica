<?php

namespace App\Console\Commands;

use App\Mail\CampaignMail;
use App\Models\AuditLog;
use App\Models\NewsletterCampaign;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends due campaigns in batches (Settings > newsletterBatch per run; the scheduler runs it every minute), so a
 * shared host's hourly mail limit is not hit. Each recipient gets one delivery row: re-runs never send twice.
 */
class SendNewsletter extends Command
{
    protected $signature = 'newsletter:send';

    protected $description = 'Send the next batch of due newsletter campaigns';

    public function handle(): int
    {
        $budget = max(1, (int) Settings::get('newsletterBatch'));
        $due = NewsletterCampaign::whereIn('status', ['scheduled', 'sending'])->where(fn ($q) => $q->whereNull('scheduled_at')->orWhere('scheduled_at', '<=', now()))->orderBy('id')->get();

        foreach ($due as $c) {
            if ($c->status === 'scheduled') {
                $c->update(['status' => 'sending', 'started_at' => now()]);
                AuditLog::system('send', 'newsletter', 'campaign:'.$c->id, 'started');
            }
            foreach ($c->pendingRecipients()->orderBy('id')->take($budget)->get() as $sub) {
                $budget--;
                try {
                    Mail::to($sub->email)->send(new CampaignMail($c, $sub));
                    [$status, $error] = ['sent', null];
                } catch (Throwable $e) {
                    report($e);
                    [$status, $error] = ['failed', Str::limit($e->getMessage(), 250)];
                }
                DB::table('newsletter_deliveries')->insertOrIgnore(['newsletter_campaign_id' => $c->id, 'newsletter_subscriber_id' => $sub->id,
                    'status' => $status, 'error' => $error, 'sent_at' => now()]);
                $c->increment($status === 'sent' ? 'sent_count' : 'failed_count');
            }
            if (! $c->pendingRecipients()->exists()) {
                $c->update(['status' => 'sent', 'finished_at' => now()]);
                AuditLog::system('send', 'newsletter', 'campaign:'.$c->id, "finished: {$c->sent_count} sent, {$c->failed_count} failed");
            }
            if ($budget <= 0) {
                break;
            }
        }
        $this->info('Newsletter batch done.');

        return self::SUCCESS;
    }
}
