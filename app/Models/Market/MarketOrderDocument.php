<?php

namespace App\Models\Market;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;

class MarketOrderDocument extends BaseModel
{
    //

    use SoftDeletes;


    protected $fillable = [
        'market_order_id',
        'document_type',
        'document_path',
        'remarks',
    ];


    // Relationships

    public function marketOrder()
    {
        return $this->belongsTo(MarketOrder::class, 'market_order_id');
    }


    // append file URL accessor

    protected $appends = ['document_url'];

    public function getDocumentUrlAttribute()
    {
        return $this->document_path_back && !$this->is_verified
            ? URL::temporarySignedRoute(
                'files.view',
                now()->addMinutes(5),
                ['path' => $this->document_path]
            )
            : null;
    }
}
