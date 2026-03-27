<?php

namespace App\Models\Master\Price;

use App\Models\BaseModel;
use App\Models\Master\Unique\MstSeqCodeGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MstProductPriceRule extends BaseModel
{
    //

    use SoftDeletes;

    protected static function booted()
    {
        static::creating(function ($model) {
            $ruleNo = null;

            do {
                $ruleNo = MstSeqCodeGenerator::getNextRuleNo();
            } while (self::where('rule_no', $ruleNo)->exists());

            $model->rule_no = $ruleNo;
        });
    }


    protected $fillable = [

        'rule_no',
        'charge_level_id', // B-STD
        'user_type', // 

        'pack_unit',
        'calc_type',

        '1_pkg',
        '2_pkg',
        '3_pkg',
        '5_pkg',
        '10_pkg',
        '20_pkg',

        'is_active',

    ];

    // casts

    protected $casts = [
        '1_pkg' => 'decimal:2',
        '2_pkg' => 'decimal:2',
        '3_pkg' => 'decimal:2',
        '5_pkg' => 'decimal:2',
        '10_pkg' => 'decimal:2',
        '20_pkg' => 'decimal:2',

        'is_active' => 'boolean',
    ];

    // Scope
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Relationships

    public function chargeLevel()
    {
        return $this->belongsTo(\App\Models\Master\Charge\MstChargeLevel::class, 'charge_level_id', 'id');
    }



    //
}
