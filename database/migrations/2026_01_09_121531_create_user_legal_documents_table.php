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
        Schema::create('user_legal_documents', function (Blueprint $table) {
            $table->id();

            // Owner
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('user_code', 20)->nullable();

            // Optional link to KYC
            $table->foreignId('user_kyc_id')
                ->nullable()
                ->constrained('user_kycs')
                ->nullOnDelete();


            $table->string('legal_doc_code', 20)->unique();
            // Document type
            $table->string('document_type', 50); // pan | aadhaar | driving_license | rc_book | gst | shop_license | other

            // Document number (ENCRYPTED + MASKED)
            $table->string('name', 150)->nullable(); // without encryption for searching

            $table->string('document_number')->nullable(); // without encryption for searching
            $table->text('document_number_encrypted')->nullable();
            $table->string('document_number_last4', 4);

            // Validity (where applicable)
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();

            // Storage (private path / object key)
            $table->text('document_path_front')->nullable();
            $table->text('document_path_back')->nullable();

            // Review status
            $table->string('status', 20)->default('pending')->nullable(); // pending | needs_update | approved | rejected

            // Review audit
            $table->text('review_comment')->nullable(); // user-visible

            $table->string('verification_mode', 20)->nullable();    // manual | system

            $table->timestamp('verified_at')->nullable();
            $table->string('verified_by', 100)->nullable();
            $table->foreignId('verified_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'document_type']);
            $table->index('user_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_legal_documents');
    }
};
