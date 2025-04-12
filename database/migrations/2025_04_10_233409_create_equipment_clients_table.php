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
        Schema::create('equipment_clients', function (Blueprint $table) {
           
                $table->id();
                $table->unsignedBigInteger('equipment_id');
                $table->string('serial_number');
                $table->unsignedBigInteger('client_id');
                $table->boolean('status_client')->default(1); // Default to 1 (active)
                $table->timestamps();
    
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
        Schema::dropIfExists('equipment_clients');
    }
};
