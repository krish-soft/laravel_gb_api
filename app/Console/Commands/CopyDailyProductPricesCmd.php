<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CopyDailyProductPricesCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prices:copy-daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy daily product prices from the previous day to today';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        DB::statement("INSERT INTO mst_product_prices
                        (product_id, product_code, price_date, price, max_price, min_price, market_id, depot_id, is_auto_created, created_at, updated_at)

                        SELECT
                            p.product_id,
                            p.product_code,
                            ?,
                            p.price,
                            p.max_price,
                            p.min_price,
                            p.market_id,
                            p.depot_id,
                            1,
                            NOW(),
                            NOW()

                        FROM mst_product_prices p

                        WHERE p.price_date = ?
                        AND NOT EXISTS (
                            SELECT 1
                            FROM mst_product_prices t
                            WHERE t.product_id = p.product_id
                            AND t.price_date = ?
                            AND IFNULL(t.market_id,0) = IFNULL(p.market_id,0)
                            AND IFNULL(t.depot_id,0) = IFNULL(p.depot_id,0)
                        )
                    ", [$today, $yesterday, $today]);

        $this->info('Daily prices copied safely.');
    }
}
