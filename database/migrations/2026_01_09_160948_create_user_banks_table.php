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
        Schema::create('user_banks', function (Blueprint $table) {
            $table->id();

            // Owner
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Reference (human-readable)
            $table->string('bank_code', 20)->unique();

            // Bank details
            $table->string('account_holder_name', 120);

            $table->string('razorpay_contact_id')->nullable();
            $table->string('razorpay_fund_account_id')->nullable();


            // Sensitive (ENCRYPTED)
            $table->text('account_number_encrypted');
            $table->string('account_number_last4', 4);

            $table->string('ifsc_code', 11);
            $table->string('bank_name', 100)->nullable();
            $table->string('branch_name', 100)->nullable();

            // Account type
            $table->string('account_type', 20)->default('savings');
            // savings | current | business

            // Status
            $table->string('status', 20)->default('pending');
            // pending | verified | rejected | inactive

            $table->string('verification_mode', 20)->nullable();    // manual | system

            $table->boolean('test_deposit_required')->default(false)->nullable();
            $table->decimal('test_deposit_amount', 8, 2)->nullable(); // usually 1.00
            $table->string('test_deposit_ref', 50)->nullable();
            $table->timestamp('test_deposit_verified_at')->nullable();

            // Verification audit
            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by', 100)->nullable();
            $table->foreignId('verified_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Optional admin note
            $table->text('review_comment')->nullable();

            // Primary payout flag
            $table->boolean('is_primary')->default(false)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_primary']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_banks');
    }
};
