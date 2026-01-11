<?php

namespace App\Models\Master\Depot;

use App\Models\Address;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MstDepot extends BaseModel
{
    //

    use SoftDeletes;

    protected static function booted()
    {
        static::creating(function ($depot) {

            // If code is already set (eg: manual insert), do nothing
            if (! empty($depot->code)) {
                return;
            }

            // Get last numeric sequence
            $lastNumber = self::withTrashed()
                ->where('code', 'like', 'DPT%')
                ->orderBy('id', 'desc')
                ->value('code');

            $next = 1;

            if ($lastNumber) {
                $next = (int) substr($lastNumber, 4) + 1;
            }

            $depot->code = 'DPT' . str_pad($next, 4, '0', STR_PAD_LEFT);
        });
    }

    protected $fillable = [
        'zone_id',

        'picture',
        'name',
        'code',
        'other_code',

        'addr_code',

        'max_capacity_kg',
        'current_load_kg',

        'buyer_cutoff_time',
        'seller_cutoff_time',

        'contact_name',
        'contact_phone',
        'contact_email',
        'is_active',

        'notes',
        'custchar1',
        'custchar2',
    ];

    // Casts
    protected $casts = [
        'is_active' => 'boolean',
    ];

    // scoope

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Relationships 

    public function address()
    {
        return $this->morphOne(Address::class, 'addr_code');
    }

    public function zone()
    {
        return $this->belongsTo(MstZone::class, 'zone_id');
    }
}
