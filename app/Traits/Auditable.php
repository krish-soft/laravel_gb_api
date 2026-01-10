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
        $model,
        array $oldValues,
        array $newValues
    ): void {
        // skip audit table + console
        if ($model instanceof AuditLog || app()->runningInConsole()) {
            return;
        }

        AuditLog::log(
            $action,
            $model,
            $oldValues,
            $newValues,
            request()->user()?->user_code
        );
    }
}
