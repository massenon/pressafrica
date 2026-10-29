<?php

namespace Tests\Feature;

use App\Mail\NewsletterConfirm;
use App\Models\AuditLog;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
    }

    public function test_double_opt_in_from_the_public_forms(): void
    {
        Mail::fake();
        $this->postJson('/fr/newsletter', ['email' => 'Awa@Example.test', 'topics' => ['daily', 'alerts']])->assertCreated()->assertJsonPath('message', __('site.nl_pending', [], 'fr'));
        $sub = NewsletterSubscriber::sole();
        $this->assertSame(['awa@example.test', 'pending', ['daily', 'alerts']], [$sub->email, $sub->status, $sub->topics]);
        $this->assertNotNull($sub->consent_at);
        Mail::assertSent(NewsletterConfirm::class, fn ($m) => $m->hasTo('awa@example.test'));

        $this->get('/fr/newsletter/confirm/'.$sub->token)->assertOk()->assertSee(__('site.nl_confirmed', [], 'fr'));
        $this->assertSame('confirmed', $sub->fresh()->status);
        $this->get('/fr/newsletter/unsubscribe/'.$sub->token)->assertOk();
        $this->assertSame('unsubscribed', $sub->fresh()->status);
        $this->get('/fr/newsletter/confirm/nope')->assertNotFound();
    }

    public function test_bots_and_repeats_learn_nothing(): void
    {
        Mail::fake();
        $this->postJson('/fr/newsletter', ['email' => 'bot@example.test', 'website' => 'x'])->assertCreated();
        $this->assertSame(0, NewsletterSubscriber::count());
        NewsletterSubscriber::create(['email' => 'ok@example.test', 'status' => 'confirmed']);
        $this->postJson('/fr/newsletter', ['email' => 'ok@example.test'])->assertCreated();
        Mail::assertNothingSent();
        $this->postJson('/fr/newsletter', ['email' => 'single@example.test', 'topics' => 'daily'])->assertCreated();   // prototype checkbox name
        $this->postJson('/fr/newsletter', ['email' => 'nope'])->assertJsonValidationErrors('email');
        $this->postJson('/fr/newsletter', ['email' => 'a@example.test', 'consent' => '0'])->assertJsonValidationErrors('consent');
    }

    public function test_admin_imports_exports_and_erases(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $csv = UploadedFile::fake()->createWithContent('list.csv', "email,locale\nawa@example.test,fr\nkofi@example.test,en\nnot-an-email,fr\nawa@example.test,fr\n");
        $this->post('/admin/newsletter/import', ['file' => $csv])->assertRedirect()->assertSessionHas('status', '2 added, 2 skipped (invalid or already listed).');

        $json = $this->get('/admin/newsletter/export/json')->assertOk()->json();
        $this->assertSame(['awa@example.test', 'kofi@example.test'], array_column($json, 'email'));
        $this->assertStringContainsString('kofi@example.test,en,confirmed', $this->get('/admin/newsletter/export/csv')->getContent());

        $this->delete('/admin/newsletter/'.NewsletterSubscriber::where('email', 'kofi@example.test')->value('id'))->assertRedirect();
        $this->assertSame(1, NewsletterSubscriber::count());
        $this->assertSame(['import', 'export', 'export', 'delete'], AuditLog::where('module', 'newsletter')->orderBy('id')->pluck('action')->all());
        $this->actingAs(User::factory()->create()->assignRole('author'))->get('/admin/newsletter')->assertForbidden();
    }
}
