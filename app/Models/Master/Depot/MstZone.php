<?php

namespace App\Models\Master\Depot;

use App\Models\BaseModel;
use App\Models\Master\MstState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MstZone extends BaseModel
{
    //

    use SoftDeletes;


    protected $fillable = [
        'state_id',
        'name',
        'code',
        'is_active',
    ];

    // scope

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Relationships
    public function state()
    {
        return $this->belongsTo(MstState::class, 'state_id');
    }

    public function depots()
    {
        return $this->hasMany(MstDepot::class, 'zone_id');
    }
}
