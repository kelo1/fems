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
            $table->unsignedBigInteger('client_id'); // Required
            $table->unsignedBigInteger('fsa_id'); // Foreign key to fire_service_agents
            $table->boolean('isVerified')->default(false); // Indicates if the certificate is verified by FEMS Admin
            $table->string('certificate_upload')->nullable(); // Path to the uploaded certificate
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
