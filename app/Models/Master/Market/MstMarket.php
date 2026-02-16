<?php

namespace App\Models\Master\Market;

use App\Models\BaseModel;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Master\Depot\MstDepot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MstMarket extends BaseModel
{
    //

    use SoftDeletes;


    protected $fillable = [
        'name',
        'code',
        'fulfillment_location_id',

        'is_active',
    ];



    // scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }


    // relationships

    public function fulfillmentLocation()
    {
        return $this->belongsTo(FulfillmentLocation::class, 'fulfillment_location_id');
    }


    public function depots()
    {
        return $this->hasMany(MstDepot::class, 'market_id');
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
