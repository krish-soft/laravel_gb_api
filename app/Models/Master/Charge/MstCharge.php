<?php

namespace App\Models\Master\Charge;

use App\Models\BaseModel;
use App\Models\Master\Charge\Rule\MstDeliveryChargeRule;
use App\Models\Master\Charge\Rule\MstMinimumOrderChargeRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MstCharge extends BaseModel
{
    //

    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];


    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Scope
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Relationships
    public function minimumRuleCharges()
    {
        return $this->hasMany(MstMinimumOrderChargeRule::class, 'charge_id');
    }

    public function deliveryRuleCharges()
    {
        return $this->hasMany(MstDeliveryChargeRule::class, 'charge_id');
    }
}
