<?php

namespace App\Models\Master;

use App\Models\BaseModel;
use App\Models\Master\Charge\Rule\MstDeliveryChargeRule;
use App\Models\Master\Charge\Rule\MstMinimumOrderChargeRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MstUnit extends BaseModel
{
    //

    use SoftDeletes;

    protected $fillable = [
        'name',
        'unit',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // relations
    public function packTypes()
    {
        return $this->hasMany(MstPackType::class, 'unit', 'unit');
    }

    public function deliveryChargeRules()
    {
        return $this->hasMany(MstDeliveryChargeRule::class, 'measure_unit', 'unit');
    }
}
