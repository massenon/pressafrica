<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Throwable;

/**
 * Append-only (INV-02): updating or deleting an entry throws, so a bug or a future admin screen cannot rewrite history.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'user_name', 'role', 'action', 'module', 'target', 'detail', 'ip'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('The audit log is append-only.'));
        static::deleting(fn () => throw new LogicException('The audit log is append-only.'));
    }

    /** Records an action by $user (or the signed-in user). Never lets a logging failure break the action it records. */
    public static function record(string $action, string $module, ?string $target = null, ?string $detail = null, ?User $user = null): void
    {
        static::write($action, $module, $target, $detail, $user ?? auth()->user());
    }

    /** Records an action taken by the system itself (scheduler, imports), whoever happens to be signed in. */
    public static function system(string $action, string $module, ?string $target = null, ?string $detail = null): void
    {
        static::write($action, $module, $target, $detail, null);
    }

    private static function write(string $action, string $module, ?string $target, ?string $detail, ?User $user): void
    {
        try {
            static::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'System',
                'role' => $user?->getRoleNames()->first() ?? ($user ? null : 'system'),
                'action' => $action,
                'module' => $module,
                'target' => $target === null ? null : mb_substr($target, 0, 255),
                'detail' => $detail,
                'ip' => request()?->ip(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
