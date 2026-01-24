<?php

namespace App\Traits;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;
use App\Models\Master\Setting\MstFinanceSetting;

trait FinancialYearScoped
{
    /**
     * Master tables that must NOT follow FY rules
     */
    protected static array $financialYearExcludedModels = [
        // \App\Models\Product::class,
        // \App\Models\Category::class,
    ];

    /**
     * Default priority date columns
     */
    protected array $financialDateColumns = ['created_at'];

    /**
     * TEMPORARY runtime switch (ON by default)
     */
    protected static bool $runtimeFinancialYearEnabled = true;

    protected static function bootFinancialYearScoped()
    {
        /*
        |--------------------------------------------------------------------------
        | READ
        |--------------------------------------------------------------------------
        */
        static::retrieved(function ($model) {
            if (!$model->shouldApplyFinancialYear()) {
                return;
            }

            $model->validateFinancialYearOnRead();
        });

        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        */
        static::creating(function ($model) {
            if ($model->shouldApplyFinancialYear()) {
                $model->validateFinancialYear();
            }
        });

        /*
        |--------------------------------------------------------------------------
        | UPDATE
        |--------------------------------------------------------------------------
        */
        static::updating(function ($model) {
            if ($model->shouldApplyFinancialYear()) {
                $model->validateFinancialYear(true);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | DELETE
        |--------------------------------------------------------------------------
        */
        static::deleting(function ($model) {
            if ($model->shouldApplyFinancialYear()) {
                $model->validateFinancialYear(true);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | PUBLIC TEMPORARY CONTROL (WHAT YOU ASKED)
    |--------------------------------------------------------------------------
    */

    /** Disable FY temporarily (runtime only) */
    public static function disableFinancialYearTemporarily(): void
    {
        static::$runtimeFinancialYearEnabled = false;
    }

    /** Enable FY back */
    public static function enableFinancialYearTemporarily(): void
    {
        static::$runtimeFinancialYearEnabled = true;
    }

    /*
    |--------------------------------------------------------------------------
    | CORE DECISION (GLOBAL + TEMP + MODEL)
    |--------------------------------------------------------------------------
    */

    protected function shouldApplyFinancialYear(): bool
    {
        // 1️⃣ GLOBAL SWITCH (DB / APP SETTING)
        if (!MstFinanceSetting::isFinancialYearEnabled()) {
            return false;
        }

        // 2️⃣ TEMPORARY RUNTIME SWITCH
        if (!static::$runtimeFinancialYearEnabled) {
            return false;
        }

        // 3️⃣ MASTER TABLE EXCLUSION
        if (in_array(static::class, static::$financialYearExcludedModels, true)) {
            return false;
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL HELPERS
    |--------------------------------------------------------------------------
    */

    protected function resolveFinancialDateColumn(): string
    {
        foreach ($this->financialDateColumns as $column) {
            if (Schema::hasColumn($this->getTable(), $column)) {
                return $column;
            }
        }

        throw new \LogicException(
            static::class . ' has no valid financial date column.'
        );
    }

    protected function validateFinancialYearOnRead(): void
    {
        $column = $this->resolveFinancialDateColumn();
        $date = $this->{$column};

        if (!$date) {
            return;
        }

        if ($date < currentFyStart() || $date > currentFyEnd()) {
            throw ValidationException::withMessages([
                'financial_year' =>
                    'You are trying to access data outside the active financial year.',
            ]);
        }
    }

    protected function validateFinancialYear(bool $useOriginal = false): void
    {
        $column = $this->resolveFinancialDateColumn();

        $date = $useOriginal
            ? $this->getOriginal($column)
            : ($this->{$column} ?? now());

        if ($date < currentFyStart() || $date > currentFyEnd()) {
            throw ValidationException::withMessages([
                'financial_year' =>
                    'Operation not allowed outside the active financial year.',
            ]);
        }
    }
}

// Temporary Off
//Order::disableFinancialYearTemporarily();
//
//try {
//    $data = Order::all(); // no FY restriction
//} finally {
//    Order::enableFinancialYearTemporarily();
//}
