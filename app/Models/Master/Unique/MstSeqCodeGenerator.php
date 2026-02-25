<?php

namespace App\Models\Master\Unique;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class MstSeqCodeGenerator extends Model
{
    //

    use SoftDeletes;

    protected $fillable = [
        'seq_no',
        'ref_no',
        'order_no',
        'invoice_no',
        'doc_no',
        'rule_no',
        'other_no',
        'market_order_no',
    ];

    /**
     * Generic incrementer (transaction-safe)
     */
    protected static function nextValue(string $column): int
    {
        return DB::transaction(function () use ($column) {
            $row = self::lockForUpdate()->first();

            if (!$row) {
                $row = self::create([$column => 1]);
                return 1;
            }

            $row->{$column} = ($row->{$column} ?? 0) + 1;
            $row->save();

            return $row->{$column};
        });
    }

    /* ===========================
       Public Sequence Methods
       =========================== */

    public static function getNextSeqNo(): int
    {
        return self::nextValue('seq_no');
    }

    public static function getNextRefNo(): int
    {
        return self::nextValue('ref_no');
    }

    public static function getNextOrderNo(): int
    {
        return self::nextValue('order_no');
    }

    public static function getNextInvoiceNo(): int
    {
        return self::nextValue('invoice_no');
    }

    public static function getNextMarketOrderNo(): int
    {
        return self::nextValue('market_order_no');
    }

    public static function getNextDocNo(): int
    {
        return self::nextValue('doc_no');
    }

    public static function getNextRuleNo(): int
    {
        return self::nextValue('rule_no');
    }

    public static function getNextOtherNo(): int
    {
        return self::nextValue('other_no');
    }
}
