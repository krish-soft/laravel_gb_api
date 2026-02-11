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
        Schema::create('shipment_package_groups', function (Blueprint $table) {
            $table->id();

            // Group/bunch code (driver will see this)
            $table->string('group_number', 30)->index();

            // Optional to calulate same seller o buyer then add in same group 
            $table->foreignId('buyer_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('seller_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Which shipment route
            $table->foreignId('shipment_id')
                ->constrained('shipments')
                ->cascadeOnDelete();

            // Which physical package
            $table->foreignId('shipment_package_id')
                ->constrained('shipment_packages')
                ->cascadeOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipment_package_groups');
    }
};
