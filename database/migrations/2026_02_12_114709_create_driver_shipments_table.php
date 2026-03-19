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
        Schema::create('driver_shipments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->cascadeOnDelete(); // becasue reassign to other driver possible
            $table->foreignId('driver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('driver_vehicle_id')->nullable()->constrained('driver_vehicles')->nullOnDelete();
            $table->string('proof_image_path')->nullable();

            $table->date('assigned_date')->nullable();

            // who assigned
            $table->foreignId('assigned_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // snapshot
            $table->string('vehicle_number')->nullable();

            $table->string('status', 30)->default('assigned')->index(); // assigned/accepted/started/completed/cancelled

            $table->string('remarks')->nullable();


            $table->timestamps();
            $table->softDeletes();

            $table->index(['shipment_id', 'deleted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_shipments');
    }
};
