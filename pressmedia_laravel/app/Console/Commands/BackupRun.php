<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Support\Backup;
use Illuminate\Console\Command;

class BackupRun extends Command
{
    protected $signature = 'backup:run {--media : also archive the uploaded files}';

    protected $description = 'Encrypted database backup (and optional media archive)';

    public function handle(): int
    {
        $db = Backup::run();
        $this->info("Database: $db");
        if ($this->option('media')) {
            $this->info('Media: '.(Backup::media() ?? 'failed'));
        }
        AuditLog::system('backup', 'backups', basename($db), $this->option('media') ? 'with media' : null);

        return self::SUCCESS;
    }
}
