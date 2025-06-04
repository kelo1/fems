<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('certificate_id'); // Foreign key to certificate_types
            $table->string('serial_number')->unique(); // Unique serial number for the certificate
            $table->unsignedBigInteger('client_id')->nullable(); // Required
            $table->unsignedBigInteger('fsa_id')->nullable(); // Allow NULL values for the foreign key            
            $table->boolean('isVerified')->default(false); // Indicates if the certificate is verified by FEMS Admin
            $table->tinyInteger('invoice_status')->default(0); // 0: Not Invoiced, 1: Invoiced, 2: Paid
            $table->string('certificate_upload')->nullable(); // Path to the uploaded certificate
            $table->date('issued_date'); // Date when the certificate was issued
            $table->date('expiry_date'); // Date when the certificate expires
            $table->string('status')->default('active'); // Status of the certificate (active, expired, etc.)
            $table->unsignedBigInteger('created_by');
            $table->string('created_by_type');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('certificate_id')->references('id')->on('certificate_types')->onDelete('cascade');
            $table->foreign('fsa_id')->references('id')->on('fire_service_agents')->onDelete('set null');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('certificates');
    }
};
