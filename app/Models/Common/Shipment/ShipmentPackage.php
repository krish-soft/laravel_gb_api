<?php

namespace App\Models\Common\Shipment;

use App\Models\Buyer\Order\Order;
use App\Models\Buyer\Order\OrderItem;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Master\Depot\MstDepot;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ShipmentPackage extends Model
{
    //

    use SoftDeletes;


    protected $fillable = [
        'order_id',
        'order_number',
        'order_item_id',

        'buyer_id',
        'seller_id',

        'pickup_fulfillment_location_id',
        'shipping_fulfillment_location_id',

        'pickup_depot_id',
        'shipping_depot_id',

        'qty',
        'pack_size',
        'pack_unit',
        'pack_type_unit',

        'shipment_number',
        'package_number',

        'status',

        'carrier',
        'tracking_number',
        'remarks',
    ];

    protected $casts = [
        'qty' => 'integer',
        'pack_size' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function pickupFulfillmentLocation()
    {
        return $this->belongsTo(FulfillmentLocation::class, 'pickup_fulfillment_location_id');
    }

    public function shippingFulfillmentLocation()
    {
        return $this->belongsTo(FulfillmentLocation::class, 'shipping_fulfillment_location_id');
    }

    public function pickupDepot()
    {
        return $this->belongsTo(MstDepot::class, 'pickup_depot_id');
    }

    public function shippingDepot()
    {
        return $this->belongsTo(MstDepot::class, 'shipping_depot_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Booted: Generate UNIQUE shipment_number (8-char alphanumeric)
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->shipment_number)) {
                $model->shipment_number = self::generateUniqueShipmentNumber();
            }
        });
    }

    private static function generateUniqueShipmentNumber(): string
    {
        do {
            // Example: A9F3K2Q8 (8 chars)
            $code = strtoupper(Str::random(8));
        } while (
            self::withTrashed()
            ->where('shipment_number', $code)
            ->exists()
        );

        return $code;
    }


    private static function businessDate(): string
    {
        $now = now();

        $startHour = 14; // 2 PM
        $endHour   = 11; // 11 AM

        /**
         * Logic:
         * - From 14:00 onwards → same calendar date
         * - From 00:00 to 10:59 → previous calendar date
         * - From 11:00 to 13:59 → NEW day window
         */

        if ($now->hour >= $startHour) {
            // 2 PM → 11:59 PM
            return $now->toDateString();
        }

        if ($now->hour < $endHour) {
            // 12 AM → 10:59 AM
            return $now->subDay()->toDateString();
        }

        // 11:00 AM → 1:59 PM (reset window)
        return $now->toDateString();
    }

    /*
    |--------------------------------------------------------------------------
    | Package Number Generator (CALL EXPLICITLY, NOT AUTO)
    |--------------------------------------------------------------------------
    | Pattern:
    | Buyer A → A-1, A-2 (daily reset)
    | Buyer B → B-1, B-2
    |--------------------------------------------------------------------------
    */
    public static function generatePackageNumber(?int $buyerId): string
    {
        $businessDate = self::businessDate();

        // Prefix logic stays SAME
        if ($buyerId) {
            $buyerIndex = $buyerId % 18278;
            $prefix = self::alphaSequence($buyerIndex ?: 1);
        } else {
            $prefix = 'SYS'; // reserved forever
        }

        $lastSeq = self::where('package_number', 'like', "{$prefix}-%")
            ->whereDate('created_at', $businessDate)
            ->whereNull('deleted_at')
            ->selectRaw("
            MAX(
                CAST(SUBSTRING_INDEX(package_number, '-', -1) AS UNSIGNED)
            ) as max_seq
        ")
            ->value('max_seq');

        $next = ($lastSeq ?? 0) + 1;

        return "{$prefix}-{$next}";
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: Convert number → alphabet (1=A, 2=B ... 27=AA)
    |--------------------------------------------------------------------------
    */

    private static function alphaSequence(int $number): string
    {
        $result = '';

        while ($number > 0) {
            $number--;
            $result = chr(65 + ($number % 26)) . $result;
            $number = intdiv($number, 26);
        }

        return $result;
    }
}
