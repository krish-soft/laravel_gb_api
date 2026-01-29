<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // Identity & profile
            $table->string('picture')->nullable();

            $table->string('user_code', 20)->unique();      // internal public code / customer no
            $table->string('name');
            $table->string('nickname')->nullable()->unique(); // To mask real name

            // Auth: phone & email
            $table->string('dial_code', 5)->default('91');
            $table->string('phone_number', 15)->nullable();
            $table->timestamp('phone_number_verified_at')->nullable();

            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Role & type (use enums/constants in code)
            $table->string('role', 30)->nullable();         // admin, seller, buyer, delivery
            $table->string('user_type', 30)->nullable();    // farmer, trader, etc.
            $table->string('user_key', 100)->nullable();    // external system integration key

            // Status flags
            $table->boolean('is_test_user')->default(false)->nullable();
            $table->boolean('is_sales_rep')->default(false)->nullable();
            $table->boolean('is_important')->default(false)->nullable();

            $table->boolean('is_active')->default(true)->nullable();    // block/unblock login
            $table->string('inactive_reason', 100)->nullable();

            // other to map table 
            $table->string('charge_level_code', 50)->nullable();
            $table->string('kyc_code', 50)->nullable(); // current KYC code
            $table->string('sales_rep', 50)->nullable(); // To Identify who get onboard this user

            $table->string('bill_addr_code', 50)->nullable(); // Billing Address Code
            $table->string('addr_code', 50)->nullable(); // Shipping Address Code


            // Tracking
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->json('access_modules')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Indexes & unique constraints
            $table->unique(['phone_number', 'deleted_at']);
            $table->unique(['email', 'deleted_at']);

            $table->index('role');
            $table->index('user_code');
            $table->index('user_type');
            $table->index('is_active');
        });


        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
