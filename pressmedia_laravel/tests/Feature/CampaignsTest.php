<?php

namespace Tests\Feature;

use App\Mail\CampaignMail;
use App\Models\AuditLog;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CampaignsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        foreach ([['a@x.test', 'fr', ['daily'], 'confirmed'], ['b@x.test', 'fr', ['alerts'], 'confirmed'], ['c@x.test', 'en', ['daily'], 'confirmed'],
            ['d@x.test', 'fr', ['daily'], 'pending'], ['e@x.test', 'fr', ['daily'], 'unsubscribed']] as [$e, $l, $t, $s]) {
            NewsletterSubscriber::create(['email' => $e, 'locale' => $l, 'topics' => $t, 'status' => $s]);
        }
    }

    public function test_a_campaign_is_written_tested_scheduled_and_sent_in_batches_once(): void
    {
        Mail::fake();
        Settings::save(['newsletterBatch' => 1]);
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->post('/admin/campaigns', ['subject' => 'Édition du lundi', 'body' => '<h2>Titre</h2><p>Bonjour <script>x()</script></p>', 'locale' => 'fr', 'topics' => ['daily', 'alerts']])->assertRedirect();
        $c = NewsletterCampaign::sole();
        $this->assertStringNotContainsString('<script', $c->body);

        $this->post("/admin/campaigns/{$c->id}/test")->assertRedirect();
        Mail::assertSent(CampaignMail::class, 1);
        $this->post("/admin/campaigns/{$c->id}/schedule")->assertRedirect('/admin/campaigns');

        $this->artisan('newsletter:send');   // batch of 1
        $this->assertSame(['sending', 1], [$c->fresh()->status, $c->fresh()->sent_count]);
        $this->artisan('newsletter:send');
        $this->artisan('newsletter:send');   // nothing left: stays sent, no duplicate
        $c->refresh();
        $this->assertSame(['sent', 2], [$c->status, $c->sent_count]);
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo('a@x.test') && $m->subscriber !== null);
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo('b@x.test'));
        Mail::assertNotSent(CampaignMail::class, fn ($m) => $m->hasTo('c@x.test') || $m->hasTo('d@x.test') || $m->hasTo('e@x.test'));
        $this->assertTrue(AuditLog::where(['module' => 'newsletter', 'action' => 'send', 'user_name' => 'System'])->exists());
    }

    public function test_scheduling_in_the_future_and_stopping(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $c = NewsletterCampaign::create(['subject' => 'S', 'body' => '<p>x</p>', 'locale' => 'all', 'status' => 'draft']);
        $this->post("/admin/campaigns/{$c->id}/schedule", ['scheduled_at' => now()->addHour()->format('Y-m-d H:i')]);
        $this->artisan('newsletter:send');
        Mail::assertNothingSent();
        $this->post("/admin/campaigns/{$c->id}/cancel")->assertRedirect();
        $this->travel(2)->hours();
        $this->artisan('newsletter:send');
        Mail::assertNothingSent();
    }

    public function test_the_mail_has_an_unsubscribe_link_and_header_and_rights_apply(): void
    {
        $sub = NewsletterSubscriber::where('email', 'a@x.test')->sole();
        $mail = new CampaignMail(new NewsletterCampaign(['subject' => 'S', 'body' => '<p>Bonjour</p>', 'locale' => 'fr']), $sub);
        $mail->assertSeeInHtml($sub->unsubscribeUrl());
        $this->assertSame('<'.$sub->unsubscribeUrl().'>', $mail->headers()->text['List-Unsubscribe']);

        $this->actingAs(User::factory()->create()->assignRole('moderator'))->get('/admin/campaigns')->assertOk();
        $this->post('/admin/campaigns', ['subject' => 'x', 'body' => '<p>x</p>', 'locale' => 'all'])->assertForbidden();
        $scheduler = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'newsletter:send'));
        $this->assertSame('* * * * *', $scheduler->expression);
    }
}
