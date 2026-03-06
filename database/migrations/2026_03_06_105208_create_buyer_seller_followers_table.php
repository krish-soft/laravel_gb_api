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
        Schema::create('buyer_seller_followers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('buyer_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('seller_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();

            // status of follow request: pending, accepted, rejected
            $table->boolean('is_following')->default(true)->nullable(); // true if accepted, false if pending or rejected
            $table->timestamp('followed_at')->nullable(); // when the follow request was accepted
            $table->timestamp('unfollowed_at')->nullable(); // when the follow request was accepted

            $table->string('follow_source')->default('app')->nullable(); // app, web, ap

            $table->boolean('is_notification_enabled')->default(true)->nullable(); // if buyer wants to receive notifications about seller updates

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_seller_followers');
    }
};
