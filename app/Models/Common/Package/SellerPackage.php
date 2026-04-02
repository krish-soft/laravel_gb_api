<?php

namespace App\Models\Common\Package;

use App\Models\BaseModel;
use App\Models\Common\Shipment\ShipmentPackage;
use App\Models\Master\Product\MstProduct;
use App\Models\Master\Product\MstProductVariant;
use App\Models\Seller\Product\ProductListing;
use App\Models\Seller\Product\ProductListingItem;
use App\Models\Seller\Product\ProductListingPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SellerPackage extends BaseModel
{
    //

    protected static function booted()
    {
        static::creating(function ($model) {

            if (empty($model->package_uid)) {

                do {
                    $uid = 'PKG' . strtoupper(substr(uniqid(), -10));
                } while (self::where('package_uid', $uid)->exists());

                $model->package_uid = $uid;
            }

            if (empty($model->package_number)) {
                $model->package_number = self::generatePackageNumberSeller($model->seller_id);
            }
        });
    }


    protected $fillable = [
        'seller_id',

        'product_listing_package_id',
        'product_listing_item_id',
        'product_listing_id',

        'product_id',
        'product_variant_id',
        'package_date',

        'package_uid',
        'package_number',

        'is_used',
        'is_seller_dropoff',
    ];



    // casts
    protected $casts = [
        'package_date' => 'date:Y-m-d',
        'is_used' => 'boolean',
        'is_seller_dropoff' => 'boolean',
    ];


    // relationships

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function productListingPackage()
    {
        return $this->belongsTo(ProductListingPackage::class, 'product_listing_package_id');
    }

    public function productListingItem()
    {
        return $this->belongsTo(ProductListingItem::class, 'product_listing_item_id');
    }

    public function productListing()
    {
        return $this->belongsTo(ProductListing::class, 'product_listing_id');
    }

    public function product()
    {
        return $this->belongsTo(MstProduct::class, 'product_id');
    }

    public function productVariant()
    {
        return $this->belongsTo(MstProductVariant::class, 'product_variant_id');
    }

    public function shipmentPackage()
    {
        return $this->hasOne(ShipmentPackage::class, 'seller_package_id');
    }


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
            $series = 'SYS';
            $seriesId = 0;
        }

        // ✅ Runtime key (prevents buyer/market collision)
        $key = $series . '|' . $seriesId . '|' . $start->timestamp . '|' . $end->timestamp;

        if (!isset(self::$runtimeSequenceSeller[$key])) {

            $query = self::whereBetween('created_at', [$start, $end])
                ->where('package_number', 'like', "{$prefix}-%");

            // strict scoping
            $query->where('seller_id', $sellerId);

            $lastSeq = $query->selectRaw("
            MAX(
                CAST(SUBSTRING_INDEX(package_number,'-',-1) AS UNSIGNED)
            ) as max_seq
        ")->value('max_seq');

            self::$runtimeSequenceSeller[$key] = (int) ($lastSeq ?? 0);
        }

        // 🔥 runtime increment
        self::$runtimeSequenceSeller[$key]++;

        return "{$prefix}-" . self::$runtimeSequenceSeller[$key];
    }





    //
}
