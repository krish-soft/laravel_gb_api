<?php

namespace Database\Seeders\Master;

use App\Models\Master\MstFinancialYear;
use Illuminate\Database\Seeder;

class MstFinancialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        MstFinancialYear::create([
            'code'       => '2025-2026',
            'name'       => 'Financial Year 2025-2026',
            'start_date' => '2025-04-01',
            'end_date'   => '2026-03-31',
            'is_active'  => true,
        ]);

        MstFinancialYear::create([
            'code'       => '2024-2025',
            'name'       => 'Financial Year 2024-2025',
            'start_date' => '2024-04-01',
            'end_date'   => '2025-03-31',
            'is_active'  => false,
        ]);
    }
}
