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
        Schema::create('mst_business_settings', function (Blueprint $table) {
            $table->id();

            $table->string('picture')->nullable();

            // Business identity
            $table->string('legal_name')->default('Green Bazar (Krishna Software Pvt Ltd)')->nullable();
            $table->string('trade_name')->nullable();

            // GST & tax
            $table->string('gst_number')->nullable();
            $table->string('gst_state_code', 2)->nullable();
            $table->boolean('is_gst_enabled')->default(false)->nullable();

            // Legal identity
            $table->text('cin_number')->nullable();
            $table->text('pan_number')->nullable();
            $table->text('tan_number')->nullable();

            // Contact
            $table->string('email')->nullable();
            $table->string('phone_number')->nullable();

            // Address (invoice / app use)
            $table->string('bill_addr_code')->nullable();
            $table->string('addr_code')->nullable();

            // Online presence
            $table->string('website')->nullable();
            $table->text('terms_url')->nullable();
            $table->text('privacy_url')->nullable();

            // Other
            $table->text('notes')->nullable();

            $table->boolean('is_active')->default(true)->nullable();


            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mst_business_settings');
    }
};
