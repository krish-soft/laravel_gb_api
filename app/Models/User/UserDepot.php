<?php

namespace App\Models\User;

use App\Models\BaseModel;
use App\Models\Master\Depot\MstDepot;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserDepot extends BaseModel
{
    //


    protected $fillable = [
        'user_id',
        'depot_id',
        'is_primary',
        'is_active',
    ];


    // casts
    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    // scope

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // relationships

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function depot()
    {
        return $this->belongsTo(MstDepot::class);
    }
}
