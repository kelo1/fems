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
        Schema::create('service_provider_vats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_provider_id');
            $table->decimal('VAT_RATE', 5, 2); // VAT rate as a percentage (e.g., 15.00 for 15%)
            $table->unsignedBigInteger('created_by'); // ID of the user who created the record
            $table->string('created_by_type'); // Type of the user who created the record
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('service_provider_id')->references('id')->on('service_providers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('service_provider_vats');
    }
};
