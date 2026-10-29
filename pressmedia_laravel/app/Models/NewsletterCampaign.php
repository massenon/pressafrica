<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterCampaign extends Model
{
    protected $guarded = [];

    protected $casts = ['topics' => 'array', 'scheduled_at' => 'datetime', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Confirmed subscribers this campaign is for (language and topics), not yet delivered. */
    public function pendingRecipients()
    {
        return NewsletterSubscriber::where('status', 'confirmed')
            ->when($this->locale !== 'all', fn ($q) => $q->where('locale', $this->locale))
            // topics is a JSON list ("daily", "alerts"...): a quoted LIKE works the same on SQLite and MySQL.
            ->when($this->topics, fn ($q) => $q->where(fn ($w) => collect($this->topics)->each(fn ($t) => $w->orWhere('topics', 'like', '%"'.$t.'"%'))))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('newsletter_deliveries')
                ->whereColumn('newsletter_deliveries.newsletter_subscriber_id', 'newsletter_subscribers.id')
                ->where('newsletter_deliveries.newsletter_campaign_id', $this->id));
    }
}
