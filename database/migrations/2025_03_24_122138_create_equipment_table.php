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
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number')->unique();
            $table->string('name');
            $table->text('description');
            $table->unsignedBigInteger('service_provider_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->date('date_of_manufacturing');
            $table->date('expiry_date');
            $table->boolean('isActive')->default(false);
             $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_type')->nullable(); // Store the type of user who created the equipment
            $table->timestamps();
            $table->foreign('service_provider_id')->references('id')->on('service_providers')->onDelete('set null');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('equipment');
    }
};
