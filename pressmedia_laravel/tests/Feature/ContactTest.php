<?php

namespace Tests\Feature;

use App\Mail\ContactReceived;
use App\Mail\ContactReply;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    private function form(array $o = []): array
    {
        return array_merge(['fname' => 'Awa', 'lname' => 'Diallo', 'org' => 'ONG', 'email' => 'awa@example.test', 'tel' => '+229 01 23 45 67',
            'country' => 'Bénin', 'city' => 'Cotonou', 'message' => 'Bonjour, une question sur votre article.', 'consent' => '1', 'terms' => '1'], $o);
    }

    public function test_messages_are_stored_and_forwarded_to_the_support_inbox(): void
    {
        Mail::fake();
        config(['mail.support' => 'support@example.test']);
        $this->postJson('/fr/contact', $this->form())->assertCreated()->assertJsonPath('message', __('site.ct_ok', [], 'fr'));
        $m = ContactMessage::sole();
        $this->assertSame(['Awa Diallo', 'new', 'fr'], [$m->name(), $m->status, $m->locale]);
        Mail::assertSent(ContactReceived::class, fn ($mail) => $mail->hasTo('support@example.test') && $mail->hasReplyTo('awa@example.test'));
    }

    public function test_bots_spam_and_missing_consent(): void
    {
        Mail::fake();
        config(['mail.support' => 'support@example.test']);
        $this->postJson('/fr/contact', $this->form(['website' => 'x']))->assertCreated();
        $this->postJson('/fr/contact', $this->form(['consent' => null]))->assertJsonValidationErrors('consent');
        $this->postJson('/fr/contact', $this->form(['message' => 'http://a.x http://b.x www.c.x']))->assertCreated();
        $this->assertSame(['spam'], ContactMessage::pluck('status')->all());
        Mail::assertNothingSent();
    }

    public function test_the_newsroom_answers_by_email_from_the_admin(): void
    {
        Mail::fake();
        $this->seed(RolesSeeder::class);
        $this->postJson('/fr/contact', $this->form());
        $m = ContactMessage::sole();
        $this->actingAs(User::factory()->create(['name' => 'Aïcha Bello'])->assignRole('editor'));

        $this->get('/admin')->assertSee('aria-label="1 new contact messages"', false);
        $this->get("/admin/messages/{$m->id}")->assertOk()->assertSee('Bonjour, une question');
        $this->assertSame('read', $m->fresh()->status);
        $this->post("/admin/messages/{$m->id}/reply", ['body' => 'Merci, voici la réponse.'])->assertRedirect('/admin/messages');
        Mail::assertSent(ContactReply::class, fn ($mail) => $mail->hasTo('awa@example.test') && $mail->staff === 'Aïcha Bello');
        $this->assertSame('replied', $m->fresh()->status);
        $this->assertTrue(AuditLog::where(['module' => 'messages', 'action' => 'reply'])->exists());

        $this->actingAs(User::factory()->create()->assignRole('author'))->get('/admin/messages')->assertForbidden();
    }
}
