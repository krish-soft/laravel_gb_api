<?php

namespace App\Models\Master\Product;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MstProductCategory extends BaseModel
{
    //

    use SoftDeletes;


    protected static function booted()
    {
        static::creating(function ($category) {

            // If code already provided (eg: seeder/manual), do nothing
            if (!empty($category->category_code)) {
                return;
            }

            if (empty($category->hsn_chapter)) {
                throw new \RuntimeException('HSN chapter is required to generate category code.');
            }

            // Find last category code for this HSN chapter
            $lastCode = self::withTrashed()
                ->where('hsn_chapter', $category->hsn_chapter)
                ->where('category_code', 'like', $category->hsn_chapter . 'C%')
                ->orderBy('category_code', 'desc')
                ->value('category_code');

            $next = 1;

            if ($lastCode) {
                $next = (int) substr($lastCode, -4) + 1;
            }

            $category->category_code =
                $category->hsn_chapter . 'C' . str_pad($next, 4, '0', STR_PAD_LEFT);
        });
    }

    protected $fillable = [
        'picture',
        'category_code',
        'name',
        'description',

        'hsn_chapter',
        'is_active',

        'custchar1',
        'custchar2',
    ];

    protected $guarded = ['category_code'];


    protected $casts = [
        'is_active' => 'boolean',
    ];

    // scope
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Relationships
    public function products()
    {
        return $this->hasMany(MstProduct::class, 'category_id');
    }
}
