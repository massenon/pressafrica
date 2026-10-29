<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Support\Backup;
use Illuminate\Console\Command;

class BackupRestore extends Command
{
    protected $signature = 'backup:restore {file : file name from storage/app/private/backups} {--force}';

    protected $description = 'Replace the database with a backup (a safety backup of the current state is taken first)';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Replace the WHOLE database with '.$this->argument('file').'?')) {
            return self::FAILURE;
        }
        $safety = Backup::run('before-restore');
        Backup::restore($this->argument('file'));
        AuditLog::system('restore', 'backups', basename($this->argument('file')), 'safety copy '.basename($safety));
        $this->info('Restored. Safety copy of the previous state: '.$safety);

        return self::SUCCESS;
    }
}
