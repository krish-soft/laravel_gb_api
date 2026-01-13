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
        'is_taxable',
        'cgst_percent',
        'sgst_percent',
        'utgst_percent',
        'igst_percent',
        'applicable_state_code',

    ];


    protected $casts = [
        'is_active' => 'boolean',
        'is_taxable' => 'boolean',
        'cgst_percent' => 'decimal:2',
        'sgst_percent' => 'decimal:2',
        'utgst_percent' => 'decimal:2',
        'igst_percent' => 'decimal:2',
        'applicable_state_code' => 'array',
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
