<?php

namespace App\Models\Common\Payment;

use App\Models\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Payment extends BaseModel
{
    //
    use SoftDeletes;


    protected $fillable = [
        'user_id',

        'payment_uuid',
        'payment_date',
        'payment_code',

        'source_type',
        'source_id',
        'source_code',

        'currency',
        'amount',
        'tax_amount',
        'fee_amount',
        'net_amount',

        'payment_type',
        'payment_method',
        'gateway',
        'status',

        'gateway_order_id',
        'gateway_payment_id',
        'gateway_signature',

        'attempt_no',
        'is_final',

        'failure_code',
        'failure_reason',

        'refunded_amount',
        'is_refunded',

        'meta',
        'paid_via', // new field for payment method used

        'payment_url', // new field for payment URL

        'paid_at',
        'failed_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_final' => 'boolean',
        'is_refunded' => 'boolean',
        'paid_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    // Relationship
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /* =====================================================
     | BOOT
     ===================================================== */
    protected static function booted()
    {
        static::creating(function ($payment) {
            $payment->payment_uuid ??= (string)Str::uuid();

            if (empty($payment->payment_date)) {
                $payment->payment_date = date('Y-m-d');
            }
        });
    }

    /* =====================================================
     | HELPERS
     ===================================================== */
    public function markPaid(string $gatewayPaymentId, array $meta = []): void
    {
        $this->update([
            'status' => 'paid',
            'gateway_payment_id' => $gatewayPaymentId,
            'is_final' => true,
            'paid_at' => now(),
            'meta' => array_merge($this->meta ?? [], $meta),
            'paid_via' => $meta['paid_via'] ?? null,
            'payment_url' => null, // clear payment URL on success
        ]);
    }

    public function markFailed(?string $code = null, ?string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'failure_code' => $code,
            'failure_reason' => $reason,
            'failed_at' => now(),
            // 'payment_url' => null, // clear payment URL on failure not necessary as it can be retried using the same URL
        ]);
    }

    public function markRefunded(float $amount): void
    {
        $this->update([
            'status' => 'refunded',
            'is_refunded' => true,
            'refunded_amount' => $amount,
        ]);
    }

    /* =====================================================
     | SOURCE RESOLUTION (OPTIONAL)
     ===================================================== */
    public function source()
    {
        if (!class_exists($this->source_type)) {
            return null;
        }

        return $this->source_type::find($this->source_id);
    }
}
