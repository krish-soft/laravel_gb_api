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
            ->take(15)        // ✅ LIMIT HERE ONLY
            ->values();
    }

    // protected $appends = ['time_line_logs'];

    public function getTimeLineLogsAttribute()
    {
        return $this->timeLineLogs();
    }



    // Common Functions For Package Module


    public static function businessWindow(): array
    {
        $now = now();


        // If before 7 AM → still previous business day
        if ($now->hour < 7) {
            $start = $now->copy()->subDay()->setTime(7, 0, 0);   // yesterday 7:00 AM
            $end   = $now->copy()->setTime(6, 59, 59);           // today 6:59:59 AM
        } else {
            $start = $now->copy()->setTime(7, 0, 0);             // today 7:00 AM
            $end   = $now->copy()->addDay()->setTime(6, 59, 59); // tomorrow 6:59:59 AM
        }
        // OR
        // $start = $now->copy()->setTime(7, 0, 0)->subDay($now->lt($now->copy()->setTime(7, 0)));
        // $end = $start->copy()->addDay()->subSecond();

        // OLD

        // If before 2 PM → we are still in previous business day
        // if ($now->hour < 14) {
        //     $start = $now->copy()->subDay()->setTime(14, 0, 0); // yesterday 2 PM
        //     $end   = $now->copy()->setTime(13, 59, 59);         // today 1:59:59 PM
        // } else {
        //     $start = $now->copy()->setTime(14, 0, 0);           // today 2 PM
        //     $end   = $now->copy()->addDay()->setTime(13, 59, 59); // tomorrow 1:59:59 PM
        // }



        return [$start, $end];
    }

    public static function alphaSequence(int $number): string
    {
        $result = '';

        while ($number > 0) {
            $number--;
            $result = chr(65 + ($number % 26)) . $result;
            $number = intdiv($number, 26);
        }

        return $result;
    }

    // End Common Relations
}
