<?php

namespace App\Models\Common\Shipment;

use App\Models\Buyer\Order\Order;
use App\Models\Buyer\Order\OrderItem;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Market\MarketOrder;
use App\Models\Market\MarketOrderItem;
use App\Models\Master\Depot\MstDepot;
use App\Models\Seller\Product\ProductListing;
use App\Models\Seller\Product\ProductListingPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShipmentPackage extends Model
{
    //

    use SoftDeletes;


    protected $fillable = [
        'order_id',
        'order_item_id',

        'market_order_id',
        'market_order_item_id',

        'buyer_id',
        'seller_id',

        'pickup_fulfillment_location_id',
        'shipping_fulfillment_location_id',

        'pickup_depot_id',
        'shipping_depot_id',

        'product_listing_package_id',
        'product_listing_id',

        'order_type',
        'market_id',

        'shipment_date',

        'product_code',
        'product_name',

        'qty',
        'pack_size',
        'pack_price',
        'pack_unit',
        'pack_type_unit',

        
        'shipment_package_number',
        'package_number',

        'status',
        'action_status',

        'seller_status', // When Pickup
        'buyer_status', // When Delivery
        'transfer_status', // When transfer between depots or fulfillment locations
        'other_status', // any other status we want to track like short shipment, pickup fail, delivery fail, damage etc.

        'carrier',
        'tracking_number',
        'remarks',

        'packed_at',
        'picked_up_at',
        'in_transit_at',
        'delivered_at',
        'returned_at',
        'cancelled_at',

        'is_seller_dropoff',
        'is_buyer_pickup',
    ];

    protected $casts = [
        'qty' => 'integer',
        'pack_size' => 'decimal:2',
        'pack_price' => 'decimal:2',
        'shipment_date' => 'date',

        'packed_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'in_transit_at' => 'datetime',
        'delivered_at' => 'datetime',
        'returned_at' => 'datetime',
        'cancelled_at' => 'datetime',

        'is_seller_dropoff' => 'boolean',
        'is_buyer_pickup' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function marketOrder()
    {
        return $this->belongsTo(MarketOrder::class, 'market_order_id');
    }

    public function marketOrderItem()
    {
        return $this->belongsTo(MarketOrderItem::class, 'market_order_item_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id')->select('id', 'name', 'user_code', 'nickname');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id')->select('id', 'name', 'user_code', 'nickname');
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

    public function packageGroup()
    {
        return $this->hasOne(ShipmentPackageGroup::class, 'shipment_package_id');
    }

    public function productListingPackage()
    {
        return $this->belongsTo(ProductListingPackage::class, 'product_listing_package_id');
    }

    public function productListing()
    {
        return $this->belongsTo(ProductListing::class, 'product_listing_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Booted: Generate UNIQUE shipment_number (8-char alphanumeric)
    |--------------------------------------------------------------------------
    */

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->shipment_package_number)) {
                $model->shipment_package_number = self::generateUniqueShipmentNumber();
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
            ->where('shipment_package_number', $code)
            ->exists()
        );

        return $code;
    }


    private static function businessWindow(): array
    {
        $now = now();


        // If before 2 PM → we are still in previous business day
        if ($now->hour < 14) {
            $start = $now->copy()->subDay()->setTime(14, 0, 0); // yesterday 2 PM
            $end   = $now->copy()->setTime(13, 59, 59);         // today 1:59:59 PM
        } else {
            $start = $now->copy()->setTime(14, 0, 0);           // today 2 PM
            $end   = $now->copy()->addDay()->setTime(13, 59, 59); // tomorrow 1:59:59 PM
        }

        // If before 11 AM → we are still in previous business day
        // if ($now->hour < 11) {
        //     $start = $now->copy()->subDay()->setTime(14, 0, 0); // yesterday 2 PM
        //     $end   = $now->copy()->setTime(10, 59, 59);        // today 10:59 AM
        // } else {
        //     $start = $now->copy()->setTime(14, 0, 0);          // today 2 PM
        //     $end   = $now->copy()->addDay()->setTime(10, 59, 59); // tomorrow 10:59 AM
        // }

        return [$start, $end];
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
    protected static array $runtimeSequence = [];

    public static function generatePackageNumber(
        ?int $buyerId = null,
        ?int $marketId = null
    ): string {

        [$start, $end] = self::businessWindow();

        // ✅ PREFIX RESOLVER (separate pattern)
        // Buyer  → A-5
        // Market → M-A-5
        // System → SYS-5

        if (!empty($marketId)) {

            $marketIndex = $marketId % 18278;
            $alpha  = self::alphaSequence($marketIndex ?: 1);
            $prefix = "M-{$alpha}";
            $series = 'MKT';
            $seriesId = $marketId;
        } elseif (!empty($buyerId)) {

            $buyerIndex = $buyerId % 18278;
            $prefix = self::alphaSequence($buyerIndex ?: 1);
            $series = 'BUY';
            $seriesId = $buyerId;
        } else {

            $prefix = 'SYS';
            $series = 'SYS';
            $seriesId = 0;
        }

        // ✅ Runtime key (prevents buyer/market collision)
        $key = $series . '|' . $seriesId . '|' . $start->timestamp . '|' . $end->timestamp;

        if (!isset(self::$runtimeSequence[$key])) {

            $query = self::whereBetween('created_at', [$start, $end])
                ->where('package_number', 'like', "{$prefix}-%");

            // strict scoping
            if ($series === 'MKT') {
                $query->where('market_id', $marketId);
            } elseif ($series === 'BUY') {
                $query->where('buyer_id', $buyerId);
            } else {
                $query->whereNull('buyer_id')->whereNull('market_id');
            }

            $lastSeq = $query->selectRaw("
            MAX(
                CAST(SUBSTRING_INDEX(package_number,'-',-1) AS UNSIGNED)
            ) as max_seq
        ")->value('max_seq');

            self::$runtimeSequence[$key] = (int) ($lastSeq ?? 0);
        }

        // 🔥 runtime increment
        self::$runtimeSequence[$key]++;

        return "{$prefix}-" . self::$runtimeSequence[$key];
    }


    // public static function generatePackageNumber(int $buyerId): string
    // {
    //     [$start, $end] = self::businessWindow();

    //     // Buyer prefix
    //     $buyerIndex = $buyerId % 18278;
    //     $prefix = self::alphaSequence($buyerIndex ?: 1);

    //     // Unique runtime key per buyer + window
    //     $key = $buyerId . '|' . $start->timestamp . '|' . $end->timestamp;

    //     // 🔥 Load from DB only once per request
    //     if (!isset(self::$runtimeSequence[$key])) {

    //         $lastSeq = self::where('buyer_id', $buyerId)
    //             ->whereBetween('created_at', [$start, $end])
    //             ->where('package_number', 'like', "{$prefix}-%")
    //             ->selectRaw("
    //             MAX(
    //                 CAST(SUBSTRING_INDEX(package_number, '-', -1) AS UNSIGNED)
    //             ) as max_seq
    //         ")
    //             ->value('max_seq');

    //         self::$runtimeSequence[$key] = (int) ($lastSeq ?? 0);
    //     }

    //     // 🔥 Increment locally (THIS fixes C-1,C-1,C-1 issue)
    //     self::$runtimeSequence[$key]++;

    //     return "{$prefix}-" . self::$runtimeSequence[$key];
    // }





    // public static function generatePackageNumber(
    //     ?int $buyerId,
    //     ?string $date = null
    // ): string {
    //     $date = $date ?? now()->toDateString();

    //     // Determine prefix
    //     if ($buyerId) {
    //         // Stable buyer-based prefix
    //         $buyerIndex = $buyerId % 18278; // limit size
    //         $prefix = self::alphaSequence($buyerIndex ?: 1);
    //     } else {
    //         // Reserved system prefix (NEVER assigned to buyers)
    //         $prefix = 'SYS';
    //     }

    //     // Get last sequence number for this prefix + day
    //     $lastSeq = self::where('package_number', 'like', "{$prefix}-%")
    //         ->whereDate('created_at', $date)
    //         ->whereNull('deleted_at')
    //         ->selectRaw("
    //         MAX(
    //             CAST(SUBSTRING_INDEX(package_number, '-', -1) AS UNSIGNED)
    //         ) as max_seq
    //     ")
    //         ->value('max_seq');

    //     $next = ($lastSeq ?? 0) + 1;

    //     return "{$prefix}-{$next}";
    // }


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
