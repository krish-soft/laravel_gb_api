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
        //

        User::create([

            'user_code' => 'developer01',
            'nickname' =>  User::generateUniqueNickName(AdminUserTypeEnum::DEVELOPER->value),
            'name' => 'Developer Test',

            'email' => 'developer@krishnasoftware.com',
            'password' => bcrypt('password'),

            'role' =>  AdminRoleEnum::SUPERADMIN->value,
            'user_type' => AdminUserTypeEnum::DEVELOPER->value,
            'email_verified_at' => now(),

            'is_active' => true,
            'is_test_user' => true,

            'access_modules' => '*',

            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::create([

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

        User::create([

            'user_code' => 'buyer01',
            'nickname' => User::generateUniqueNickName(UserTypeEnum::TRADER->value),
            'name' => 'Buyer Test',

            'dial_code' => '91',
            'phone_number' => '7777777777',
            'password' => bcrypt('password'),

            'role' => UserRoleEnum::BUYER->value,
            'user_type' => UserTypeEnum::TRADER->value,
            'phone_number_verified_at' => now(),

            'is_active' => true,
            'is_test_user' => true,

            'created_at' => now(),
            'updated_at' => now(),
        ]);



        User::create([

            'user_code' => 'seller01',
            'nickname' => User::generateUniqueNickName(UserTypeEnum::FARMER->value),
            'name' => 'Seller Test',

            'dial_code' => '91',
            'phone_number' => '8888888888',
            'password' => bcrypt('password'),

            'role' => UserRoleEnum::SELLER->value,
            'user_type' => UserTypeEnum::FARMER->value,
            'phone_number_verified_at' => now(),

            'is_active' => true,
            'is_test_user' => true,

            'created_at' => now(),
            'updated_at' => now(),
        ]);


        User::create([

            'user_code' => 'delivery01',
            'nickname' => User::generateUniqueNickName(UserTypeEnum::DELIVERY->value),
            'name' => 'Delivery Test',

            'dial_code' => '91',
            'phone_number' => '6666666666',
            'password' => bcrypt('password'),

            'role' => UserRoleEnum::DELIVERY->value,
            'user_type' => UserTypeEnum::DELIVERY->value,
            'phone_number_verified_at' => now(),

            'is_active' => true,
            'is_test_user' => true,

            'created_at' => now(),
            'updated_at' => now(),
        ]);




        //
    }
}
