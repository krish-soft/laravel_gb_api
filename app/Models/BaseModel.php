<?php

namespace App\Models;

use App\Models\Common\Log\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class BaseModel extends Model
{
    //


    protected static function booted()
    {
        static::created(function ($model) {
            self::audit('created', $model, [], $model->getAttributes());
        });

        static::updated(function ($model) {
            $changes = $model->getChanges();

            if (empty($changes)) {
                return;
            }

            $old = [];
            foreach ($changes as $key => $value) {
                $old[$key] = $model->getOriginal($key);
            }

            self::audit('updated', $model, $old, $changes);
        });

        static::deleted(function ($model) {
            self::audit('deleted', $model, $model->getAttributes(), []);
        });


    }

    protected static function audit($action, $model, $old, $new)
    {
        AuditLog::create([
            'user_code' => request()->user()?->user_code,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'action' => $action,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
