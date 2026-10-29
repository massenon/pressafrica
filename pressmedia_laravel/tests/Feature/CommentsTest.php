<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CommentsTest extends TestCase
{
    use RefreshDatabase;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->post = Post::create(['author_id' => User::factory()->create()->id, 'status' => 'published', 'published_at' => now()->subDay()]);
        $this->post->translations()->create(['locale' => 'fr', 'title' => 'Bénin', 'slug' => 'benin', 'body' => 'x']);
        $this->post->translations()->create(['locale' => 'en', 'title' => 'Benin', 'slug' => 'benin-en', 'body' => 'x']);
    }

    private function form(array $overrides = []): array
    {
        return array_merge(['comment' => 'Très bon article', 'author' => 'Awa', 'email' => 'awa@example.test', 'consent' => '1'], $overrides);
    }

    /** PRD phase 2 exit criterion: a public comment arrives as pending. */
    public function test_a_public_comment_arrives_pending_with_its_consent_timestamp(): void
    {
        $this->postJson('/fr/articles/benin/comments', $this->form(['comment' => '<a href="http://spam.example">Très</a> bon']))
            ->assertCreated()->assertJsonPath('message', __('public.comment_pending', [], 'fr'));

        $c = Comment::sole();
        $this->assertSame(['pending', 'fr', 'Très bon'], [$c->status, $c->locale, $c->body]);
        $this->assertNotNull($c->consent_at);
        $this->assertArrayNotHasKey('email', $c->toArray(), 'the e-mail is never serialised');
    }

    public function test_consent_and_required_fields_are_enforced(): void
    {
        $this->postJson('/fr/articles/benin/comments', $this->form(['consent' => null, 'email' => 'nope']))->assertJsonValidationErrors(['consent', 'email']);
        $this->assertSame(0, Comment::count());
    }

    public function test_the_honeypot_silently_drops_bots(): void
    {
        $this->postJson('/fr/articles/benin/comments', $this->form(['website' => 'http://bot.example']))->assertCreated();
        $this->assertSame(0, Comment::count());
    }

    public function test_comments_need_a_published_post_in_that_language(): void
    {
        $this->postJson('/en/articles/benin/comments', $this->form())->assertNotFound();          // FR slug on the EN site
        $this->postJson('/en/articles/benin-en/comments', $this->form())->assertCreated();
        $this->post->update(['status' => 'draft']);
        $this->postJson('/fr/articles/benin/comments', $this->form())->assertNotFound();
    }

    public function test_the_form_is_rate_limited(): void
    {
        foreach (range(1, 5) as $_) {
            $this->postJson('/fr/articles/benin/comments', $this->form())->assertCreated();
        }
        $this->postJson('/fr/articles/benin/comments', $this->form())->assertStatus(429);
    }

    public function test_without_approval_setting_comments_are_published_directly(): void
    {
        Settings::save(['commentsApproval' => false]);
        $this->postJson('/fr/articles/benin/comments', $this->form())->assertJsonPath('message', __('public.comment_published', [], 'fr'));
        $this->assertSame('approved', Comment::sole()->status);
    }

    public function test_moderators_approve_mark_spam_and_delete_with_journal_entries(): void
    {
        $this->postJson('/fr/articles/benin/comments', $this->form());
        $c = Comment::sole();
        $this->actingAs(User::factory()->create()->assignRole('moderator'));

        $this->get('/admin/comments')->assertOk()->assertSee('Très bon article')->assertSee('awa@example.test');
        $this->put("/admin/comments/{$c->id}", ['status' => 'approved'])->assertRedirect();
        $this->assertSame(1, Comment::approved()->count());
        $this->put("/admin/comments/{$c->id}", ['status' => 'spam']);
        $this->delete("/admin/comments/{$c->id}");
        $this->assertSoftDeleted($c);

        $this->assertSame(['approve', 'update', 'delete'], AuditLog::where('module', 'comments')->orderBy('id')->pluck('action')->all());
    }

    public function test_approving_needs_the_publish_right(): void
    {
        $this->postJson('/fr/articles/benin/comments', $this->form());
        $c = Comment::sole();
        Role::create(['name' => 'triage', 'guard_name' => 'web'])->syncPermissions(['comments.view', 'comments.edit']);
        $this->actingAs(User::factory()->create()->assignRole('triage'));

        $this->put("/admin/comments/{$c->id}", ['status' => 'approved'])->assertForbidden();
        $this->put("/admin/comments/{$c->id}", ['status' => 'spam'])->assertRedirect();
        $this->actingAs(User::factory()->create()->assignRole('author'))->get('/admin/comments')->assertForbidden();
    }
}
