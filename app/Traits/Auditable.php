<?php

namespace App\Traits;

use App\Models\Log\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    public static function bootAuditable()
    {
        static::created(function (Model $model) {
            self::logAudit('created', $model, [], $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $changes = $model->getChanges();

            if (empty($changes)) {
                return;
            }

            $old = [];
            foreach ($changes as $key => $value) {
                $old[$key] = $model->getOriginal($key);
            }

            self::logAudit('updated', $model, $old, $changes);
        });

        static::deleted(function (Model $model) {
            self::logAudit('deleted', $model, $model->getAttributes(), []);
        });
    }

    protected static function logAudit(
        string $action,
        Model $model,
        array $oldValues,
        array $newValues
    ): void {
        AuditLog::create([
            'user_id'        => request()->user()?->id,
            'auditable_type' => get_class($model),
            'auditable_id'   => $model->getKey(),
            'action'         => $action,
            'old_values'     => $oldValues ?: null,
            'new_values'     => $newValues ?: null,
            'ip_address'     => request()->ip(),
            'user_agent'     => request()->userAgent(),
        ]);
    }
}
