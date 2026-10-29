<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Database backups without mysqldump (shared host: no shell): SQL written through PDO, gzip, AES-256-GCM with a key
 * derived from APP_KEY (EX-29, EX-30). Files: storage/app/private/backups/*.sql.gz.enc (+ weekly media zip).
 * Keep APP_KEY safe: without it a backup cannot be read.
 */
class Backup
{
    public const DIR = 'backups';

    private const MAGIC = 'AGPBK1';

    private const SEP = "\n-- @@\n";

    public static function run(string $label = 'auto'): string
    {
        $name = self::DIR.'/db-'.now()->format('Ymd-His').'-'.$label.'.sql.gz.enc';
        Storage::disk('local')->put($name, self::encrypt(gzencode(self::dump(), 6)));
        self::prune();

        return $name;
    }

    public static function media(): ?string
    {
        $root = Storage::disk('public')->path('');
        $name = self::DIR.'/media-'.now()->format('Ymd-His').'.zip';
        $zip = new ZipArchive;
        Storage::disk('local')->makeDirectory(self::DIR);
        if ($zip->open(Storage::disk('local')->path($name), ZipArchive::CREATE) !== true) {
            return null;
        }
        foreach (Storage::disk('public')->allFiles() as $f) {
            $zip->addFile($root.$f, $f);
        }
        $zip->close();

        return $name;
    }

    /** @return list<array{name: string, size: int, at: int}> newest first */
    public static function list(): array
    {
        return collect(Storage::disk('local')->files(self::DIR))->filter(fn ($f) => str_ends_with($f, '.enc') || str_ends_with($f, '.zip'))
            ->map(fn ($f) => ['name' => basename($f), 'size' => Storage::disk('local')->size($f), 'at' => Storage::disk('local')->lastModified($f)])
            ->sortByDesc('at')->values()->all();
    }

    /**
     * Replaces the database with the backup's content, except the audit log, which keeps every entry written since
     * (append-only, INV-02); the backup still contains it for a rebuild from scratch on an empty server.
     */
    public static function restore(string $file): void
    {
        $sql = gzdecode(self::decrypt(Storage::disk('local')->get(self::DIR.'/'.basename($file))));
        if ($sql === false) {
            throw new RuntimeException('Backup is not readable (wrong APP_KEY or damaged file).');
        }
        $mysql = DB::getDriverName() === 'mysql';
        DB::statement($mysql ? 'SET FOREIGN_KEY_CHECKS=0' : 'PRAGMA foreign_keys = OFF');
        try {
            $keepAudit = Schema::hasTable('audit_logs');
            DB::transaction(function () use ($sql, $mysql, $keepAudit) {
                if (! $mysql) {
                    DB::statement('PRAGMA defer_foreign_keys = ON');   // foreign_keys=OFF is ignored inside a transaction
                }
                foreach (explode(self::SEP, $sql) as $stmt) {
                    if (trim($stmt) === '' || ($keepAudit && preg_match('/^(DROP TABLE IF EXISTS|CREATE TABLE|INSERT INTO|CREATE INDEX)\s+["`]?audit_logs["`]?|ON ["`]?audit_logs["`]?/i', trim($stmt)))) {
                        continue;
                    }
                    DB::unprepared($stmt);
                }
            });
        } finally {
            DB::statement($mysql ? 'SET FOREIGN_KEY_CHECKS=1' : 'PRAGMA foreign_keys = ON');
        }
    }

    public static function dump(): string
    {
        $pdo = DB::connection()->getPdo();
        $mysql = DB::getDriverName() === 'mysql';
        $tables = $mysql ? array_map(fn ($r) => array_values((array) $r)[0], DB::select('SHOW TABLES'))
            : array_column(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"), 'name');
        $q = fn ($t) => $mysql ? "`$t`" : '"'.$t.'"';
        $out = ['-- Africa Global Press backup '.now()->toIso8601String().' ('.DB::getDriverName().')'];
        foreach ($tables as $t) {
            $create = $mysql ? array_values((array) DB::select('SHOW CREATE TABLE '.$q($t))[0])[1]
                : DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$t])->sql;
            $out[] = 'DROP TABLE IF EXISTS '.$q($t);
            $out[] = $create;
            foreach (DB::table($t)->cursor()->chunk(200) as $rows) {
                $cols = array_keys((array) $rows->first());
                $values = $rows->map(fn ($r) => '('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values((array) $r))).')')->implode(',');
                $out[] = 'INSERT INTO '.$q($t).' ('.implode(',', array_map($q, $cols)).') VALUES '.$values;
            }
        }
        if (! $mysql) {
            foreach (DB::select("SELECT sql FROM sqlite_master WHERE type = 'index' AND sql IS NOT NULL") as $i) {
                $out[] = $i->sql;
            }
        }

        return implode(self::SEP, $out).self::SEP;
    }

    private static function prune(): void
    {
        $keep = max(1, (int) Settings::get('backupKeep'));
        collect(self::list())->filter(fn ($b) => str_starts_with($b['name'], 'db-'))->slice($keep)
            ->each(fn ($b) => Storage::disk('local')->delete(self::DIR.'/'.$b['name']));
    }

    private static function key(): string
    {
        $k = (string) config('app.key');

        return hash('sha256', str_starts_with($k, 'base64:') ? base64_decode(substr($k, 7)) : $k, true);
    }

    private static function encrypt(string $data): string
    {
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($data, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);

        return self::MAGIC.$iv.$tag.$cipher;
    }

    private static function decrypt(string $blob): string
    {
        if (! str_starts_with($blob, self::MAGIC)) {
            throw new RuntimeException('Not an Africa Global Press backup.');
        }
        $o = strlen(self::MAGIC);
        $plain = openssl_decrypt(substr($blob, $o + 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($blob, $o, 12), substr($blob, $o + 12, 16));
        if ($plain === false) {
            throw new RuntimeException('Backup is not readable (wrong APP_KEY or damaged file).');
        }

        return $plain;
    }
}
