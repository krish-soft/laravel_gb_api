<?php

namespace App\Models\Master\Product;

use App\Models\BaseModel;
use App\Models\Seller\Product\ProductListing;
use App\Models\Seller\Product\ProductListingItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MstProduct extends BaseModel
{
    //

    use SoftDeletes;

    protected static function booted()
    {
        static::creating(function ($product) {

            // If code already provided (seeder/manual), do nothing
            if (!empty($product->product_code)) {
                return;
            }

            if (empty($product->category_id)) {
                throw new \RuntimeException('Category is required to generate product code.');
            }

            $category = MstProductCategory::find($product->category_id);

            if (!$category || empty($category->hsn_chapter)) {
                throw new \RuntimeException('Invalid category or missing HSN chapter.');
            }

            $prefix = $category->hsn_chapter . 'P';

            // Find last product code for this category
            $lastCode = self::withTrashed()
                ->where('category_id', $product->category_id)
                ->where('product_code', 'like', $prefix . '%')
                ->orderBy('product_code', 'desc')
                ->value('product_code');

            $next = 1;

            if ($lastCode) {
                $next = (int)substr($lastCode, -3) + 1;
            }

            $product->product_code =
                $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
        });
    }

    protected $fillable = [
        'picture',
        'category_id',

        'product_code',
        'name',
        'description',

        'upc',
        'hsn',
        'sku',

        'grade',
        'size',
        'origin',

        'is_active',

        'custchar1',
        'custchar2',


    ];

    protected $guarded = [
        'product_code'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // scope
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Relationships
    public function category()
    {
        return $this->belongsTo(MstProductCategory::class, 'category_id');
    }

    public function variants()
    {
        return $this->hasMany(MstProductVariant::class, 'product_id')->active();
    }

    public function packagings()
    {
        return $this->hasMany(MstProductPackaging::class, 'product_id')->active();
    }

    public function farmerListingItems()
    {
        return $this->hasMany(ProductListingItem::class, 'product_id', 'id');
    }


    protected $appends = ['pictureUrl'];

    public function getPictureUrlAttribute()
    {
        if ($this->picture) {
            return Storage::disk('public')->url($this->picture);
        }
        return null;
    }
}
