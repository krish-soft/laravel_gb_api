<?php

namespace App\Models\Seller\Product;

use App\Models\BaseModel;
use App\Models\Buyer\Order\OrderItem;
use App\Models\Common\Fulfillment\FulfillmentLocation;
use App\Models\Common\Invoice\Invoice;
use App\Models\Common\Log\ActivityLog;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Master\Unique\MstSeqCodeGenerator;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProductListing extends BaseModel
{
    //

    protected static function booted()
    {
        parent::booted();

        static::creating(function ($model) {

            if (is_null($model->listing_code)) {

                $date = now()->format('Ymd');

                do {
                    $next = DB::table('product_listings')
                        ->whereDate('created_at', now()->toDateString())
                        ->lockForUpdate()
                        ->max(DB::raw("CAST(SUBSTRING_INDEX(listing_code, '-', -1) AS UNSIGNED)")) + 1;

                    $code = 'PL-' . $date . '-' . str_pad($next, 6, '0', STR_PAD_LEFT);

                    $exists = DB::table('product_listings')
                        ->where('listing_code', $code)
                        ->exists();
                } while ($exists);

                $model->listing_code = $code;
            }

            if (is_null($model->expires_at)) {
                $model->expires_at = now()->addHours(24);
            }
        });
    }

    protected $fillable = [
        'picture',
        'seller_id',

        'fulfillment_location_id',
        'listing_code',

        'listing_date',

        'is_sell_to_market',
        'is_seller_dropoff',

        'is_active',
        'inactive_reason',

        'is_cutoff',
        'is_partial',
        'is_sold',
        'is_locked',

        'is_expired',
        'expires_at',
    ];

    // casts
    protected $casts = [
        'listing_date' => 'date:Y-m-d',
        'expires_at' => 'datetime',
        'is_sell_to_market' => 'boolean',
        'is_seller_dropoff' => 'boolean',
        'is_active' => 'boolean',
        'is_partial' => 'boolean',
        'is_sold' => 'boolean',
        'is_locked' => 'boolean',
        'is_expired' => 'boolean',
        'is_cutoff' => 'boolean',
    ];

    // scope
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Relationships
    public function listingItems()
    {
        return $this->hasMany(ProductListingItem::class, 'product_listing_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function fulfillmentLocation()
    {
        return $this->belongsTo(FulfillmentLocation::class, 'fulfillment_location_id');
    }

    public function productListingInvoices()
    {
        return $this->hasMany(Invoice::class, 'product_listing_id', 'id');
    }

    public function shipmentPackages()
    {
        return $this->hasMany(ShipmentPackage::class, 'source_id')->where('source', ProductListing::class);
    }

    public function orderItems()
    {
        return $this->hasManyThrough(
            OrderItem::class,
            ProductListingItem::class,
            'product_listing_id', // Foreign key on listing item
            'product_listing_item_id', // Foreign key on order item
            'id', // Local key on listing
            'id' // Local key on listing item
        );
    }


    // Logs
    public function activityLogs()
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }


    protected $appends = [
        'total_qty',
        'total_sold_qty',
        'total_available_qty',

        'total_weight',
        'total_sold_weight',
        'total_available_weight',
    ];

    // get total qty 
    public function getTotalQtyAttribute()
    {
        return $this->listingItems()
            ->join('product_listing_packages', 'product_listing_items.id', '=', 'product_listing_packages.product_listing_item_id')
            ->sum('product_listing_packages.qty');
    }

    // get total sold qty
    public function getTotalSoldQtyAttribute()
    {
        return $this->listingItems()
            ->join('product_listing_packages', 'product_listing_items.id', '=', 'product_listing_packages.product_listing_item_id')
            ->sum('product_listing_packages.sold_qty');
    }


    // get total available qty
    public function getTotalAvailableQtyAttribute()
    {
        return $this->listingItems()
            ->join('product_listing_packages', 'product_listing_items.id', '=', 'product_listing_packages.product_listing_item_id')
            ->selectRaw('SUM(product_listing_packages.qty - product_listing_packages.sold_qty) as available_qty')
            ->value('available_qty');
    }

    // get total weight
    public function getTotalWeightAttribute()
    {
        return $this->listingItems()
            ->join('product_listing_packages', 'product_listing_items.id', '=', 'product_listing_packages.product_listing_item_id')
            ->sum(DB::raw('product_listing_packages.qty * product_listing_packages.pack_size'));
    }

    // get total sold weight
    public function getTotalSoldWeightAttribute()
    {
        return $this->listingItems()
            ->join('product_listing_packages', 'product_listing_items.id', '=', 'product_listing_packages.product_listing_item_id')
            ->sum(DB::raw('product_listing_packages.sold_qty * product_listing_packages.pack_size'));
    }

    // get total available weight
    public function getTotalAvailableWeightAttribute()
    {
        return $this->listingItems()
            ->join('product_listing_packages', 'product_listing_items.id', '=', 'product_listing_packages.product_listing_item_id')
            ->selectRaw('SUM((product_listing_packages.qty - product_listing_packages.sold_qty) * product_listing_packages.pack_size) as available_weight')
            ->value('available_weight');
    }
}
