<?php

namespace App\Models\Master\Price;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MstProductPrice extends Model
{
    //

    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'product_code',
        
        'price_date',
        'price',

        'min_price',
        'max_price',

        // Future Use
        'market_id',
        'depot_id',
        'is_auto_created',
    ];


    // casts

    protected $casts = [
        'price_date' => 'date:Y-m-d',
        'price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'min_price' => 'decimal:2',

        'is_auto_created' => 'boolean',
    ];


    // relationships
    public function product()
    {
        return $this->belongsTo(\App\Models\Master\Product\MstProduct::class, 'product_id', 'id');
    }

    public function market()
    {
        return $this->belongsTo(\App\Models\Master\Market\MstMarket::class, 'market_id', 'id');
    }

    public function depot()
    {
        return $this->belongsTo(\App\Models\Master\Depot\MstDepot::class, 'depot_id', 'id');
    }
}
