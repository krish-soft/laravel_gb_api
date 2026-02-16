<?php

namespace Database\Seeders\Master\Depot;

use App\Models\Common\Address;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MstMarketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //

        $address = Address::create([
            'addr_code' => Address::generateUniqueAddrCode(),
            'addr_name' => 'Sardar Market (APMC-Surat)',
            'addr_type' => 'MARKET',
            'address_line1' => 'Sardar Market, APMC Yard, Surat',
            'city' => 'Surat',
            'state' => 'Gujarat',
            'state_iso' => 'GJ',
            'postal_code' => '395003',
            'country' => 'India',
            'country_iso' => 'IN',
        ]);


        DB::table('mst_markets')->insert([
            [
                'name' => 'Sardar Market (APMC-Surat)',
                'code' => 'MKT0001',
                'addr_code' => $address->addr_code,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],

        ]);
    }
}
