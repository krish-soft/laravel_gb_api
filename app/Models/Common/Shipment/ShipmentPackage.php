<?php

namespace App\Models\Common\Shipment;

use App\Models\BaseModel;
use App\Models\Buyer\Order\DemandOrder;
use App\Models\Buyer\Order\DemandOrderItem;
use App\Models\Buyer\Order\Order;
use App\Models\Buyer\Order\OrderItem;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Common\Package\SellerPackage;
use App\Models\Market\MarketOrder;
use App\Models\Market\MarketOrderItem;
use App\Models\Master\Depot\MstDepot;
use App\Models\Master\Product\MstProduct;
use App\Models\Master\Product\MstProductVariant;
use App\Models\Seller\Product\ProductListing;
use App\Models\Seller\Product\ProductListingItem;
use App\Models\Seller\Product\ProductListingPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShipmentPackage extends BaseModel
{
    //
    use SoftDeletes;

    protected $fillable = [
        'shipment_id',
        'shipment_package_number',

        'seller_package_id',

        'source',
        'source_id',

        'source_item',
        'source_item_id',

        'source_pkg',
        'source_pkg_id',

        'buyer_id',
        'seller_id',

        'product_listing_package_id',
        'product_listing_item_id',
        'product_listing_id',

        'product_id',
        'product_variant_id',

        'qty',
        'pack_size',
        'pack_unit',
        'pack_price',
        'pack_type_unit',


        'package_number',
        'package_number_buyer',
        'package_number_seller',
        'package_number_market',

        'status',
        'is_seller_dropoff',
        'is_buyer_pickup',
    ];

    protected $casts = [

        'shipment_date' => 'date:Y-m-d',
        'is_seller_dropoff' => 'boolean',
        'is_buyer_pickup' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id')->select('id', 'name', 'user_code', 'nickname');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id')->select('id', 'name', 'user_code', 'nickname');
    }


    public function sellerPackage()
    {
        return $this->belongsTo(SellerPackage::class, 'seller_package_id');
    }


    public function product()
    {
        return $this->belongsTo(MstProduct::class, 'product_id');
    }

    public function productVariant()
    {
        return $this->belongsTo(MstProductVariant::class, 'product_variant_id');
    }


    public function order()
    {
        return $this->belongsTo(Order::class, 'source_id', 'id')->where('source', Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'source_item_id', 'id')->where('source_item', OrderItem::class);
    }

    public function marketOrder()
    {
        return $this->belongsTo(MarketOrder::class, 'source_id', 'id')->where('source', MarketOrder::class);
    }

    public function marketOrderItem()
    {
        return $this->belongsTo(MarketOrderItem::class, 'source_item_id', 'id')->where('source_item', MarketOrderItem::class);
    }

    public function demandOrder()
    {
        return $this->belongsTo(DemandOrder::class, 'source_id', 'id')->where('source', DemandOrder::class);
    }

    public function demandOrderItem()
    {
        return $this->belongsTo(DemandOrderItem::class, 'source_item_id', 'id')->where('source_item', DemandOrderItem::class);
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
            if (empty($model->package_number)) {
                $model->package_number = self::generatePackageNumber(null, null, null);
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
        ?int $sellerId = null,
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
            $prefix = "MK-{$alpha}";
            $series = 'MKT';
            $seriesId = $marketId;
        }
        if (!empty($sellerId)) {

            $sellerIndex = $sellerId % 18278;
            $alpha  = self::alphaSequence($sellerIndex ?: 1);
            $prefix = "SL-{$alpha}";
            $series = 'SEL';
            $seriesId = $sellerId;
        } elseif (!empty($buyerId)) {

            $buyerIndex = $buyerId % 18278;
            $alpha  = self::alphaSequence($buyerIndex ?: 1);
            $prefix = "BY-{$alpha}";
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
            } elseif ($series === 'SEL') {
                $query->where('seller_id', $sellerId);
            }
            // else {
            //     // $query->whereNull('buyer_id')->whereNull('market_id');
            //     $query->whereNull('seller_id')->whereNull('buyer_id')->whereNull('market_id');
            // }

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

    // Only For Seller
    protected static array $runtimeSequenceSeller = [];
    public static function generatePackageNumberSeller(
        ?int $sellerId = null
    ): string {

        [$start, $end] = self::businessWindow();


        if (!empty($sellerId)) {
            $sellerIndex = $sellerId % 18278;
            $alpha  = self::alphaSequence($sellerIndex ?: 1);
            $prefix = "SL-{$alpha}";
            $series = 'SEL';
            $seriesId = $sellerId;
        } else {
            $prefix = 'SYS';
            $series = 'SYS';
            $seriesId = 0;
        }

        // ✅ Runtime key (prevents buyer/market collision)
        $key = $series . '|' . $seriesId . '|' . $start->timestamp . '|' . $end->timestamp;

        if (!isset(self::$runtimeSequenceSeller[$key])) {

            $query = self::whereBetween('created_at', [$start, $end])
                ->where('package_number_seller', 'like', "{$prefix}-%");

            // strict scoping
            $query->where('seller_id', $sellerId);

            $lastSeq = $query->selectRaw("
            MAX(
                CAST(SUBSTRING_INDEX(package_number_seller,'-',-1) AS UNSIGNED)
            ) as max_seq
        ")->value('max_seq');

            self::$runtimeSequenceSeller[$key] = (int) ($lastSeq ?? 0);
        }

        // 🔥 runtime increment
        self::$runtimeSequenceSeller[$key]++;

        return "{$prefix}-" . self::$runtimeSequenceSeller[$key];
    }


    protected static array $runtimeSequenceBuyer = [];
    public static function generatePackageNumberBuyer(
        ?int $buyerId = null
    ): string {

        [$start, $end] = self::businessWindow();


        if (!empty($buyerId)) {
            $buyerIndex = $buyerId % 18278;
            $alpha  = self::alphaSequence($buyerIndex ?: 1);
            $prefix = "BY-{$alpha}";
            $series = 'BUY';
            $seriesId = $buyerId;
        } else {
            $prefix = 'SYS';
            $series = 'SYS';
            $seriesId = 0;
        }

        // ✅ Runtime key (prevents buyer/market collision)
        $key = $series . '|' . $seriesId . '|' . $start->timestamp . '|' . $end->timestamp;

        if (!isset(self::$runtimeSequenceBuyer[$key])) {

            $query = self::whereBetween('created_at', [$start, $end])
                ->where('package_number_buyer', 'like', "{$prefix}-%");

            // strict scoping
            $query->where('buyer_id', $buyerId);

            $lastSeq = $query->selectRaw("
            MAX(
                CAST(SUBSTRING_INDEX(package_number_buyer,'-',-1) AS UNSIGNED)
            ) as max_seq
        ")->value('max_seq');

            self::$runtimeSequenceBuyer[$key] = (int) ($lastSeq ?? 0);
        }

        // 🔥 runtime increment
        self::$runtimeSequenceBuyer[$key]++;

        return "{$prefix}-" . self::$runtimeSequenceBuyer[$key];
    }
}
