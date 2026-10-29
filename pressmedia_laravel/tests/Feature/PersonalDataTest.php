<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $p = Post::create(['author_id' => User::factory()->create()->id, 'status' => 'published', 'published_at' => now()->subDay()]);
        $p->translations()->create(['locale' => 'fr', 'title' => 'Bénin', 'slug' => 'benin', 'body' => '<p>x</p>']);
        Comment::create(['post_id' => $p->id, 'locale' => 'fr', 'author' => 'Awa Diallo', 'email' => 'Awa@Example.test', 'body' => 'Bravo', 'status' => 'approved', 'consent_at' => now(), 'notify_consent_at' => now()]);
        NewsletterSubscriber::create(['email' => 'awa@example.test', 'status' => 'confirmed']);
        ContactMessage::create(['locale' => 'fr', 'first_name' => 'Awa', 'last_name' => 'Diallo', 'email' => 'awa@example.test', 'message' => 'Bonjour', 'consent_at' => now()]);
        NewsletterSubscriber::create(['email' => 'other@example.test', 'status' => 'confirmed']);
    }

    public function test_access_request_export(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->get('/admin/privacy?email=awa@example.test')->assertOk()->assertSee('1 comment(s)')->assertSee('1 contact message(s)');
        $json = $this->get('/admin/privacy/export?email=awa@example.test')->assertOk()->json();
        $this->assertSame(['Bravo', 'confirmed', 'Bonjour'], [$json['comments'][0]['text'], $json['newsletter'][0]['status'], $json['contact_messages'][0]['message']]);
        $this->assertStringNotContainsString('awa@example.test', AuditLog::where('module', 'privacy')->value('target'), 'the journal does not repeat the address');
    }

    public function test_erasure_anonymises_comments_and_deletes_the_rest(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->post('/admin/privacy/erase', ['email' => 'awa@example.test', 'confirm' => 'nope@example.test'])->assertSessionHasErrors('confirm');
        $this->post('/admin/privacy/erase', ['email' => 'awa@example.test', 'confirm' => 'awa@example.test'])->assertRedirect();

        $c = Comment::sole();
        $this->assertSame([__('site.cm_anonymous'), 'Bravo', null], [$c->author, $c->body, $c->notify_consent_at]);
        $this->assertStringEndsWith('@invalid', $c->email);
        $this->assertSame(['other@example.test'], NewsletterSubscriber::pluck('email')->all());
        $this->assertSame(0, ContactMessage::count());
        $this->get('/fr/articles/benin')->assertDontSee('Awa Diallo')->assertSee('Bravo');
    }

    public function test_only_administrators_handle_requests(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('editor'))->get('/admin/privacy')->assertForbidden();
    }
}
