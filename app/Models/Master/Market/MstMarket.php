<?php

namespace App\Models\Master\Market;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MstMarket extends BaseModel
{
    //

    use SoftDeletes;


    protected $fillable = [
        'name',
        'code',
        'addr_code',
        'is_active',
    ];



    // scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }


    // relationships
    public function address()
    {
        return $this->belongsTo(\App\Models\Common\Address::class, 'addr_code', 'addr_code');
    }

    public function depots()
    {
        return $this->hasMany(\App\Models\Master\Depot\MstDepot::class, 'market_id');
    }



    // booted to geenrate code if not provided
    protected static function booted()
    {
        static::creating(function ($market) {
            if (empty($market->code)) {
                $market->code = $market->generateUniqueCode();
            }
        });
    }

    private function generateUniqueCode(): string
    {
        $prefix = 'MKT';
        $latestMarket = self::withTrashed()->where('code', 'like', $prefix . '%')->latest('id')->first();

        if (!$latestMarket) {
            return $prefix . '0001';
        }

        $latestNumber = (int) substr($latestMarket->code, strlen($prefix));
        $newNumber = str_pad($latestNumber + 1, 4, '0', STR_PAD_LEFT);

        return $prefix . $newNumber;
    }
}
