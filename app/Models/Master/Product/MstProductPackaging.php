<?php

namespace App\Models\Master\Product;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MstProductPackaging extends BaseModel
{
    //
    use SoftDeletes;


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'picture',

        'product_id',
        'pack_size',
        'pack_unit',
        'pack_type_unit',

        // Optional fields
        'length_in',
        'width_in',
        'height_in',
        'weight_kg',
        'volume_cu_in',

        'is_active',
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

    public function product()
    {
        return $this->belongsTo(MstProduct::class, 'product_id');
    }
}
