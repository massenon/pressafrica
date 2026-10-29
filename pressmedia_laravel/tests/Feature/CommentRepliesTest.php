<?php

namespace Tests\Feature;

use App\Mail\CommentReplied;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommentRepliesTest extends TestCase
{
    use RefreshDatabase;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->post = Post::create(['author_id' => User::factory()->create()->id, 'status' => 'published', 'published_at' => now()->subDay()]);
        $this->post->translations()->create(['locale' => 'fr', 'title' => 'Bénin', 'slug' => 'benin', 'body' => '<p>x</p>']);
    }

    private function question(array $extra = []): Comment
    {
        return Comment::create($extra + ['post_id' => $this->post->id, 'locale' => 'fr', 'author' => 'Awa', 'email' => 'awa@example.test',
            'body' => 'Quand aura lieu le scrutin ?', 'status' => 'approved', 'consent_at' => now()]);
    }

    private function form(array $o = [], int $age = 10): array
    {
        return array_merge(['comment' => 'Une réponse de lecteur', 'author' => 'Kofi', 'email' => 'kofi@example.test', 'consent' => '1',
            'ft' => Crypt::encryptString((string) now()->subSeconds($age)->getTimestamp())], $o);
    }

    public function test_newsroom_answers_publicly_with_name_badge_and_structured_data(): void
    {
        Mail::fake();
        $q = $this->question(['status' => 'pending', 'notify_consent_at' => now()]);
        $this->actingAs(User::factory()->create(['name' => 'Aïcha Bello'])->assignRole('moderator'));

        $this->post("/admin/comments/{$q->id}/reply", ['body' => 'Le 12 avril, selon la CENA.'])->assertRedirect();

        $this->assertSame('approved', $q->fresh()->status, 'replying approves a pending question');
        $reply = Comment::whereNotNull('staff_user_id')->sole();
        $this->assertSame([$q->id, 'approved'], [$reply->parent_id, $reply->status]);
        Mail::assertSent(CommentReplied::class, fn ($m) => $m->hasTo('awa@example.test') && $m->reply->is($reply));
        $this->assertNotNull($q->fresh()->notified_at);
        $this->assertSame(['approve', 'reply'], AuditLog::where('module', 'comments')->orderBy('id')->pluck('action')->all());

        $html = $this->get('/fr/articles/benin.html')->assertOk()
            ->assertSeeInOrder(['Quand aura lieu le scrutin ?', 'Aïcha Bello', __('site.cm_staff', [], 'fr'), 'Le 12 avril, selon la CENA.'])
            ->assertDontSee('awa@example.test')->getContent();
        preg_match('#"@type":"NewsArticle".*?</script>#s', $html, $m);
        $this->assertStringContainsString('"commentCount":2', $m[0]);
        $this->assertStringContainsString('"text":"Le 12 avril, selon la CENA."', $m[0]);
    }

    public function test_an_unreachable_mail_server_does_not_block_the_answer(): void
    {
        // A real SMTP transport pointed at a closed port: the send throws like an unreachable server.
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.scheme' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        $q = $this->question(['notify_consent_at' => now()]);
        $this->actingAs(User::factory()->create()->assignRole('moderator'))
            ->post("/admin/comments/{$q->id}/reply", ['body' => 'Réponse publiée quand même.'])->assertRedirect();
        $this->assertTrue(Comment::where('body', 'Réponse publiée quand même.')->where('status', 'approved')->exists());
        $this->assertNull($q->fresh()->notified_at);
    }

    public function test_support_inbox_is_configurable(): void
    {
        config(['mail.support' => 'support@example.test']);
        $this->assertSame('support@example.test', config('mail.support'));
    }

    public function test_no_email_without_the_readers_opt_in(): void
    {
        Mail::fake();
        $q = $this->question();
        $this->actingAs(User::factory()->create()->assignRole('moderator'))->post("/admin/comments/{$q->id}/reply", ['body' => 'Merci.']);
        Mail::assertNothingSent();
    }

    public function test_readers_reply_one_level_with_moderation_and_opt_in(): void
    {
        $q = $this->question();
        $this->postJson('/fr/articles/benin/comments', $this->form(['parent_id' => $q->id, 'notify' => '1']))->assertCreated();
        $r = Comment::where('parent_id', $q->id)->sole();
        $this->assertSame('pending', $r->status);
        $this->assertNotNull($r->notify_consent_at);

        $this->postJson('/fr/articles/benin/comments', $this->form(['parent_id' => $r->id, 'comment' => 'Réponse à une réponse']))->assertJsonValidationErrors('parent_id');
        $this->actingAs(User::factory()->create()->assignRole('moderator'))->post("/admin/comments/{$r->id}/reply", ['body' => 'x'])->assertStatus(422);
        $this->get('/fr/articles/benin.html?reply='.$q->id)->assertSee('name="parent_id" value="'.$q->id.'"', false);
    }

    public function test_bots_and_floods_are_stopped(): void
    {
        $this->postJson('/fr/articles/benin/comments', $this->form([], 1))->assertCreated();                         // too fast: dropped
        $this->postJson('/fr/articles/benin/comments', $this->form(['ft' => 'forged']))->assertCreated();             // forged token: dropped
        $this->postJson('/fr/articles/benin/comments', $this->form([], 3 * 24 * 3600))->assertCreated();              // stale form: dropped
        $this->assertSame(0, Comment::count());

        $this->postJson('/fr/articles/benin/comments', $this->form(['comment' => 'Même texte']))->assertCreated();
        $this->postJson('/fr/articles/benin/comments', $this->form(['comment' => '  même   TEXTE ']))->assertCreated();  // duplicate: dropped
        $this->assertSame(1, Comment::count());

        $this->travel(2)->minutes();   // the 5-per-minute limit is tested elsewhere
        $this->postJson('/fr/articles/benin/comments', $this->form(['comment' => 'Voir http://a.example et www.b.example']))->assertCreated();
        Settings::save(['commentBlacklist' => "@spam.example\ncasino"]);
        $this->postJson('/fr/articles/benin/comments', $this->form(['email' => 'x@spam.example', 'comment' => 'Bonjour']))->assertCreated();
        $this->postJson('/fr/articles/benin/comments', $this->form(['comment' => 'Meilleur casino en ligne']))->assertCreated();
        $this->assertSame(3, Comment::where('status', 'spam')->count(), 'links, blacklisted domain and word go to spam');
    }

    public function test_spam_or_deleted_questions_hide_their_replies(): void
    {
        $q = $this->question();
        Comment::create(['post_id' => $this->post->id, 'parent_id' => $q->id, 'locale' => 'fr', 'author' => 'Kofi', 'email' => 'k@example.test',
            'body' => 'Réponse visible ?', 'status' => 'approved', 'consent_at' => now()]);
        $this->get('/fr/articles/benin.html')->assertSee('Réponse visible ?');
        $q->update(['status' => 'spam']);
        $this->get('/fr/articles/benin.html')->assertDontSee('Réponse visible ?');
    }
}
