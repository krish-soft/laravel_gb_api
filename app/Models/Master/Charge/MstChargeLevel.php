<?php

namespace App\Models\Master\Charge;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MstChargeLevel extends BaseModel
{
    //
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'user_role_type',
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

    public function charges()
    {
        return $this->hasMany(MstCharge::class, 'charge_level_id');
    }
}
