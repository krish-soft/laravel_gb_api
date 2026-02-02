<?php

namespace App\Models;

use App\Models\Common\Log\ActivityLog;
use App\Models\Common\Log\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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




    // Common Relations

    // Relations
    public function activityLogs()
    {
        return $this->morphMany(ActivityLog::class, 'subject')->latest();
    }

    public function relatedActivityLogs()
    {
        return $this->morphMany(ActivityLog::class, 'related')->latest();
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest();
    }

    // Timeline

    public function timeLineLogs(): Collection
    {
        return collect()
            ->merge($this->relationLoaded('activityLogs') ? $this->activityLogs : [])
            ->merge($this->relationLoaded('relatedActivityLogs') ? $this->relatedActivityLogs : [])
            ->merge($this->relationLoaded('auditLogs') ? $this->auditLogs : [])
            ->map(fn($log) => method_exists($log, 'toLog') ? $log->toLog() : $log)
            ->sortByDesc('created_at')
            ->values();
    }

    protected $appends = ['time_line_logs'];

    public function getTimeLineLogsAttribute()
    {
        return $this->timeLineLogs();
    }




    // End Common Relations
}
