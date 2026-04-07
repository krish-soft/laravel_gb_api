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
use App\Models\Master\Market\MstMarket;
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
        'parent_shipment_package_id', // from pickup mainly
        'depot_id', // Parent Depot id

        'shipment_trace_code',
        'shipment_package_number',

        'seller_package_id',

        'order_id',
        'order_item_id',

        'demand_order_id',
        'demand_order_item_id',

        'market_order_id',
        'market_order_item_id',

        // To Generate Unique Numbers
        'buyer_id',
        'seller_id',
        'market_id',

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
    public function sellerPackage()
    {
        return $this->belongsTo(SellerPackage::class, 'seller_package_id');
    }


    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id')->select('id', 'name', 'user_code', 'nickname');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id')->select('id', 'name', 'user_code', 'nickname');
    }

    public function market()
    {
        return $this->belongsTo(MstMarket::class, 'market_id');
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

    public function demandOrder()
    {
        return $this->belongsTo(DemandOrder::class, 'demand_order_id');
    }

    public function demandOrderItem()
    {
        return $this->belongsTo(DemandOrderItem::class, 'demand_order_item_id');
    }

    public function depot()
    {
        return $this->belongsTo(MstDepot::class, 'depot_id');
    }

    public function productListing()
    {
        return $this->belongsTo(ProductListing::class, 'product_listing_id');
    }

    public function productListingItem()
    {
        return $this->belongsTo(ProductListingItem::class, 'product_listing_item_id');
    }

    public function productListingPackage()
    {
        return $this->belongsTo(ProductListingPackage::class, 'product_listing_package_id');
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


            $model->shipment_trace_code = self::generateShipmentTraceCode($model);
        });
    }


    private static function generateUniqueShipmentNumber(): string
    {
        do {

            // PKG + Date + Random
            // Example: PKG240402A7F3
            $code = 'PKG'
                . now()->format('ymd')
                . strtoupper(Str::random(6));
        } while (
            self::withTrashed()
            ->where('shipment_package_number', $code)
            ->exists()
        );

        return $code;
    }


    private static function generateShipmentTraceCode(self $model): string
    {
        try {

            $shipment = $model->shipment;

            if (!$shipment) {
                throw new \Exception('Shipment not found');
            }

            $parts = [];

            // ORIGIN
            if ($shipment->originDepot && !empty($shipment->originDepot->depot_code)) {
                $parts[] = $shipment->originDepot->depot_code;
            } elseif ($shipment->originMarket && !empty($shipment->originMarket->market_code)) {
                $parts[] = $shipment->originMarket->market_code;
            } elseif ($shipment->originFulfillmentLocation && !empty($shipment->originFulfillmentLocation->location_code)) {
                $parts[] = $shipment->originFulfillmentLocation->location_code;
            }

            // DESTINATION
            if ($shipment->destinationDepot && !empty($shipment->destinationDepot->depot_code)) {
                $parts[] = $shipment->destinationDepot->depot_code;
            } elseif ($shipment->destinationMarket && !empty($shipment->destinationMarket->market_code)) {
                $parts[] = $shipment->destinationMarket->market_code;
            } elseif ($shipment->destinationFulfillmentLocation && !empty($shipment->destinationFulfillmentLocation->location_code)) {
                $parts[] = $shipment->destinationFulfillmentLocation->location_code;
            }

            // OWNER
            if ($model->buyer && !empty($model->buyer->user_code)) {
                $parts[] = 'BUY-' . $model->buyer->user_code;
            } elseif ($model->seller && !empty($model->seller->user_code)) {
                $parts[] = 'SLR-' . $model->seller->user_code;
            }

            if (!empty($parts)) {
                return implode('-', $parts);
            }

            throw new \Exception('Trace code empty');
        } catch (\Throwable $e) {

            // Fallback: generate unique random trace
            do {

                $code = 'TRC-' . strtoupper(\Illuminate\Support\Str::random(8));
            } while (
                self::withTrashed()
                ->where('shipment_trace_code', $code)
                ->exists()
            );

            return $code;
        }
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
            $prefix = "BU-{$alpha}";
            $series = 'BUY';
            $seriesId = $buyerId;
        } else {

            $prefix = 'SY';
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
            $prefix = 'SY';
            $series = 'SY-SEL';
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
            $prefix = "BU-{$alpha}";
            $series = 'BUY';
            $seriesId = $buyerId;
        } else {
            $prefix = 'SY';
            $series = 'SY-BUY';
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

    protected static array $runtimeSequenceMarket = [];
    public static function generatePackageNumberMarket(
        ?int $marketId = null
    ): string {

        [$start, $end] = self::businessWindow();


        if (!empty($marketId)) {
            $marketIndex = $marketId % 18278;
            $alpha  = self::alphaSequence($marketIndex ?: 1);
            $prefix = "MK-{$alpha}";
            $series = 'MKT';
            $seriesId = $marketId;
        } else {
            $prefix = 'SY-MKT';
            $series = 'SY-MKT';
            $seriesId = 0;
        }

        // ✅ Runtime key (prevents buyer/market collision)
        $key = $series . '|' . $seriesId . '|' . $start->timestamp . '|' . $end->timestamp;

        if (!isset(self::$runtimeSequenceMarket[$key])) {

            $query = self::whereBetween('created_at', [$start, $end])
                ->where('package_number_market', 'like', "{$prefix}-%");

            // strict scoping
            $query->where('market_id', $marketId);

            $lastSeq = $query->selectRaw("
            MAX(
                CAST(SUBSTRING_INDEX(package_number_market,'-',-1) AS UNSIGNED)
            ) as max_seq
        ")->value('max_seq');

            self::$runtimeSequenceMarket[$key] = (int) ($lastSeq ?? 0);
        }

        // 🔥 runtime increment
        self::$runtimeSequenceMarket[$key]++;

        return "{$prefix}-" . self::$runtimeSequenceMarket[$key];
    }
}
