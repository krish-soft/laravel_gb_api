<?php

use App\Enum\Common\Payment\PaymentMethodEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        Schema::create('mst_payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_code', 20)->unique(); // To Pick First not base on id 

            $table->string('payment_in_mode', 30)->nullable();
            $table->string('payment_out_mode', 30)->nullable();

            $table->decimal('min_payout_amount', 10, 2)->default(100);
            $table->decimal('max_payout_amount', 10, 2)->default(15000);

            $table->decimal('min_cart_order_amount', 10, 2)->default(2500);
            $table->decimal('max_cart_order_amount', 10, 2)->default(15000);

            $table->string('payout_cycle', 20)->default('weekly')->nullable();

            $table->integer('refund_window_days')->default(7);

            $table->integer('max_payment_attempts')->default(2);
            $table->integer('cart_expiry_minutes')->default(30);


            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_payment_settings');
    }
};
