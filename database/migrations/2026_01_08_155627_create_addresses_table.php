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
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            $table->string('addr_code', 20)->unique();     // ADDR-USER-001 (system use)

            $table->string('addr_name', 150)->nullable();              // Home, Office, Farm A
            $table->string('addr_type', 30);            // billing, shipping, other

            // Address lines
            $table->string('address_line1', 120);
            $table->string('address_line2', 120)->nullable();
            $table->string('landmark', 80)->nullable();

            // Location hierarchy (India-focused)
            $table->string('village', 60)->nullable();
            $table->string('taluka', 60)->nullable();
            $table->string('district', 60)->nullable();
            $table->string('city', 60)->nullable();
            $table->string('state', 40)->nullable();
            $table->string('state_iso', 10)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('country', 40)->nullable();
            $table->string('country_iso', 10)->nullable();

            // Contact (optional)
            $table->string('contact_name', 60)->nullable();
            $table->string('dial_code', 4)->default('91');
            $table->string('phone_number', 12)->nullable();
            $table->string('email', 80)->nullable();

            // Geo (accurate & compact)
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('geo_tag', 100)->nullable(); //

            // Status
            $table->boolean('is_active')->default(true)->nullable();

            $table->string('custchar1', 100)->nullable();
            $table->string('custchar2', 100)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
