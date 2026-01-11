<?php

namespace App\Models\Master\Product;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MstProductVariant extends BaseModel
{
    //

     use SoftDeletes;


    protected static function booted()
    {
        static::creating(function ($variant) {

            // If variant_code already provided (seeder/manual), do nothing
            if (! empty($variant->variant_code)) {
                return;
            }

            if (empty($variant->product_id)) {
                throw new \Exception('Product is required to generate variant code.');
            }

            $product = MstProduct::find($variant->product_id);

            if (! $product || empty($product->product_code)) {
                throw new \Exception('Invalid product or missing product code.');
            }

            $prefix = $product->product_code . 'V';

            // Find last variant for this product
            $lastCode = self::withTrashed()
                ->where('product_id', $variant->product_id)
                ->where('variant_code', 'like', $prefix . '%')
                ->orderBy('variant_code', 'desc')
                ->value('variant_code');

            $next = 1;

            if ($lastCode) {
                $next = (int) substr($lastCode, -2) + 1;
            }

            $variant->variant_code =
                $prefix . str_pad($next, 2, '0', STR_PAD_LEFT);
        });
    }

    protected $fillable = [
        'picture',
        'product_id',

        'variant_code',
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

    protected $guarded = ['variant_code'];


    // Casts
    protected $casts = [
        'is_active' => 'boolean',
    ];

    // scope
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Relationships
    public function product()
    {
        return $this->belongsTo(MstProduct::class, 'product_id');
    }

    // category through product
    public function category()
    {
        return $this->hasOneThrough(
            MstProductCategory::class,
            MstProduct::class,
            'id',               // Foreign key on MstProduct table...
            'id',               // Foreign key on MstProductCategory table...
            'product_id',      // Local key on MstProductVariant table...
            'category_id'      // Local key on MstProduct table...
        );
    }

   
}
