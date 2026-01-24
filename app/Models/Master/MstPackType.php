<?php

namespace App\Models\Master;

use App\Models\BaseModel;
use App\Models\Master\Charge\Rule\MstDeliveryChargeRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MstPackType extends BaseModel
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
    public function unitDetails()
    {
        return $this->belongsTo(MstUnit::class, 'unit', 'unit');    
    }

    public function deliveryChargeRules()
    {
        return $this->hasMany(MstDeliveryChargeRule::class, 'pack_type_unit', 'unit');
    }

}
