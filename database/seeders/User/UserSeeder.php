<?php

namespace Database\Seeders\User;

use App\Enum\Admin\AdminRoleEnum;
use App\Enum\Admin\AdminUserTypeEnum;
use App\Enum\User\UserRoleEnum;
use App\Enum\User\UserTypeEnum;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin User


        User::create([
            'id' => 1,
            'user_code' => 'developer01',
            'nickname' =>  User::generateUniqueNickName(AdminUserTypeEnum::DEVELOPER->value),
            'name' => 'Developer User',

            'email' => 'developer@krishnasoftware.com',
            'password' => bcrypt('password'),

            'role' =>  AdminRoleEnum::SUPERADMIN->value,
            'user_type' => AdminUserTypeEnum::DEVELOPER->value,
            'email_verified_at' => now(),

            'is_active' => true,
            'is_test_user' => false,

            'access_modules' => '*',

            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::create([
            'id' => 2,
            'user_code' => 'krunal-rana',
            'nickname' =>  User::generateUniqueNickName(AdminUserTypeEnum::DEVELOPER->value),
            'name' => 'Krunal Rana',

            'email' => 'krunal@lionheartapps.com',
            'password' => bcrypt('Password@2026!'),

            'role' =>  AdminRoleEnum::SUPERADMIN->value,
            'user_type' => AdminUserTypeEnum::DEVELOPER->value,
            'email_verified_at' => now(),

            'is_active' => true,
            'is_test_user' => false,

            'access_modules' => '*',

            'created_at' => now(),
            'updated_at' => now(),
        ]);






        //
    }
}
