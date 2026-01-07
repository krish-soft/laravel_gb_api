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
        Schema::create('one_time_passwords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('request_id', 100)->unique();

            // Purpose of OTP
            $table->string('purpose', 30);
            $table->string('channel', 20);

            // Recipient identifiers
            $table->string('dial_code', 5)->nullable();      // +91
            $table->string('phone_number', 20)->nullable();
            $table->string('email')->nullable();

            // OTP data
            $table->string('otp_code', 10);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();

            // Security & control
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['purpose', 'channel']);
            $table->index(['phone_number', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('one_time_passwords');
    }
};
