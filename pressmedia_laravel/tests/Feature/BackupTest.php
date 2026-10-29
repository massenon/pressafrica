<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Post;
use App\Models\User;
use App\Support\Backup;
use App\Support\Settings;
use Database\Seeders\RolesSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(RolesSeeder::class);
    }

    /** EX-30: a restore is tested, not assumed. */
    public function test_backup_then_restore_brings_the_data_back(): void
    {
        $p = Post::create(['author_id' => User::factory()->create()->id, 'status' => 'published', 'published_at' => now()]);
        $p->translations()->create(['locale' => 'fr', 'title' => "L'article « sauvé »\navec retour", 'slug' => 'sauve', 'body' => '<p>x</p>']);
        $file = basename(Backup::run('test'));
        $raw = Storage::disk('local')->get('backups/'.$file);
        $this->assertStringNotContainsString('sauvé', $raw, 'encrypted');
        $this->assertStringStartsWith('AGPBK1', $raw);

        Post::query()->forceDelete();
        $this->assertSame(0, Post::count());
        $this->artisan('backup:restore', ['file' => $file, '--force' => true])->assertSuccessful();

        $this->assertSame("L'article « sauvé »\navec retour", Post::sole()->translation('fr')->title);
        $this->assertCount(2, Storage::disk('local')->files('backups'), 'a safety copy was taken before restoring');
    }

    public function test_admin_runs_downloads_and_restores_with_confirmation(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->post('/admin/backups', ['media' => '1'])->assertRedirect();
        $name = collect(Backup::list())->first(fn ($b) => str_starts_with($b['name'], 'db-'))['name'];
        $this->get("/admin/backups/$name")->assertOk();
        $this->post("/admin/backups/$name/restore", ['confirm' => 'yes'])->assertSessionHasErrors('confirm');
        $this->post("/admin/backups/$name/restore", ['confirm' => 'RESTORE'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame(['backup', 'download', 'restore'], AuditLog::where('module', 'backups')->orderBy('id')->pluck('action')->all(), 'the journal survives a restore');
        $this->actingAs(User::factory()->create()->assignRole('editor'))->get('/admin/backups')->assertForbidden();
    }

    public function test_retention_and_schedule(): void
    {
        Settings::save(['backupKeep' => 2]);
        foreach (range(1, 3) as $i) {
            $this->travel(1)->minutes();
            Backup::run("n$i");
        }
        $this->assertCount(2, Backup::list());
        $events = collect(app(Schedule::class)->events())->map(fn ($e) => $e->expression.' '.trim(str_replace(["'", '"'], '', strstr($e->command, 'backup'))))->filter(fn ($c) => str_contains($c, 'backup'))->values()->all();
        $this->assertSame(['15 3 * * * backup:run', '15 4 * * 0 backup:run --media'], $events);
    }
}
