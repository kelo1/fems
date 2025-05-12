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
        Schema::create('equipment_activities', function (Blueprint $table) {
            $table->id();
            $table->string('activity')->nullable(); // Activity description
            $table->date('next_maintenance_date')->nullable(); // Next maintenance date
            $table->unsignedBigInteger('service_provider_id')->nullable(); // Service provider ID
            $table->unsignedBigInteger('client_id')->nullable(); // Client ID
            $table->unsignedBigInteger('created_by'); // Created by user ID
            $table->unsignedBigInteger('equipment_id')->nullable(); // Equipment ID
            $table->string('device_serial_number')->nullable(); // Equipment serial number
            $table->string('created_by_type'); // Created by user type
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
            $table->foreign('service_provider_id')->references('id')->on('service_providers')->onDelete('cascade');
            $table->foreign('equipment_id')->references('id')->on('equipment')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('equipment_activities');
    }
};
