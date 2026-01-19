<?php

namespace App\Models;

use App\Models\Common\Log\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class BaseModel extends Model
{
    //

    protected string $financialDateColumn = 'created_at';
    public function getFinancialDateColumn(): string
    {
        return $this->financialDateColumn;
    }

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


        /*
 |--------------------------------------------------------------------------
 | READ: Financial Year Global Scope
 |--------------------------------------------------------------------------
 */
        static::addGlobalScope('fy_scope', function (Builder $query) {
            $model = new static;
            $column = $model->getFinancialDateColumn();

            $query->whereBetween(
                $column,
                [currentFyStart(), currentFyEnd()]
            );
        });

        /*
        |--------------------------------------------------------------------------
        | CREATE: Block back-dated entries
        |--------------------------------------------------------------------------
        */
        static::creating(function ($model) {
            $column = $model->getFinancialDateColumn();
            $date = $model->{$column} ?? now();

            if ($date < currentFyStart() || $date > currentFyEnd()) {
                throw ValidationException::withMessages([
                    $column => 'Date must be within the active financial year.',
                ]);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | UPDATE: Block past FY modification
        |--------------------------------------------------------------------------
        */
        static::updating(function ($model) {
            $column = $model->getFinancialDateColumn();
            $originalDate = $model->getOriginal($column);

            if ($originalDate < currentFyStart() || $originalDate > currentFyEnd()) {
                throw ValidationException::withMessages([
                    'financial_year' => 'Past financial year data is locked.',
                ]);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | DELETE: Block past FY deletion
        |--------------------------------------------------------------------------
        */
        static::deleting(function ($model) {
            $column = $model->getFinancialDateColumn();
            $date = $model->{$column};

            if ($date < currentFyStart() || $date > currentFyEnd()) {
                throw ValidationException::withMessages([
                    'financial_year' => 'Past financial year data is locked.',
                ]);
            }
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
