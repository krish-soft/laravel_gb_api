<?php

namespace App\Models\Master\Charge\Rule;

use App\Models\BaseModel;
use App\Models\Master\Charge\MstCharge;
use App\Models\Master\Charge\MstChargeLevel;
use App\Models\Master\Unique\MstSeqCodeGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MstDeliveryChargeRule extends BaseModel
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
        'charge_id',
        'charge_level_id',
        'rule_no',
        'description',
        'calc_type',
        'measure_value',
        'measure_unit',
        'pack_type_unit',
        'charge_amount',
        'is_active',
    ];

    protected $guarded = ['rule_no'];


    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Scope
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Relationships

    public function charge()
    {
        return $this->belongsTo(MstCharge::class, 'charge_id');
    }

    public function chargeLevel()
    {
        return $this->belongsTo(MstChargeLevel::class, 'charge_level_id');
    }
}
