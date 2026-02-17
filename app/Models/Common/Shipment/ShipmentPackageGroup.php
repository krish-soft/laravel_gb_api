<?php

namespace App\Models\Common\Shipment;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShipmentPackageGroup extends BaseModel
{
    //

    use SoftDeletes;

    protected $fillable = [
        'group_number',
        'shipment_id',
        'shipment_package_id',

        'buyer_id',
        'seller_id',
    ];


    // relationships
    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function shipmentPackage()
    {
        return $this->belongsTo(ShipmentPackage::class);
    }


    public function buyer()
    {
        // only return id,name,user_code,nickname
        return $this->belongsTo(User::class, 'buyer_id')->select(['id', 'name', 'user_code', 'nickname']);
    }

    public function seller()
    {
        // only return id,name,user_code,nickname
        return $this->belongsTo(User::class, 'seller_id')->select(['id', 'name', 'user_code', 'nickname']);
    }

    // Generate unique group number (e.g. G-20260212-0001)
    public static function generateUniqueGroupNumber(): string
    {
        // $datePart = date('Ymd');
        $counter = 1;
        do {
            // $groupNumber = sprintf('G-%s-%04d', $datePart, $counter);
            $groupNumber = sprintf('G-%04d', $counter);
            $exists = self::withTrashed()->where('group_number', $groupNumber)->exists();
            if (!$exists) {
                return $groupNumber;
            }
            $counter++;
        } while (true);
    }



    //
}
