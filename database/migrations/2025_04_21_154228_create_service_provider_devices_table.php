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
        Schema::create('service_provider_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_serial_number')->unique(); // Unique and not null
            $table->unsignedBigInteger('service_provider_id')->nullable(); // Nullable foreign key
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('service_provider_id')->references('id')->on('service_providers')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('service_provider_devices');
    }
};
