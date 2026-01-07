<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([

            // App Settings
            \Database\Seeders\Setting\AppSettingSeeder::class,

            // User Testing
            \Database\Seeders\User\UserSeeder::class,

            // Other seeders can be added here
        ]);
    }
}
