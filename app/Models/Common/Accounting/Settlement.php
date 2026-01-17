<?php

namespace App\Models\Common\Accounting;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Settlement extends BaseModel
{
    //
    use SoftDeletes;



    protected $fillable = [
        'from_entity_type',
        'from_entity_id',
        'to_entity_type',
        'to_entity_id',
        'amount',
        'reason',
        'source_type',
        'source_id',
        'status',
        'settled_at',
        'payment_id',
        'wallet_transaction_id',
    ];
}
