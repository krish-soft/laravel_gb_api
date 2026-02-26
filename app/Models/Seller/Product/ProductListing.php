<?php

namespace App\Models\Seller\Product;

use App\Models\BaseModel;
use App\Models\Common\Fulfillment\FulfillmentLocation;
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

            // Check already exists doc_no for today
            do {
                $docNo = MstSeqCodeGenerator::getNextDocNo();
            } while (
                self::where('doc_no', $docNo)->exists()
            );

            $model->doc_no = $docNo;
        });
    }

    protected $fillable = [
        'picture',
        'seller_id',

        'fulfillment_location_id',
        'listing_code',

        'doc_no',
        'doc_date',

        'is_sell_to_market',
        'is_seller_dropoff',

        'is_active',
        'inactive_reason',

        'is_partial',
        'is_sold',
        'is_locked',

        'is_expired',
        'expires_at',
    ];

    // casts
    protected $casts = [
        'doc_date' => 'date',
        'expires_at' => 'datetime',
        'is_sell_to_market' => 'boolean',
        'is_seller_dropoff' => 'boolean',
        'is_active' => 'boolean',
        'is_partial' => 'boolean',
        'is_sold' => 'boolean',
        'is_locked' => 'boolean',
        'is_expired' => 'boolean',
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

    public function productListingInvoice()
    {
        return $this->hasOne(ProductListingInvoice::class, 'product_listing_id');
    }

    public function shipmentPackages()
    {
        return $this->hasMany(ShipmentPackage::class, 'product_listing_id');
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
}
